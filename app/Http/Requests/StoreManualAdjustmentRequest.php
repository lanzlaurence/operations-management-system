<?php

namespace App\Http\Requests;

use App\Data\StockAdjustmentData;
use App\Enums\RecordStatus;
use App\Enums\StockAdjustmentAction;
use App\Models\Inventory;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the manual inventory adjustment screen.
 *
 * Which fields are required depends on the action:
 *
 *  - initial:  material + location, must not already exist
 *  - adjust:   an existing inventory row and a different quantity
 *  - transfer: an existing row, a target location and enough stock to move
 */
class StoreManualAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjust', Inventory::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('remarks') === '') {
            $this->merge(['remarks' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $action = $this->action();

        return [
            'action' => ['required', StockAdjustmentAction::rule()],
            'transaction_date' => ['required', 'date'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'material_id' => [
                Rule::requiredIf($action === StockAdjustmentAction::Initial),
                'nullable',
                'integer',
                Rule::exists('materials', 'id')->where('status', RecordStatus::Active->value),
                Rule::when($action === StockAdjustmentAction::Initial, [
                    Rule::unique('inventories')
                        ->where('location_id', $this->input('location_id'))
                        ->whereNull('deleted_at'),
                ]),
            ],
            'inventory_id' => [
                Rule::requiredIf(in_array($action, [StockAdjustmentAction::Adjust, StockAdjustmentAction::Transfer], true)),
                'nullable',
                'integer',
                'exists:inventories,id',
            ],
            'quantity' => ['required', 'numeric', 'min:0', 'max:99999999'],
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
            'material_id.unique' => 'This material already exists in the selected location.',
            'material_id.exists' => 'Select an active material.',
            'transfer_location_id.different' => 'Transfer location must be different from the source location.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'material_id' => 'material',
            'location_id' => 'location',
            'inventory_id' => 'stock record',
            'transfer_location_id' => 'transfer location',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateQuantity($validator),
        ];
    }

    /**
     * Quantity rules that depend on the current stock balance.
     */
    protected function validateQuantity(Validator $validator): void
    {
        $action = $this->action();
        $quantity = Money::quantity($this->input('quantity', 0));

        if ($action === StockAdjustmentAction::Initial && $quantity <= 0) {
            $validator->errors()->add('quantity', 'Initial quantity must be greater than zero.');

            return;
        }

        if ($action === StockAdjustmentAction::Transfer && $quantity <= 0) {
            $validator->errors()->add('quantity', 'Transfer quantity must be greater than zero.');

            return;
        }

        $inventory = Inventory::find($this->input('inventory_id'));

        if ($inventory === null) {
            return;
        }

        if ($action === StockAdjustmentAction::Adjust && Money::quantityEquals($inventory->quantity, $quantity)) {
            $validator->errors()->add('quantity', 'No changes detected. Please enter a different quantity.');
        }

        if ($action === StockAdjustmentAction::Transfer && $quantity > Money::quantity($inventory->quantity)) {
            $validator->errors()->add(
                'quantity',
                'Transfer quantity cannot exceed the available stock of '.(Money::quantity($inventory->quantity) + 0).'.',
            );
        }
    }

    public function toData(): StockAdjustmentData
    {
        return StockAdjustmentData::fromRequest($this);
    }

    private function action(): ?StockAdjustmentAction
    {
        return StockAdjustmentAction::parse($this->input('action'));
    }
}
