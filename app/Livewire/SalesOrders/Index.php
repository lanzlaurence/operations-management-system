<?php

namespace App\Livewire\SalesOrders;

use App\Enums\SalesOrderStatus;
use App\Livewire\Support\MasterIndex;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Sales order list.
 *
 * Deletion is the only write here, and the service refuses it for anything but
 * a draft with no stock booked - posting, cancelling and reverting happen on
 * the document itself, where the consequences are visible.
 */
class Index extends MasterIndex
{
    #[Url(as: 'status', except: '', history: true)]
    public string $statusFilter = '';

    #[Url(as: 'customer', except: '', history: true)]
    public string $customerFilter = '';

    #[Url(as: 'from', except: '', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', except: '', history: true)]
    public string $toDate = '';

    protected function model(): string
    {
        return SalesOrder::class;
    }

    protected function permissionPrefix(): string
    {
        return 'purchase-order';
    }

    protected function label(): string
    {
        return 'Sales order';
    }

    protected function title(): string
    {
        return 'Sales Orders';
    }

    protected function view(): string
    {
        return 'livewire.sales-orders.index';
    }

    protected function displayColumn(): string
    {
        return 'code';
    }

    /**
     * @return array<int, string>
     */
    protected function withRelations(): array
    {
        return ['customer:id,code,name', 'user:id,name'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['code', 'reference_no', 'remarks', 'customer.code', 'customer.name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'order_date', 'delivery_date', 'status', 'grand_total'];
    }

    protected function defaultSortField(): string
    {
        return 'order_date';
    }

    protected function defaultSortDirection(): string
    {
        return 'desc';
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function baseQuery(Builder $query): Builder
    {
        return $query
            ->when($this->statusFilter === 'open', fn (Builder $q) => $q->open())
            ->when(
                $this->statusFilter !== '' && $this->statusFilter !== 'open',
                fn (Builder $q) => $q->where('status', $this->statusFilter),
            )
            ->when($this->customerFilter !== '', fn (Builder $q) => $q->where('customer_id', $this->customerFilter))
            ->when($this->fromDate !== '', fn (Builder $q) => $q->whereDate('order_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (Builder $q) => $q->whereDate('order_date', '<=', $this->toDate));
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCustomerFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || $this->customerFilter !== ''
            || $this->fromDate !== ''
            || $this->toDate !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->customerFilter = '';
        $this->fromDate = '';
        $this->toDate = '';
        $this->resetPage();
    }

    /**
     * Value of everything the filters match, so a filtered view answers "how
     * much is this" without exporting.
     *
     * @return array<string, float|int>
     */
    public function totals(): array
    {
        $totals = $this->applySearch($this->baseQuery(SalesOrder::query()))
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as value')
            ->first();

        return [
            'orders' => (int) ($totals->orders ?? 0),
            'value' => Money::round($totals->value ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'statuses' => ['open' => 'Open (awaiting delivery)'] + SalesOrderStatus::options(),
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
