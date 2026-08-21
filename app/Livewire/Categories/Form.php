<?php

namespace App\Livewire\Categories;

use App\Livewire\Forms\CategoryForm;
use App\Livewire\Support\MasterForm;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;

/**
 * Create and edit screen for a category.
 */
class Form extends MasterForm
{
    public CategoryForm $form;

    public ?Category $category = null;

    public function mount(?Category $category = null): void
    {
        if ($category?->exists) {
            $this->category = $category;
            $this->form->setCategory($category);
        }
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->category;
    }

    protected function indexRoute(): string
    {
        return 'categories.index';
    }

    protected function label(): string
    {
        return 'Category';
    }

    protected function view(): string
    {
        return 'livewire.categories.form';
    }
}
