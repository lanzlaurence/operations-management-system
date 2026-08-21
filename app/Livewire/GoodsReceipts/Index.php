<?php

namespace App\Livewire\GoodsReceipts;

use App\Enums\GoodsReceiptStatus;
use App\Livewire\Support\MasterIndex;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Location;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Goods receipt list.
 *
 * A receipt is a plan while pending and a fact once completed, so the status
 * filter is the useful one here: pending receipts are what the warehouse still
 * has to check in.
 */
class Index extends MasterIndex
{
    #[Url(as: 'status', except: '', history: true)]
    public string $statusFilter = '';

    #[Url(as: 'location', except: '', history: true)]
    public string $locationFilter = '';

    protected function model(): string
    {
        return GoodsReceipt::class;
    }

    protected function permissionPrefix(): string
    {
        return 'goods-receipt';
    }

    protected function label(): string
    {
        return 'Goods receipt';
    }

    protected function title(): string
    {
        return 'Goods Receipts';
    }

    protected function view(): string
    {
        return 'livewire.goods-receipts.index';
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
        return ['purchaseOrder:id,code,vendor_id', 'purchaseOrder.vendor:id,code,name', 'location:id,code,name', 'user:id,name'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['code', 'remarks', 'purchaseOrder.code', 'purchaseOrder.vendor.name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'gr_date', 'transaction_date', 'status'];
    }

    protected function defaultSortField(): string
    {
        return 'gr_date';
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
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->locationFilter !== '', fn (Builder $q) => $q->where('location_id', $this->locationFilter));
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->statusFilter !== '' || $this->locationFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->locationFilter = '';
        $this->resetPage();
    }

    /**
     * Quantity per receipt for the rows on screen, in one query.
     *
     * @return array<int, float>
     */
    public function quantities(): array
    {
        return GoodsReceiptItem::query()
            ->selectRaw('goods_receipt_id, COALESCE(SUM(qty_to_receive), 0) as total')
            ->groupBy('goods_receipt_id')
            ->pluck('total', 'goods_receipt_id')
            ->map(fn (mixed $value): float => Money::quantity($value))
            ->all();
    }

    /**
     * How many receipts are still waiting to be checked in.
     */
    public function pendingCount(): int
    {
        return GoodsReceipt::query()->pending()->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'statuses' => GoodsReceiptStatus::options(),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
