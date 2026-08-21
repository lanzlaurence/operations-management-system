<?php

namespace App\Livewire\Materials;

use App\Enums\RecordStatus;
use App\Livewire\Support\MasterIndex;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Material;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * Material list.
 *
 * The richest of the master lists: on top of the shared search and sort it
 * filters by brand, category and status, flags what is at or below its reorder
 * level, and shows stock on hand - the one figure a planner always wants next
 * to the costing.
 */
class Index extends MasterIndex
{
    #[Url(as: 'status', except: '', history: true)]
    public string $statusFilter = '';

    #[Url(as: 'brand', except: '', history: true)]
    public string $brandFilter = '';

    #[Url(as: 'category', except: '', history: true)]
    public string $categoryFilter = '';

    #[Url(as: 'reorder', except: false, history: true)]
    public bool $needsReorder = false;

    protected function model(): string
    {
        return Material::class;
    }

    protected function permissionPrefix(): string
    {
        return 'material';
    }

    protected function label(): string
    {
        return 'Material';
    }

    protected function view(): string
    {
        return 'livewire.materials.index';
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
        return ['brand:id,name', 'category:id,name', 'uom:id,acronym'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['code', 'sku', 'name', 'description', 'brand.name', 'category.name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return [
            'code', 'sku', 'name', 'unit_cost', 'unit_price',
            'avg_unit_cost', 'avg_unit_price', 'reorder_level', 'status', 'created_at',
        ];
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
        return $query
            ->when($this->statusFilter !== '', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->brandFilter !== '', fn (Builder $q) => $q->where('brand_id', $this->brandFilter))
            ->when($this->categoryFilter !== '', fn (Builder $q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->needsReorder, fn (Builder $q) => $q->needingReorder());
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedBrandFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedNeedsReorder(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || $this->brandFilter !== ''
            || $this->categoryFilter !== ''
            || $this->needsReorder;
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->brandFilter = '';
        $this->categoryFilter = '';
        $this->needsReorder = false;
        $this->resetPage();
    }

    /**
     * Retiring a material keeps its history and takes it off new documents.
     */
    public function toggleStatus(int $id): void
    {
        $this->authorizePermission('edit');

        $material = $this->findRecord($id);

        if ($material === null) {
            return;
        }

        $status = $material->status === RecordStatus::Active ? RecordStatus::Inactive : RecordStatus::Active;
        $before = $material->only(['status']);

        $material->update(['status' => $status]);
        $material->logUpdated($before, ['status' => $status->value], 'Status changed from the material list');

        $this->toast('success', "{$material->name} is now {$status->label()}.");
    }

    /**
     * Stock on hand for the materials on screen, in one query rather than one
     * per row.
     *
     * @return array<int, float>
     */
    public function stockOnHand(): array
    {
        return Inventory::query()
            ->selectRaw('material_id, COALESCE(SUM(quantity), 0) as total')
            ->groupBy('material_id')
            ->pluck('total', 'material_id')
            ->map(fn (mixed $quantity): float => (float) $quantity)
            ->all();
    }

    /**
     * A material that holds stock or appears on any order is part of the
     * history; retire it with the status instead of deleting it.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        $id = $record->getKey();

        $stock = (float) Inventory::query()->where('material_id', $id)->sum('quantity');

        if ($stock > 0) {
            return "{$record->name} still holds {$stock} in stock and cannot be deleted.";
        }

        $documents = PurchaseOrderItem::query()->where('material_id', $id)->count()
            + SalesOrderItem::query()->where('material_id', $id)->count();

        return $documents === 0
            ? null
            : "{$record->name} appears on {$documents} order line(s). Set it inactive instead of deleting.";
    }

    /**
     * Option lists for the filter selects.
     *
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'statuses' => RecordStatus::options(),
            'brands' => Brand::query()->orderBy('name')->pluck('name', 'id'),
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
