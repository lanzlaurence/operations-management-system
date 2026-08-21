<?php

namespace App\Livewire\GoodsIssues;

use App\Enums\GoodsIssueStatus;
use App\Livewire\Support\MasterIndex;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Location;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Goods issue list.
 *
 * A issue is a plan while pending and a fact once completed, so the status
 * filter is the useful one here: pending issues are what the warehouse still
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
        return GoodsIssue::class;
    }

    protected function permissionPrefix(): string
    {
        return 'goods-issue';
    }

    protected function label(): string
    {
        return 'Goods issue';
    }

    protected function title(): string
    {
        return 'Goods Issues';
    }

    protected function view(): string
    {
        return 'livewire.goods-issues.index';
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
        return ['salesOrder:id,code,customer_id', 'salesOrder.customer:id,code,name', 'location:id,code,name', 'user:id,name'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['code', 'remarks', 'salesOrder.code', 'salesOrder.customer.name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'gi_date', 'transaction_date', 'status'];
    }

    protected function defaultSortField(): string
    {
        return 'gi_date';
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
     * Quantity per issue for the rows on screen, in one query.
     *
     * @return array<int, float>
     */
    public function quantities(): array
    {
        return GoodsIssueItem::query()
            ->selectRaw('goods_issue_id, COALESCE(SUM(qty_to_ship), 0) as total')
            ->groupBy('goods_issue_id')
            ->pluck('total', 'goods_issue_id')
            ->map(fn (mixed $value): float => Money::quantity($value))
            ->all();
    }

    /**
     * How many issues are still waiting to be checked in.
     */
    public function pendingCount(): int
    {
        return GoodsIssue::query()->pending()->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'statuses' => GoodsIssueStatus::options(),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
