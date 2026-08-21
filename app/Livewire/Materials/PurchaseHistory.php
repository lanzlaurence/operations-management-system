<?php

namespace App\Livewire\Materials;

use App\Livewire\Support\MaterialHistoryScreen;
use App\Models\PurchaseOrderItem;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every purchase order line for one material: who supplied it, when, at what
 * unit cost after discount, and how much was ordered.
 */
class PurchaseHistory extends MaterialHistoryScreen
{
    protected function view(): string
    {
        return 'livewire.materials.purchase-history';
    }

    protected function heading(): string
    {
        return 'Purchase history';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['purchaseOrder.code', 'purchaseOrder.reference_no', 'purchaseOrder.vendor.name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['order_date', 'qty_ordered', 'unit_cost_after_discount', 'net_price'];
    }

    /**
     * Lines joined to their order so the vendor and the order date can be
     * sorted on in SQL.
     *
     * @return Builder<PurchaseOrderItem>
     */
    protected function historyQuery(): Builder
    {
        return PurchaseOrderItem::query()
            ->with(['purchaseOrder:id,code,reference_no,status,order_date,vendor_id', 'purchaseOrder.vendor:id,code,name'])
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_order_items.material_id', $this->material->id)
            ->whereNull('purchase_orders.deleted_at')
            ->select('purchase_order_items.*', 'purchase_orders.order_date as order_date');
    }

    /**
     * @return array<string, float>
     */
    public function totals(): array
    {
        $totals = $this->aggregateQuery()
            ->selectRaw('COALESCE(SUM(purchase_order_items.qty_ordered), 0) as qty')
            ->selectRaw('COALESCE(SUM(purchase_order_items.qty_received), 0) as received')
            ->selectRaw('COALESCE(SUM(purchase_order_items.net_price), 0) as net')
            ->first();

        $quantity = (float) ($totals->qty ?? 0);
        $net = (float) ($totals->net ?? 0);

        return [
            'qty_ordered' => Money::quantity($quantity),
            'qty_received' => Money::quantity((float) ($totals->received ?? 0)),
            'net_cost' => Money::round($net),
            'avg_unit_cost' => $quantity > 0 ? Money::round($net / $quantity) : 0.0,
        ];
    }
}
