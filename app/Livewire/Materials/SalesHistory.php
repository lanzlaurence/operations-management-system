<?php

namespace App\Livewire\Materials;

use App\Livewire\Support\MaterialHistoryScreen;
use App\Models\SalesOrderItem;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every sales order line for one material: who bought it, when, at what unit
 * price after discount, and how much was ordered.
 */
class SalesHistory extends MaterialHistoryScreen
{
    protected function view(): string
    {
        return 'livewire.materials.sales-history';
    }

    protected function heading(): string
    {
        return 'Sales history';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['salesOrder.code', 'salesOrder.reference_no', 'salesOrder.customer.name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['order_date', 'qty_ordered', 'unit_price_after_discount', 'net_price'];
    }

    /**
     * @return Builder<SalesOrderItem>
     */
    protected function historyQuery(): Builder
    {
        return SalesOrderItem::query()
            ->with(['salesOrder:id,code,reference_no,status,order_date,customer_id', 'salesOrder.customer:id,code,name'])
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->where('sales_order_items.material_id', $this->material->id)
            ->whereNull('sales_orders.deleted_at')
            ->select('sales_order_items.*', 'sales_orders.order_date as order_date');
    }

    /**
     * @return array<string, float>
     */
    public function totals(): array
    {
        $totals = $this->aggregateQuery()
            ->selectRaw('COALESCE(SUM(sales_order_items.qty_ordered), 0) as qty')
            ->selectRaw('COALESCE(SUM(sales_order_items.qty_shipped), 0) as shipped')
            ->selectRaw('COALESCE(SUM(sales_order_items.net_price), 0) as net')
            ->first();

        $quantity = (float) ($totals->qty ?? 0);
        $net = (float) ($totals->net ?? 0);

        return [
            'qty_ordered' => Money::quantity($quantity),
            'qty_shipped' => Money::quantity((float) ($totals->shipped ?? 0)),
            'net_revenue' => Money::round($net),
            'avg_unit_price' => $quantity > 0 ? Money::round($net / $quantity) : 0.0,
        ];
    }
}
