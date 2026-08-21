<?php

namespace App\Livewire\Forms;

use App\Enums\ChargeType;
use App\Enums\ChargeValueType;
use App\Enums\RecordStatus;
use App\Models\Charge;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The charge form, shared by the create and edit screens.
 *
 * The three option lists come straight from the enums, so the form can never
 * offer a value the domain does not accept. A percentage charge is additionally
 * capped at 100.
 */
class ChargeForm extends Form
{
    public ?Charge $charge = null;

    public string $name = '';

    public string $description = '';

    public string $type = ChargeType::Tax->value;

    public string $value_type = ChargeValueType::Fixed->value;

    public string $value = '0';

    public string $status = RecordStatus::Active->value;

    public function setCharge(Charge $charge): void
    {
        $this->charge = $charge;
        $this->name = $charge->name;
        $this->description = (string) $charge->description;
        $this->type = $charge->type->value;
        $this->value_type = $charge->value_type->value;
        $this->value = (string) Money::round($charge->value);
        $this->status = $charge->status->value;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('charges', 'name')
                    ->ignore($this->charge?->id)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', ChargeType::rule()],
            'value_type' => ['required', ChargeValueType::rule()],
            'value' => [
                'required',
                'numeric',
                'min:0',
                $this->value_type === ChargeValueType::Percentage->value ? 'max:100' : 'max:999999999.99',
            ],
            'status' => ['required', RecordStatus::rule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the charge name.',
            'name.unique' => 'A charge with that name already exists.',
            'type.required' => 'Choose whether this adds to (tax) or subtracts from (discount) the total.',
            'value_type.required' => 'Choose a fixed amount or a percentage.',
            'value.max' => 'A percentage charge cannot exceed 100.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'value_type' => 'value type',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['name'] = trim((string) ($attributes['name'] ?? ''));
        $attributes['description'] = trim((string) ($attributes['description'] ?? ''));

        return $attributes;
    }

    public function save(): Charge
    {
        $data = $this->validate();

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] === '' ? null : $data['description'],
            'type' => $data['type'],
            'value_type' => $data['value_type'],
            'value' => Money::round($data['value']),
            'status' => $data['status'],
        ];

        if ($this->charge === null) {
            return Charge::create($attributes);
        }

        $this->charge->update($attributes);

        return $this->charge;
    }

    /**
     * Preview of what this charge does to a sample amount, shown on the form so
     * the effect of tax vs discount and fixed vs percentage is obvious.
     */
    public function previewOn(float $base = 10000): float
    {
        $valueType = ChargeValueType::parse($this->value_type, ChargeValueType::Fixed);
        $type = ChargeType::parse($this->type, ChargeType::Tax);

        $amount = $valueType->computeOn($base, (float) $this->value);

        return Money::round($base + ($type->sign() * $amount));
    }
}
