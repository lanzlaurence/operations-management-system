<?php

namespace App\Livewire\Forms;

use App\Models\Category;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The category form, shared by the create and edit screens.
 *
 * A Livewire form object is this stack's counterpart to a FormRequest: it owns
 * the fields, the rules, the messages and the write, so create and edit cannot
 * drift apart.
 */
class CategoryForm extends Form
{
    public ?Category $category = null;

    public string $name = '';

    public string $description = '';

    public function setCategory(Category $category): void
    {
        $this->category = $category;
        $this->name = $category->name;
        $this->description = (string) $category->description;
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
                Rule::unique('categorys', 'name')
                    ->ignore($this->category?->id)
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
            'name.required' => 'Enter the category name.',
            'name.unique' => 'That category already exists.',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['name'] = trim((string) ($attributes['name'] ?? ''));
        $attributes['description'] = trim((string) ($attributes['description'] ?? ''));

        return $attributes;
    }

    public function save(): Category
    {
        $data = $this->validate();

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] === '' ? null : $data['description'],
        ];

        if ($this->category === null) {
            return Category::create($attributes);
        }

        $this->category->update($attributes);

        return $this->category;
    }
}
