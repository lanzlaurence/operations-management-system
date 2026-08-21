<?php

namespace App\Livewire\Forms;

use App\Models\Uom;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The unit-of-measurement form, shared by the create and edit screens.
 *
 * A Livewire form object is this stack's counterpart to a FormRequest: it owns
 * the fields, the rules, the messages and the write. The component only decides
 * when to call it, which is what keeps create and edit from drifting apart.
 */
class UomForm extends Form
{
    public ?Uom $uom = null;

    public string $acronym = '';

    public string $description = '';

    /**
     * Load an existing record for editing.
     */
    public function setUom(Uom $uom): void
    {
        $this->uom = $uom;
        $this->acronym = $uom->acronym;
        $this->description = (string) $uom->description;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'acronym' => [
                'required',
                'string',
                'max:50',
                Rule::unique('uoms', 'acronym')
                    ->ignore($this->uom?->id)
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
            'acronym.required' => 'Enter an acronym, for example KG, PC or BOX.',
            'acronym.unique' => 'That acronym is already in use.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'acronym' => 'acronym',
            'description' => 'description',
        ];
    }

    /**
     * Acronyms are stored upper case and trimmed, whichever screen typed them.
     */
    protected function prepareForValidation($attributes)
    {
        $attributes['acronym'] = strtoupper(trim((string) ($attributes['acronym'] ?? '')));
        $attributes['description'] = trim((string) ($attributes['description'] ?? ''));

        return $attributes;
    }

    /**
     * Create or update, returning the saved record.
     */
    public function save(): Uom
    {
        $data = $this->validate();

        $attributes = [
            'acronym' => $data['acronym'],
            'description' => $data['description'] === '' ? null : $data['description'],
        ];

        if ($this->uom === null) {
            return Uom::create($attributes);
        }

        $this->uom->update($attributes);

        return $this->uom;
    }
}
