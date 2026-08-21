<?php

namespace App\Livewire\Forms;

use App\Models\Brand;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The brand form, shared by the create and edit screens.
 *
 * A Livewire form object is this stack's counterpart to a FormRequest: it owns
 * the fields, the rules, the messages and the write, so create and edit cannot
 * drift apart.
 */
class BrandForm extends Form
{
    public ?Brand $brand = null;

    public string $name = '';

    public string $description = '';

    public function setBrand(Brand $brand): void
    {
        $this->brand = $brand;
        $this->name = $brand->name;
        $this->description = (string) $brand->description;
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
                Rule::unique('brands', 'name')
                    ->ignore($this->brand?->id)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the brand name.',
            'name.unique' => 'That brand already exists.',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['name'] = trim((string) ($attributes['name'] ?? ''));
        $attributes['description'] = trim((string) ($attributes['description'] ?? ''));

        return $attributes;
    }

    public function save(): Brand
    {
        $data = $this->validate();

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] === '' ? null : $data['description'],
        ];

        if ($this->brand === null) {
            return Brand::create($attributes);
        }

        $this->brand->update($attributes);

        return $this->brand;
    }
}
