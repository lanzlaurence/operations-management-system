<?php

namespace App\Livewire\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Livewire\Support\MasterIndex;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Purchase order list.
 *
 * Deletion is the only write here, and the service refuses it for anything but
 * a draft with no stock booked - posting, cancelling and reverting happen on
 * the document itself, where the consequences are visible.
 */
class Index extends MasterIndex
{
    #[Url(as: 'status', except: '', history: true)]
    public string $statusFilter = '';

    #[Url(as: 'vendor', except: '', history: true)]
    public string $vendorFilter = '';

    #[Url(as: 'from', except: '', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', except: '', history: true)]
    public string $toDate = '';

    protected function model(): string
    {
        return PurchaseOrder::class;
    }

    protected function permissionPrefix(): string
    {
        return 'purchase-order';
    }

    protected function label(): string
    {
        return 'Purchase order';
    }

    protected function title(): string
    {
        return 'Purchase Orders';
    }

    protected function view(): string
    {
        return 'livewire.purchase-orders.index';
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
        return ['vendor:id,code,name', 'user:id,name'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['code', 'reference_no', 'remarks', 'vendor.code', 'vendor.name'];
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
            ->when($this->vendorFilter !== '', fn (Builder $q) => $q->where('vendor_id', $this->vendorFilter))
            ->when($this->fromDate !== '', fn (Builder $q) => $q->whereDate('order_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (Builder $q) => $q->whereDate('order_date', '<=', $this->toDate));
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedVendorFilter(): void
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
            || $this->vendorFilter !== ''
            || $this->fromDate !== ''
            || $this->toDate !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->vendorFilter = '';
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
        $totals = $this->applySearch($this->baseQuery(PurchaseOrder::query()))
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
            'statuses' => ['open' => 'Open (awaiting delivery)'] + PurchaseOrderStatus::options(),
            'vendors' => Vendor::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
