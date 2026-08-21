<?php

namespace App\Livewire\Brands;

use App\Livewire\Forms\BrandForm;
use App\Livewire\Support\MasterForm;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;

/**
 * Create and edit screen for a brand.
 */
class Form extends MasterForm
{
    public BrandForm $form;

    public ?Brand $brand = null;

    public function mount(?Brand $brand = null): void
    {
        if ($brand?->exists) {
            $this->brand = $brand;
            $this->form->setBrand($brand);
        }
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->brand;
    }

    protected function indexRoute(): string
    {
        return 'brands.index';
    }

    protected function label(): string
    {
        return 'Brand';
    }

    protected function view(): string
    {
        return 'livewire.brands.form';
    }
}
