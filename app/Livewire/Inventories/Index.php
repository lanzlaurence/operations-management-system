<?php

namespace App\Livewire\Inventories;

use App\Livewire\Support\MasterIndex;
use App\Models\Inventory;
use App\Models\Location;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * Stock balances, one row per material and location.
 *
 * Read-only by design: quantities move through goods receipts, goods issues and
 * the manual adjustment screen, never by editing a row here. The only write is
 * removing an emptied record.
 */
class Index extends MasterIndex
{
    #[Url(as: 'location', except: '', history: true)]
    public string $locationFilter = '';

    #[Url(as: 'stock', except: '', history: true)]
    public string $stockFilter = '';

    protected function model(): string
    {
        return Inventory::class;
    }

    protected function permissionPrefix(): string
    {
        return 'inventory';
    }

    protected function label(): string
    {
        return 'Stock record';
    }

    protected function title(): string
    {
        return 'Inventory';
    }

    protected function view(): string
    {
        return 'livewire.inventories.index';
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
        return ['material:id,code,name,uom_id,avg_unit_cost,reorder_level', 'material.uom:id,acronym', 'location:id,code,name'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['code', 'material.code', 'material.name', 'location.code', 'location.name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'quantity', 'updated_at'];
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
            ->when($this->locationFilter !== '', fn (Builder $q) => $q->where('location_id', $this->locationFilter))
            ->when($this->stockFilter === 'in', fn (Builder $q) => $q->where('quantity', '>', 0))
            ->when($this->stockFilter === 'out', fn (Builder $q) => $q->where('quantity', '<=', 0))
            ->when($this->stockFilter === 'reorder', fn (Builder $q) => $q->whereHas(
                'material',
                fn (Builder $material) => $material
                    ->where('reorder_level', '>', 0)
                    ->whereColumn('materials.reorder_level', '>=', 'inventories.quantity'),
            ));
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStockFilter(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->locationFilter !== '' || $this->stockFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->locationFilter = '';
        $this->stockFilter = '';
        $this->resetPage();
    }

    /**
     * Totals across everything the current filters match, not just this page.
     *
     * @return array<string, float|int>
     */
    public function totals(): array
    {
        $totals = $this->applySearch($this->baseQuery(Inventory::query()))
            ->join('materials', 'materials.id', '=', 'inventories.material_id')
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(*) as records')
            ->selectRaw('COALESCE(SUM(inventories.quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(inventories.quantity * materials.avg_unit_cost), 0) as value')
            ->first();

        return [
            'records' => (int) ($totals->records ?? 0),
            'quantity' => Money::quantity($totals->quantity ?? 0),
            'value' => Money::round($totals->value ?? 0),
        ];
    }

    /**
     * When each record last moved, so the list can show it without a query per
     * row.
     *
     * @return array<int, string>
     */
    public function lastMovedAt(): array
    {
        return DB::table('inventory_logs')
            ->selectRaw('inventory_id, MAX(created_at) as moved_at')
            ->groupBy('inventory_id')
            ->pluck('moved_at', 'inventory_id')
            ->all();
    }

    /**
     * A record still holding stock is a balance, not a mistake: empty it first.
     */
    protected function deleteBlockedReason(Model $record): ?string
    {
        return Money::quantity($record->quantity) > 0
            ? "{$record->code} still holds {$record->quantity} in stock. Issue or transfer it out first."
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function filterOptions(): array
    {
        return [
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id'),
            'stock' => [
                'in' => 'In stock',
                'out' => 'Out of stock',
                'reorder' => 'At reorder level',
            ],
        ];
    }
}
