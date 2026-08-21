<?php

namespace App\Livewire\Inventories;

use App\Enums\InventoryMovementType;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One stock balance and the ledger behind it.
 *
 * The ledger is the audit trail for a quantity, so it paginates and filters in
 * SQL rather than shipping every movement to the browser, and the screen
 * reconciles the balance against the sum of its movements - if those two ever
 * disagree, something wrote a quantity outside InventoryService.
 */
#[Layout('components.layouts.app')]
class Show extends Component
{
    use WithDataTable;

    public Inventory $inventory;

    #[Url(as: 'type', except: '', history: true)]
    public string $typeFilter = '';

    public function mount(Inventory $inventory): void
    {
        $this->inventory = $inventory;
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['movement_code', 'remarks'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['created_at', 'movement_code', 'quantity_change', 'quantity_after'];
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

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->typeFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->resetPage();
    }

    /**
     * Balance, value, and the reconciliation against the ledger.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $onHand = Money::quantity($this->inventory->quantity);

        $ledger = Money::quantity(
            InventoryLog::query()->where('inventory_id', $this->inventory->id)->sum('quantity_change')
        );

        $material = $this->inventory->material;
        $reorder = (int) ($material->reorder_level ?? 0);

        return [
            'on_hand' => $onHand,
            'value' => Money::round($onHand * (float) ($material->avg_unit_cost ?? 0)),
            'ledger_total' => $ledger,
            'reconciled' => Money::quantityEquals($onHand, $ledger),
            'movements' => InventoryLog::query()->where('inventory_id', $this->inventory->id)->count(),
            'needs_reorder' => $reorder > 0 && $onHand <= $reorder,
            'reorder_level' => $reorder,
        ];
    }

    /**
     * Quantity in and out over the whole ledger, which is what tells you
     * whether this location is a staging point or a real stock holding.
     *
     * @return array<string, float>
     */
    public function flow(): array
    {
        $flow = InventoryLog::query()
            ->where('inventory_id', $this->inventory->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END), 0) as inbound')
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END), 0) as outbound')
            ->first();

        return [
            'inbound' => Money::quantity($flow->inbound ?? 0),
            'outbound' => Money::quantity($flow->outbound ?? 0),
        ];
    }

    public function render(): View
    {
        $this->inventory->load(['material.uom', 'material.brand:id,name', 'material.category:id,name', 'location']);

        return view('livewire.inventories.show', [
            'records' => $this->movements(),
            'typeOptions' => InventoryMovementType::options(),
        ])->title("{$this->inventory->code} — {$this->inventory->material?->name}");
    }

    /**
     * @return LengthAwarePaginator<int, InventoryLog>
     */
    private function movements(): LengthAwarePaginator
    {
        $query = InventoryLog::query()
            ->with(['user:id,name', 'transferLocation:id,code,name'])
            ->where('inventory_id', $this->inventory->id)
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('type', $this->typeFilter));

        return $this->applyDataTable($query)->paginate($this->perPage);
    }
}
