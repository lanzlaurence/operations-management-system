<?php

namespace App\Livewire\Charges;

use App\Enums\ChargeType;
use App\Enums\RecordStatus;
use App\Livewire\Support\MasterIndex;
use App\Models\Charge;
use App\Models\PurchaseOrderCharge;
use App\Models\SalesOrderCharge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Charge list.
 *
 * Charges drive the tax and discount lines on orders, so the list can be
 * filtered by type and status on top of the shared search.
 */
class Index extends MasterIndex
{
    #[Url(as: 'type', except: '', history: true)]
    public string $typeFilter = '';

    #[Url(as: 'status', except: '', history: true)]
    public string $statusFilter = '';

    protected function model(): string
    {
        return Charge::class;
    }

    protected function permissionPrefix(): string
    {
        return 'charge';
    }

    protected function label(): string
    {
        return 'Charge';
    }

    protected function view(): string
    {
        return 'livewire.charges.index';
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'description'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'type', 'value_type', 'value', 'status', 'created_at', 'updated_at'];
    }

    protected function defaultSortField(): string
    {
        return 'name';
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
        return $query
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter));
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->typeFilter !== '' || $this->statusFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    /**
     * Toggling a charge inactive is the usual way to retire one: documents keep
     * their snapshot, and it stops being offered on new orders.
     */
    public function toggleStatus(int $id): void
    {
        $this->authorizePermission('edit');

        $charge = $this->findRecord($id);

        if ($charge === null) {
            return;
        }

        $status = $charge->status === RecordStatus::Active ? RecordStatus::Inactive : RecordStatus::Active;

        $charge->update(['status' => $status]);

        $this->toast('success', "{$charge->name} is now {$status->label()}.");
    }

    /**
     * Charges already snapshotted onto an order are history; retire them with
     * the status instead of deleting them.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        $documents = PurchaseOrderCharge::query()->where('charge_id', $record->getKey())->count()
            + SalesOrderCharge::query()->where('charge_id', $record->getKey())->count();

        return $documents === 0
            ? null
            : "{$record->name} is applied to {$documents} order(s). Set it inactive instead of deleting it.";
    }

    /**
     * Option lists for the filter selects.
     *
     * @return array<string, array<string, string>>
     */
    public function filterOptions(): array
    {
        return [
            'types' => ChargeType::options(),
            'statuses' => RecordStatus::options(),
        ];
    }
}
