<?php

namespace App\Livewire\Inventories;

use App\Enums\StockAdjustmentAction;
use App\Livewire\Forms\StockAdjustmentForm;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Material;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Manual stock adjustment: opening stock, corrections and transfers.
 *
 * This is the only screen that writes a quantity without a document behind it,
 * so it shows the effect before committing - the record's current balance, and
 * what it will read afterwards.
 */
#[Layout('components.layouts.app')]
#[Title('Manual Stock Adjustment')]
class Adjust extends Component
{
    public StockAdjustmentForm $form;

    public function mount(): void
    {
        $this->form->mountForm();
    }

    /**
     * Switching the action clears the fields that belonged to the previous one.
     */
    public function updatedFormAction(): void
    {
        $this->form->resetForAction();
        $this->resetValidation();
    }

    /**
     * Changing the location changes which stock records are selectable.
     */
    public function updatedFormLocationId(): void
    {
        $this->form->inventory_id = '';
        $this->form->material_id = '';
        $this->form->quantity = '';
    }

    /**
     * Picking a record pre-fills the quantity with its current balance, so a
     * correction starts from what is on record rather than from blank.
     */
    public function updatedFormInventoryId(): void
    {
        $inventory = $this->form->selectedInventory();

        if ($inventory !== null && $this->form->selectedAction() === StockAdjustmentAction::Adjust) {
            $this->form->quantity = (string) Money::quantity($inventory->quantity);
        }
    }

    public function save(): void
    {
        $logs = $this->form->submit(app(InventoryService::class));

        $first = $logs[0] ?? null;

        session()->flash('success', sprintf(
            '%s recorded: %s is now %s.',
            $this->form->selectedAction()?->label() ?? 'Adjustment',
            $first?->inventory?->code ?? 'the stock record',
            $first === null ? '' : Money::quantity($first->quantity_after) + 0,
        ));

        $this->redirectRoute('inventories.index', navigate: true);
    }

    /**
     * Stock records selectable for the chosen location.
     *
     * @return Collection<int, Inventory>
     */
    public function selectableRecords(): Collection
    {
        if ($this->form->location_id === '') {
            return collect();
        }

        return Inventory::query()
            ->with(['material:id,code,name,uom_id', 'material.uom:id,acronym'])
            ->where('location_id', $this->form->location_id)
            ->when(
                $this->form->selectedAction() === StockAdjustmentAction::Transfer,
                fn ($query) => $query->where('quantity', '>', 0),
            )
            ->get()
            ->sortBy(fn (Inventory $inventory): string => (string) $inventory->material?->code)
            ->values();
    }

    /**
     * What the balance will read once this movement is applied.
     *
     * @return array<string, float>|null
     */
    public function preview(): ?array
    {
        $action = $this->form->selectedAction();
        $quantity = Money::quantity($this->form->quantity);

        if ($action === null || $this->form->quantity === '') {
            return null;
        }

        if ($action === StockAdjustmentAction::Initial) {
            return ['before' => 0.0, 'change' => $quantity, 'after' => $quantity];
        }

        $inventory = $this->form->selectedInventory();

        if ($inventory === null) {
            return null;
        }

        $before = Money::quantity($inventory->quantity);

        return match ($action) {
            StockAdjustmentAction::Adjust => [
                'before' => $before,
                'change' => Money::quantity($quantity - $before),
                'after' => $quantity,
            ],
            StockAdjustmentAction::Transfer => [
                'before' => $before,
                'change' => Money::quantity(-$quantity),
                'after' => Money::quantity($before - $quantity),
            ],
            default => null,
        };
    }

    public function render(): View
    {
        return view('livewire.inventories.adjust', [
            'actions' => StockAdjustmentAction::cases(),
            'locations' => Location::query()->orderBy('name')->get(['id', 'code', 'name']),
            'materials' => $this->form->selectedAction() === StockAdjustmentAction::Initial
                ? Material::query()->active()->orderBy('code')->get(['id', 'code', 'name'])
                : collect(),
            'records' => $this->selectableRecords(),
        ]);
    }
}
