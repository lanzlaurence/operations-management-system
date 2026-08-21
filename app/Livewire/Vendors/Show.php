<?php

namespace App\Livewire\Vendors;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vendor detail screen: the record, its contact persons, its purchasing
 * summary and its change log.
 *
 * The summary answers what the list cannot: how much has been ordered from this
 * vendor, how much is still awaiting delivery, and which materials they are
 * actually supplying.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Vendor $vendor;

    public function mount(Vendor $vendor): void
    {
        $this->vendor = $vendor;
    }

    /**
     * Order counts and values per status, plus what is still outstanding.
     *
     * @return array<string, float|int>
     */
    public function summary(): array
    {
        // Aggregated on the query builder rather than the model: an Eloquent
        // row would cast `status` to the enum, and the comparisons below work
        // on the raw values the status lists hold.
        $orders = DB::table('purchase_orders')
            ->where('vendor_id', $this->vendor->id)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as total_count, COALESCE(SUM(grand_total), 0) as total_value')
            ->groupBy('status')
            ->get();

        $liveValue = $orders
            ->filter(fn (object $row): bool => in_array($row->status, PurchaseOrderStatus::liveValues(), true))
            ->sum('total_value');

        $openValue = $orders
            ->filter(fn (object $row): bool => in_array($row->status, PurchaseOrderStatus::openValues(), true))
            ->sum('total_value');

        $credit = (float) $this->vendor->credit_amount;

        return [
            'orders' => (int) $orders->sum('total_count'),
            'live_value' => Money::round($liveValue),
            'open_value' => Money::round($openValue),
            'credit_limit' => Money::round($credit),
            'credit_used_percent' => $credit > 0 ? min(100, round(($openValue / $credit) * 100)) : 0,
        ];
    }

    /**
     * The vendor's recent purchase orders.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function recentOrders(): Collection
    {
        return PurchaseOrder::query()
            ->where('vendor_id', $this->vendor->id)
            ->latest('order_date')
            ->limit(10)
            ->get(['id', 'code', 'status', 'order_date', 'delivery_date', 'grand_total']);
    }

    /**
     * Materials bought from this vendor, with the quantity ordered and the last
     * unit cost agreed - the figures a buyer wants before raising the next order.
     *
     * @return Collection<int, object>
     */
    public function suppliedMaterials(): Collection
    {
        return PurchaseOrder::query()
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->join('materials', 'materials.id', '=', 'purchase_order_items.material_id')
            ->where('purchase_orders.vendor_id', $this->vendor->id)
            ->whereNull('purchase_orders.deleted_at')
            ->whereIn('purchase_orders.status', PurchaseOrderStatus::liveValues())
            ->groupBy('materials.id', 'materials.code', 'materials.name')
            ->selectRaw('materials.id, materials.code, materials.name')
            ->selectRaw('SUM(purchase_order_items.qty_ordered) as qty_ordered')
            ->selectRaw('MAX(purchase_orders.order_date) as last_ordered_at')
            ->selectRaw('AVG(purchase_order_items.unit_cost_after_discount) as avg_unit_cost')
            ->orderByDesc('qty_ordered')
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        $this->vendor->load(['logs' => fn ($query) => $query->with('user:id,name')->latest('id')]);

        return view('livewire.vendors.show')
            ->title("{$this->vendor->code} — {$this->vendor->name}");
    }
}
