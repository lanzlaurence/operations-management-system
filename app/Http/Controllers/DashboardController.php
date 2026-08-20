<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /** Statuses that represent a live (non-draft, non-cancelled) order. */
    private const OPEN_PO_STATUSES = ['posted', 'partially_received'];
    private const OPEN_SO_STATUSES = ['posted', 'partially_shipped'];
    private const LIVE_PO_STATUSES = ['posted', 'partially_received', 'fully_received'];
    private const LIVE_SO_STATUSES = ['posted', 'partially_shipped', 'fully_shipped'];

    public function index()
    {
        $stockRows = $this->stockRows();
        $soldByMaterial = $this->soldValueByMaterial();

        $stockValue = $stockRows->sum(fn($r) => $r->stock * $r->avg_unit_cost);
        $lowStock = $stockRows
            ->filter(fn($r) => $r->reorder_level > 0 && $r->stock <= $r->reorder_level)
            ->sortBy(fn($r) => $r->reorder_level > 0 ? $r->stock / $r->reorder_level : 0)
            ->values();

        return Inertia::render('dashboard', [
            'stats' => [
                'materials'      => $stockRows->count(),
                'stock_qty'      => round($stockRows->sum('stock'), 2),
                'stock_value'    => round($stockValue, 2),
                'low_stock'      => $lowStock->count(),
                'out_of_stock'   => $stockRows->where('stock', '<=', 0)->count(),
                'purchase_value' => round((float) PurchaseOrder::whereIn('status', self::LIVE_PO_STATUSES)->sum('grand_total'), 2),
                'sales_value'    => round((float) SalesOrder::whereIn('status', self::LIVE_SO_STATUSES)->sum('grand_total'), 2),
                'open_po'        => PurchaseOrder::whereIn('status', self::OPEN_PO_STATUSES)->count(),
                'open_so'        => SalesOrder::whereIn('status', self::OPEN_SO_STATUSES)->count(),
                'vendors'        => Vendor::count(),
                'customers'      => Customer::count(),
            ],
            'monthlyTrend'   => $this->monthlyTrend(),
            'stockByCategory' => $this->stockByCategory($stockRows),
            'topStockValue'  => $stockRows
                ->map(fn($r) => [
                    'code'  => $r->code,
                    'name'  => $r->name,
                    'value' => round($r->stock * $r->avg_unit_cost, 2),
                ])
                ->where('value', '>', 0)
                ->sortByDesc('value')
                ->take(8)
                ->values(),
            'topSoldValue'   => $stockRows
                ->map(fn($r) => [
                    'code'  => $r->code,
                    'name'  => $r->name,
                    'value' => round((float) ($soldByMaterial[$r->id] ?? 0), 2),
                ])
                ->where('value', '>', 0)
                ->sortByDesc('value')
                ->take(8)
                ->values(),
            'lowStockItems'  => $lowStock
                ->take(10)
                ->map(fn($r) => [
                    'id'            => $r->id,
                    'code'          => $r->code,
                    'name'          => $r->name,
                    'uom'           => $r->uom,
                    'stock'         => round($r->stock, 2),
                    'reorder_level' => (float) $r->reorder_level,
                ])
                ->values(),
            'orderStatus'    => [
                'purchase' => $this->statusCounts(PurchaseOrder::query()),
                'sales'    => $this->statusCounts(SalesOrder::query()),
            ],
        ]);
    }

    /** One row per material with its total on-hand stock, costing and category. */
    private function stockRows()
    {
        return DB::table('materials')
            ->leftJoin('inventories', fn($j) => $j
                ->on('inventories.material_id', '=', 'materials.id')
                ->whereNull('inventories.deleted_at'))
            ->leftJoin('categories', 'categories.id', '=', 'materials.category_id')
            ->leftJoin('uoms', 'uoms.id', '=', 'materials.uom_id')
            ->whereNull('materials.deleted_at')
            ->groupBy('materials.id', 'materials.code', 'materials.name', 'materials.avg_unit_cost', 'materials.reorder_level', 'categories.name', 'uoms.acronym')
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
            ->each(function ($row) {
                $row->stock = (float) $row->stock;
                $row->avg_unit_cost = (float) $row->avg_unit_cost;
                $row->reorder_level = (float) $row->reorder_level;
            });
    }

    /** material_id => total value shipped on completed goods issues. */
    private function soldValueByMaterial(): array
    {
        return DB::table('goods_issue_items')
            ->join('goods_issues', 'goods_issues.id', '=', 'goods_issue_items.goods_issue_id')
            ->where('goods_issues.status', 'completed')
            ->whereNull('goods_issues.deleted_at')
            ->groupBy('goods_issue_items.material_id')
            ->select('goods_issue_items.material_id')
            ->selectRaw('SUM(goods_issue_items.qty_to_ship * goods_issue_items.unit_price) as sold_value')
            ->get()
            ->mapWithKeys(fn($row) => [$row->material_id => (float) $row->sold_value])
            ->all();
    }

    /** Purchases vs sales totals for each of the last 12 months. */
    private function monthlyTrend(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(11);

        $purchases = $this->monthlyTotals('purchase_orders', self::LIVE_PO_STATUSES, $start);
        $sales = $this->monthlyTotals('sales_orders', self::LIVE_SO_STATUSES, $start);

        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $months[] = [
                'month'     => $month->format('M Y'),
                'purchases' => round((float) ($purchases[$key] ?? 0), 2),
                'sales'     => round((float) ($sales[$key] ?? 0), 2),
            ];
        }

        return $months;
    }

    /** Grouped in PHP rather than SQL so the query stays driver-agnostic. */
    private function monthlyTotals(string $table, array $statuses, Carbon $start): array
    {
        return DB::table($table)
            ->whereNull('deleted_at')
            ->whereIn('status', $statuses)
            ->where('order_date', '>=', $start->toDateString())
            ->select('order_date', 'grand_total')
            ->get()
            ->groupBy(fn($row) => substr((string) $row->order_date, 0, 7))
            ->map(fn($rows) => (float) $rows->sum('grand_total'))
            ->all();
    }

    /** Stock value grouped by category, largest first, with the tail folded into "Others". */
    private function stockByCategory($stockRows): array
    {
        $grouped = $stockRows
            ->groupBy(fn($r) => $r->category ?? 'Uncategorized')
            ->map(fn($rows, $category) => [
                'category' => $category,
                'value'    => round($rows->sum(fn($r) => $r->stock * $r->avg_unit_cost), 2),
            ])
            ->filter(fn($row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values();

        if ($grouped->count() <= 6) {
            return $grouped->all();
        }

        $top = $grouped->take(5);
        $others = $grouped->slice(5);

        return $top->push([
            'category' => 'Others',
            'value'    => round($others->sum('value'), 2),
        ])->all();
    }

    private function statusCounts($query): array
    {
        return $query
            ->where('status', '!=', 'draft')
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status')
            ->map(fn($total, $status) => [
                'status' => str_replace('_', ' ', $status),
                'total'  => (int) $total,
            ])
            ->values()
            ->all();
    }
}
