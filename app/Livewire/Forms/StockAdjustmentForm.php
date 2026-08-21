<?php

namespace App\Livewire\Forms;

use App\Enums\InventoryMovementType;
use App\Enums\StockAdjustmentAction;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The manual stock adjustment form.
 *
 * Three operations share one screen, and which fields matter depends on the
 * one chosen:
 *
 *   initial   - open a stock record for a material at a location
 *   adjust    - correct the quantity of an existing record
 *   transfer  - move stock from one location to another
 *
 * The rules here are the user-facing half; the movement itself goes through
 * InventoryService, which locks the row, refuses to go negative and writes the
 * matching ledger entry.
 */
class StockAdjustmentForm extends Form
{
    public string $action = '';

    public string $transaction_date = '';

    public string $location_id = '';

    public string $material_id = '';

    public string $inventory_id = '';

    public string $quantity = '';

    public string $transfer_location_id = '';

    public string $remarks = '';

    public function mountForm(): void
    {
        $this->transaction_date = today()->toDateString();
    }

    public function selectedAction(): ?StockAdjustmentAction
    {
        return StockAdjustmentAction::parse($this->action);
    }

    /**
     * Changing the action invalidates the fields that belonged to the previous
     * one, so the form never submits a stale combination.
     */
    public function resetForAction(): void
    {
        $this->location_id = '';
        $this->material_id = '';
        $this->inventory_id = '';
        $this->quantity = '';
        $this->transfer_location_id = '';
    }

    /**
     * The stock record being corrected or moved.
     */
    public function selectedInventory(): ?Inventory
    {
        return $this->inventory_id === ''
            ? null
            : Inventory::with(['material:id,code,name,uom_id', 'material.uom:id,acronym', 'location:id,code,name'])
                ->find($this->inventory_id);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $action = $this->selectedAction();

        return [
            'action' => ['required', StockAdjustmentAction::rule()],
            'transaction_date' => ['required', 'date'],

            'location_id' => ['required', 'integer', 'exists:locations,id'],

            'material_id' => [
                Rule::requiredIf($action === StockAdjustmentAction::Initial),
                'nullable',
                'integer',
                Rule::exists('materials', 'id')->where('status', 'active'),
                // One stock record per material per location.
                Rule::when($action === StockAdjustmentAction::Initial, [
                    Rule::unique('inventories', 'material_id')
                        ->where('location_id', $this->location_id === '' ? null : $this->location_id)
                        ->whereNull('deleted_at'),
                ]),
            ],

            'inventory_id' => [
                Rule::requiredIf(in_array($action, [
                    StockAdjustmentAction::Adjust,
                    StockAdjustmentAction::Transfer,
                ], true)),
                'nullable',
                'integer',
                'exists:inventories,id',
            ],

            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999', $this->quantityRule()],

            'transfer_location_id' => [
                Rule::requiredIf($action === StockAdjustmentAction::Transfer),
                'nullable',
                'integer',
                'exists:locations,id',
                'different:location_id',
            ],

            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Choose what you want to do.',
            'location_id.required' => 'Select a location.',
            'material_id.required' => 'Select the material to open stock for.',
            'material_id.unique' => 'This material already has a stock record at that location. Use Adjust instead.',
            'material_id.exists' => 'Select an active material.',
            'inventory_id.required' => 'Select the stock record to work on.',
            'transfer_location_id.required' => 'Select where the stock is moving to.',
            'transfer_location_id.different' => 'The destination must differ from the source location.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'location_id' => 'location',
            'material_id' => 'material',
            'inventory_id' => 'stock record',
            'transfer_location_id' => 'destination location',
            'transaction_date' => 'transaction date',
        ];
    }

    /**
     * Quantity rules that depend on the action and on the current balance.
     */
    private function quantityRule(): \Closure
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            $action = $this->selectedAction();
            $quantity = Money::quantity($value);

            if ($action !== StockAdjustmentAction::Adjust && $quantity <= 0) {
                $fail('Enter a quantity greater than zero.');

                return;
            }

            $inventory = $this->selectedInventory();

            if ($inventory === null) {
                return;
            }

            $onHand = Money::quantity($inventory->quantity);

            if ($action === StockAdjustmentAction::Adjust && Money::quantityEquals($onHand, $quantity)) {
                $fail('That is the quantity already on record. Enter a different one.');
            }

            if ($action === StockAdjustmentAction::Transfer && $quantity > $onHand) {
                $fail('Only '.($onHand + 0).' is available at the source location.');
            }
        };
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['remarks'] = trim((string) ($attributes['remarks'] ?? ''));

        foreach (['location_id', 'material_id', 'inventory_id', 'transfer_location_id'] as $field) {
            if (($attributes[$field] ?? '') === '') {
                $attributes[$field] = null;
            }
        }

        return $attributes;
    }

    /**
     * Run the movement and return the resulting ledger entry, or the pair of
     * entries a transfer produces.
     *
     * @return array<int, InventoryLog>
     */
    public function submit(InventoryService $inventory): array
    {
        $data = $this->validate();

        $action = StockAdjustmentAction::from($data['action']);
        $quantity = Money::quantity($data['quantity']);
        $remarks = $data['remarks'] === '' ? null : $data['remarks'];

        return match ($action) {
            StockAdjustmentAction::Initial => [
                $inventory->increase(
                    materialId: (int) $data['material_id'],
                    locationId: (int) $data['location_id'],
                    quantity: $quantity,
                    type: InventoryMovementType::Initial,
                    remarks: $remarks ?? 'Opening stock',
                ),
            ],
            StockAdjustmentAction::Adjust => [
                $inventory->adjustTo(
                    inventory: Inventory::findOrFail($data['inventory_id']),
                    quantity: $quantity,
                    remarks: $remarks,
                ),
            ],
            StockAdjustmentAction::Transfer => $inventory->transfer(
                inventory: Inventory::findOrFail($data['inventory_id']),
                toLocationId: (int) $data['transfer_location_id'],
                quantity: $quantity,
                remarks: $remarks,
            ),
        };
    }
}
