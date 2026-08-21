<?php

namespace App\Livewire\Vendors;

use App\Enums\RecordStatus;
use App\Livewire\Support\MasterIndex;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Vendor list.
 *
 * Beyond the shared search and sort, vendors can be filtered by status,
 * because an inactive vendor is kept for history but must not be picked on
 * new purchase orders.
 */
class Index extends MasterIndex
{
    #[Url(as: 'status', except: '', history: true)]
    public string $statusFilter = '';

    protected function model(): string
    {
        return Vendor::class;
    }

    protected function permissionPrefix(): string
    {
        return 'vendor';
    }

    protected function label(): string
    {
        return 'Vendor';
    }

    protected function view(): string
    {
        return 'livewire.vendors.index';
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
     * Deactivating is how a vendor is retired: their orders stay intact and
     * they stop appearing on new ones.
     */
    public function toggleStatus(int $id): void
    {
        $this->authorizePermission('edit');

        $vendor = $this->findRecord($id);

        if ($vendor === null) {
            return;
        }

        $status = $vendor->status === RecordStatus::Active ? RecordStatus::Inactive : RecordStatus::Active;
        $before = $vendor->only(['status']);

        $vendor->update(['status' => $status]);
        $vendor->logUpdated($before, ['status' => $status->value], 'Status changed from the vendor list');

        $this->toast('success', "{$vendor->name} is now {$status->label()}.");
    }

    /**
     * A vendor with purchase orders is part of the trading history; retire them
     * with the status instead of deleting them.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        $orders = PurchaseOrder::query()->where('vendor_id', $record->getKey())->count();

        return $orders === 0
            ? null
            : "{$record->name} has {$orders} purchase order(s). Set the vendor inactive instead of deleting.";
    }

    /**
     * Sales order counts for the rows on screen, in one query.
     *
     * @return array<int, int>
     */
    public function orderCounts(): array
    {
        return PurchaseOrder::query()
            ->selectRaw('vendor_id, COUNT(*) as total')
            ->groupBy('vendor_id')
            ->pluck('total', 'vendor_id')
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
