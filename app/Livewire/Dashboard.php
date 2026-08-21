<?php

namespace App\Livewire;

use App\Enums\GoodsIssueStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The operations dashboard.
 *
 * Everything here is derived, so the component only reads. The trend window is
 * selectable (6, 12 or 24 months), and the stock rows are resolved once per
 * request so switching the window does not recompute them.
 *
 * Stock is valued at the weighted average purchase cost, and order figures
 * exclude drafts and cancellations - the same definitions the order screens use,
 * taken from the status enums so the two cannot drift.
 */
#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** Trend windows offered, in months. */
    public const WINDOWS = [6, 12, 24];

    #[Url(as: 'months', except: 12, history: true)]
    public int $months = 12;

    /** Stock rows, resolved once per request. */
    private ?Collection $stockRows = null;

    public function updatedMonths(): void
    {
        if (! in_array($this->months, self::WINDOWS, true)) {
            $this->months = 12;
        }
    }

    /**
     * Headline figures.
     *
     * @return array<string, float|int>
     */
    public function stats(): array
    {
        $rows = $this->rows();

        $stockValue = $rows->sum(fn (object $row): float => $row->stock * $row->avg_unit_cost);
        $purchaseValue = (float) PurchaseOrder::query()->live()->sum('grand_total');
        $salesValue = (float) SalesOrder::query()->live()->sum('grand_total');
        $soldCost = $this->soldCost();

        return [
            'materials' => $rows->count(),
            'stock_qty' => Money::quantity($rows->sum('stock')),
            'stock_value' => Money::round($stockValue),
            'low_stock' => $this->lowStock()->count(),
            'out_of_stock' => $rows->where('stock', '<=', 0)->count(),
            'purchase_value' => Money::round($purchaseValue),
            'sales_value' => Money::round($salesValue),
            'open_po' => PurchaseOrder::query()->open()->count(),
            'open_so' => SalesOrder::query()->open()->count(),
            'vendors' => Vendor::query()->count(),
            'customers' => Customer::query()->count(),
            // Margin on what has actually shipped, not on list prices.
            'shipped_revenue' => Money::round($this->soldRevenue()),
            'shipped_cost' => Money::round($soldCost),
            'gross_margin' => Money::round($this->soldRevenue() - $soldCost),
            'margin_percent' => $this->soldRevenue() > 0
                ? round((($this->soldRevenue() - $soldCost) / $this->soldRevenue()) * 100, 1)
                : null,
        ];
    }

    /**
     * Purchases against sales per month, for the selected window.
     *
     * @return array<string, array<int, string|float>>
     */
    public function trend(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($this->months - 1);

        $purchases = $this->monthlyTotals('purchase_orders', PurchaseOrderStatus::liveValues(), $start);
        $sales = $this->monthlyTotals('sales_orders', SalesOrderStatus::liveValues(), $start);

        $labels = [];
        $purchaseSeries = [];
        $salesSeries = [];

        for ($index = 0; $index < $this->months; $index++) {
            $month = $start->copy()->addMonths($index);
            $key = $month->format('Y-m');

            $labels[] = $month->format($this->months > 12 ? 'M y' : 'M Y');
            $purchaseSeries[] = Money::round($purchases[$key] ?? 0);
            $salesSeries[] = Money::round($sales[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'purchases' => $purchaseSeries,
            'sales' => $salesSeries,
        ];
    }

    /**
     * Stock value by category, largest first, with the tail folded into Others.
     *
     * @return array<int, array{category: string, value: float}>
     */
    public function stockByCategory(): array
    {
        $grouped = $this->rows()
            ->groupBy(fn (object $row): string => $row->category ?? 'Uncategorised')
            ->map(fn (Collection $rows, string $category): array => [
                'category' => $category,
                'value' => Money::round($rows->sum(fn (object $row): float => $row->stock * $row->avg_unit_cost)),
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->sortByDesc('value')
            ->values();

        if ($grouped->count() <= 6) {
            return $grouped->all();
        }

        return $grouped->take(5)
            ->push([
                'category' => 'Others',
                'value' => Money::round($grouped->slice(5)->sum('value')),
            ])
            ->all();
    }

    /**
     * Materials holding the most stock value.
     *
     * @return array<int, array{code: string, name: string, value: float}>
     */
    public function topStockValue(): array
    {
        return $this->rows()
            ->map(fn (object $row): array => [
                'code' => $row->code,
                'name' => $row->name,
                'value' => Money::round($row->stock * $row->avg_unit_cost),
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->sortByDesc('value')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Materials that have shipped the most value.
     *
     * @return array<int, array{code: string, name: string, value: float}>
     */
    public function topSoldValue(): array
    {
        $sold = $this->soldValueByMaterial();

        return $this->rows()
            ->map(fn (object $row): array => [
                'code' => $row->code,
                'name' => $row->name,
                'value' => Money::round($sold[$row->id] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->sortByDesc('value')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Materials at or below their reorder level, most urgent first.
     *
     * @return Collection<int, object>
     */
    public function lowStock(): Collection
    {
        return $this->rows()
            ->filter(fn (object $row): bool => $row->reorder_level > 0 && $row->stock <= $row->reorder_level)
            ->sortBy(fn (object $row): float => $row->reorder_level > 0 ? $row->stock / $row->reorder_level : 0)
            ->values();
    }

    /**
     * Open documents by status, for both flows.
     *
     * @return array<string, array<int, array{status: string, total: int}>>
     */
    public function orderStatus(): array
    {
        return [
            'purchase' => $this->statusCounts('purchase_orders', PurchaseOrderStatus::class),
            'sales' => $this->statusCounts('sales_orders', SalesOrderStatus::class),
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }

    /**
     * One row per material with its stock, costing and category.
     *
     * @return Collection<int, object>
     */
    private function rows(): Collection
    {
        return $this->stockRows ??= DB::table('materials')
            ->leftJoin('inventories', fn ($join) => $join
                ->on('inventories.material_id', '=', 'materials.id')
                ->whereNull('inventories.deleted_at'))
            ->leftJoin('categories', 'categories.id', '=', 'materials.category_id')
            ->leftJoin('uoms', 'uoms.id', '=', 'materials.uom_id')
            ->whereNull('materials.deleted_at')
            ->groupBy(
                'materials.id', 'materials.code', 'materials.name',
                'materials.avg_unit_cost', 'materials.reorder_level',
                'categories.name', 'uoms.acronym',
            )
            ->select([
                'materials.id',
                'materials.code',
                'materials.name',
                'materials.reorder_level',
                'materials.avg_unit_cost',
                'categories.name as category',
                'uoms.acronym as uom',
                DB::raw('COALESCE(SUM(inventories.quantity), 0) as stock'),
            ])
            ->get()
            ->map(function (object $row): object {
                $row->stock = (float) $row->stock;
                $row->avg_unit_cost = (float) $row->avg_unit_cost;
                $row->reorder_level = (float) $row->reorder_level;

                return $row;
            });
    }

    /**
     * Revenue shipped on completed goods issues.
     */
    private function soldRevenue(): float
    {
        return (float) DB::table('goods_issue_items')
            ->join('goods_issues', 'goods_issues.id', '=', 'goods_issue_items.goods_issue_id')
            ->where('goods_issues.status', GoodsIssueStatus::Completed->value)
            ->whereNull('goods_issues.deleted_at')
            ->sum(DB::raw('goods_issue_items.qty_to_ship * goods_issue_items.unit_price'));
    }

    /**
     * What that shipped stock cost, at each material's average purchase cost.
     */
    private function soldCost(): float
    {
        return (float) DB::table('goods_issue_items')
            ->join('goods_issues', 'goods_issues.id', '=', 'goods_issue_items.goods_issue_id')
            ->join('materials', 'materials.id', '=', 'goods_issue_items.material_id')
            ->where('goods_issues.status', GoodsIssueStatus::Completed->value)
            ->whereNull('goods_issues.deleted_at')
            ->sum(DB::raw('goods_issue_items.qty_to_ship * materials.avg_unit_cost'));
    }

    /**
     * Shipped value per material id.
     *
     * @return array<int, float>
     */
    private function soldValueByMaterial(): array
    {
        return DB::table('goods_issue_items')
            ->join('goods_issues', 'goods_issues.id', '=', 'goods_issue_items.goods_issue_id')
            ->where('goods_issues.status', GoodsIssueStatus::Completed->value)
            ->whereNull('goods_issues.deleted_at')
            ->groupBy('goods_issue_items.material_id')
            ->select('goods_issue_items.material_id')
            ->selectRaw('SUM(goods_issue_items.qty_to_ship * goods_issue_items.unit_price) as sold_value')
            ->pluck('sold_value', 'material_id')
            ->map(fn (mixed $value): float => (float) $value)
            ->all();
    }

    /**
     * Grand totals per month, keyed `YYYY-MM`.
     *
     * @param  array<int, string>  $statuses
     * @return array<string, float>
     */
    private function monthlyTotals(string $table, array $statuses, Carbon $start): array
    {
        return DB::table($table)
            ->whereNull('deleted_at')
            ->whereIn('status', $statuses)
            ->where('order_date', '>=', $start->toDateString())
            ->select('order_date', 'grand_total')
            ->get()
            ->groupBy(fn (object $row): string => substr((string) $row->order_date, 0, 7))
            ->map(fn (Collection $rows): float => (float) $rows->sum('grand_total'))
            ->all();
    }

    /**
     * Document counts per status, drafts excluded, labelled through the enum.
     *
     * @param  class-string  $enum
     * @return array<int, array{status: string, total: int}>
     */
    private function statusCounts(string $table, string $enum): array
    {
        return DB::table($table)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'draft')
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status')
            ->map(fn (mixed $total, string $status): array => [
                'status' => $enum::tryFrom($status)?->label() ?? str_replace('_', ' ', $status),
                'total' => (int) $total,
            ])
            ->values()
            ->all();
    }
}
