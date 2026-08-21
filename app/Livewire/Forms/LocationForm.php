<?php

namespace App\Livewire\Forms;

use App\Models\Location;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The location form, shared by the create and edit screens.
 *
 * Locations carry a short code (WH-MNL, DC-NTH, …) that appears on every stock
 * movement, so it is stored upper case and kept unique.
 */
class LocationForm extends Form
{
    public ?Location $location = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public function setLocation(Location $location): void
    {
        $this->location = $location;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->description = (string) $location->description;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('locations', 'code')
                    ->ignore($this->location?->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Enter a location code, for example WH-MNL.',
            'code.unique' => 'That location code is already in use.',
            'name.required' => 'Enter the location name.',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['code'] = strtoupper(trim((string) ($attributes['code'] ?? '')));
        $attributes['name'] = trim((string) ($attributes['name'] ?? ''));
        $attributes['description'] = trim((string) ($attributes['description'] ?? ''));

        return $attributes;
    }

    public function save(): Location
    {
        $data = $this->validate();

        $attributes = [
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] === '' ? null : $data['description'],
        ];

        if ($this->location === null) {
            return Location::create($attributes);
        }

        $this->location->update($attributes);

        return $this->location;
    }
}
