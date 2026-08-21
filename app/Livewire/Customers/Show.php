<?php

namespace App\Livewire\Customers;

use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Customer detail screen: the record, its contact persons, its trading summary
 * and its change log.
 *
 * The summary is what the list cannot show - how much the customer has ordered
 * and how much of their credit limit is committed to open orders.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    public Customer $customer;

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * Order totals per status, plus the value still open.
     *
     * @return array<string, float|int>
     */
    public function summary(): array
    {
        // Aggregated on the query builder rather than the model: an Eloquent
        // row would cast `status` to the enum, and the comparisons below work
        // on the raw values the status lists hold.
        $orders = DB::table('sales_orders')
            ->where('customer_id', $this->customer->id)
            ->whereNull('deleted_at')
            ->selectRaw('status, COUNT(*) as total_count, COALESCE(SUM(grand_total), 0) as total_value')
            ->groupBy('status')
            ->get();

        $liveValue = $orders
            ->filter(fn (object $row): bool => in_array($row->status, SalesOrderStatus::liveValues(), true))
            ->sum('total_value');

        $openValue = $orders
            ->filter(fn (object $row): bool => in_array($row->status, SalesOrderStatus::openValues(), true))
            ->sum('total_value');

        $credit = (float) $this->customer->credit_amount;

        return [
            'orders' => (int) $orders->sum('total_count'),
            'live_value' => Money::round($liveValue),
            'open_value' => Money::round($openValue),
            'credit_limit' => Money::round($credit),
            'credit_used_percent' => $credit > 0 ? min(100, round(($openValue / $credit) * 100)) : 0,
        ];
    }

    /**
     * The customer's recent orders.
     *
     * @return Collection<int, SalesOrder>
     */
    public function recentOrders(): Collection
    {
        return SalesOrder::query()
            ->where('customer_id', $this->customer->id)
            ->latest('order_date')
            ->limit(10)
            ->get(['id', 'code', 'status', 'order_date', 'delivery_date', 'grand_total']);
    }

    public function render(): View
    {
        $this->customer->load(['logs' => fn ($query) => $query->with('user:id,name')->latest('id')]);

        return view('livewire.customers.show')
            ->title("{$this->customer->code} — {$this->customer->name}");
    }
}
