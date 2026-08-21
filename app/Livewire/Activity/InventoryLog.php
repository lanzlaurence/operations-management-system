<?php

namespace App\Livewire\Activity;

use App\Enums\InventoryMovementType;
use App\Livewire\Concerns\WithDataTable;
use App\Models\InventoryLog as InventoryLogModel;
use App\Models\Location;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The stock movement ledger across every material and location.
 *
 * Every quantity change in the system lands here, so this is the screen that
 * answers "why is the balance what it is". Append-only and read-only, filtered
 * in SQL, and totalled so the in/out volume of a period is visible without
 * exporting it.
 */
#[Layout('components.layouts.app')]
#[Title('Inventory Log')]
class InventoryLog extends Component
{
    use WithDataTable;

    #[Url(as: 'type', except: '', history: true)]
    public string $typeFilter = '';

    #[Url(as: 'module', except: '', history: true)]
    public string $moduleFilter = '';

    #[Url(as: 'location', except: '', history: true)]
    public string $locationFilter = '';

    #[Url(as: 'from', except: '', history: true)]
    public string $fromDate = '';

    #[Url(as: 'to', except: '', history: true)]
    public string $toDate = '';

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['movement_code', 'remarks', 'material.code', 'material.name', 'inventory.code'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['created_at', 'movement_code', 'type', 'quantity_change', 'quantity_after'];
    }

    protected function defaultSortField(): string
    {
        return 'created_at';
    }

    protected function defaultSortDirection(): string
    {
        return 'desc';
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedModuleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
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
            || $this->typeFilter !== ''
            || $this->moduleFilter !== ''
            || $this->locationFilter !== ''
            || $this->fromDate !== ''
            || $this->toDate !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->moduleFilter = '';
        $this->locationFilter = '';
        $this->fromDate = '';
        $this->toDate = '';
        $this->resetPage();
    }

    /**
     * Movement volume for everything the filters match.
     *
     * @return array<string, float|int>
     */
    public function totals(): array
    {
        $totals = $this->applySearch($this->filteredQuery())
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(*) as movements')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END), 0) as inbound')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END), 0) as outbound')
            ->first();

        $inbound = Money::quantity($totals->inbound ?? 0);
        $outbound = Money::quantity($totals->outbound ?? 0);

        return [
            'movements' => (int) ($totals->movements ?? 0),
            'inbound' => $inbound,
            'outbound' => $outbound,
            'net' => Money::quantity($inbound - $outbound),
        ];
    }

    public function render(): View
    {
        return view('livewire.activity.inventory-log', [
            'records' => $this->rows(),
            'typeOptions' => InventoryMovementType::options(),
            'moduleOptions' => $this->moduleOptions(),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, InventoryLogModel>
     */
    private function rows(): LengthAwarePaginator
    {
        return $this->applyDataTable($this->filteredQuery())
            ->with([
                'material:id,code,name,uom_id',
                'material.uom:id,acronym',
                'location:id,code,name',
                'transferLocation:id,code,name',
                'user:id,name',
                'inventory:id,code',
            ])
            ->paginate($this->perPage);
    }

    /**
     * @return Builder<InventoryLogModel>
     */
    private function filteredQuery(): Builder
    {
        return InventoryLogModel::query()
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->when($this->moduleFilter !== '', fn (Builder $query) => $query->whereIn('type', $this->typesForModule()))
            ->when($this->locationFilter !== '', fn (Builder $query) => $query->where('location_id', $this->locationFilter))
            ->betweenDates($this->fromDate ?: null, $this->toDate ?: null);
    }

    /**
     * Movement types grouped by the module that produces them, so the ledger
     * can be read one workflow at a time.
     *
     * @return array<string, string>
     */
    private function moduleOptions(): array
    {
        return collect(InventoryMovementType::cases())
            ->groupBy(fn (InventoryMovementType $type): string => $type->module())
            ->keys()
            ->mapWithKeys(fn (string $module): array => [$module => $module])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function typesForModule(): array
    {
        return collect(InventoryMovementType::cases())
            ->filter(fn (InventoryMovementType $type): bool => $type->module() === $this->moduleFilter)
            ->map(fn (InventoryMovementType $type): string => $type->value)
            ->values()
            ->all();
    }
}
