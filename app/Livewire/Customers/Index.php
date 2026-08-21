<?php

namespace App\Livewire\Customers;

use App\Enums\RecordStatus;
use App\Livewire\Support\MasterIndex;
use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Customer list.
 *
 * Beyond the shared search and sort, customers can be filtered by status,
 * because an inactive customer is kept for history but must not be picked on
 * new sales orders.
 */
class Index extends MasterIndex
{
    #[Url(as: 'status', except: '', history: true)]
    public string $statusFilter = '';

    protected function model(): string
    {
        return Customer::class;
    }

    protected function permissionPrefix(): string
    {
        return 'customer';
    }

    protected function label(): string
    {
        return 'Customer';
    }

    protected function view(): string
    {
        return 'livewire.customers.index';
    }

    protected function displayColumn(): string
    {
        return 'code';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['code', 'name', 'city', 'state_province', 'payment_terms'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'name', 'city', 'payment_terms', 'credit_amount', 'status', 'created_at'];
    }

    protected function defaultSortField(): string
    {
        return 'code';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function baseQuery(Builder $query): Builder
    {
        return $query->when(
            $this->statusFilter !== '',
            fn (Builder $q) => $q->where('status', $this->statusFilter),
        );
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->statusFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    /**
     * Deactivating is how a customer is retired: their orders stay intact and
     * they stop appearing on new ones.
     */
    public function toggleStatus(int $id): void
    {
        $this->authorizePermission('edit');

        $customer = $this->findRecord($id);

        if ($customer === null) {
            return;
        }

        $status = $customer->status === RecordStatus::Active ? RecordStatus::Inactive : RecordStatus::Active;
        $before = $customer->only(['status']);

        $customer->update(['status' => $status]);
        $customer->logUpdated($before, ['status' => $status->value], 'Status changed from the customer list');

        $this->toast('success', "{$customer->name} is now {$status->label()}.");
    }

    /**
     * A customer with sales orders is part of the trading history; retire them
     * with the status instead of deleting them.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        $orders = SalesOrder::query()->where('customer_id', $record->getKey())->count();

        return $orders === 0
            ? null
            : "{$record->name} has {$orders} sales order(s). Set the customer inactive instead of deleting.";
    }

    /**
     * Sales order counts for the rows on screen, in one query.
     *
     * @return array<int, int>
     */
    public function orderCounts(): array
    {
        return SalesOrder::query()
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id')
            ->all();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function filterOptions(): array
    {
        return [
            'statuses' => RecordStatus::options(),
        ];
    }
}
