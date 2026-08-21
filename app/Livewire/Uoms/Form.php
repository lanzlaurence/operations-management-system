<?php

namespace App\Livewire\Uoms;

use App\Livewire\Forms\UomForm;
use App\Livewire\Support\MasterForm;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;

/**
 * Create and edit screen for a unit of measurement.
 */
class Form extends MasterForm
{
    public UomForm $form;

    public ?Uom $uom = null;

    public function mount(?Uom $uom = null): void
    {
        if ($uom?->exists) {
            $this->uom = $uom;
            $this->form->setUom($uom);
        }
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->uom;
    }

    protected function indexRoute(): string
    {
        return 'uoms.index';
    }

    protected function label(): string
    {
        return 'Unit of Measurement';
    }

    protected function view(): string
    {
        return 'livewire.uoms.form';
    }

    protected function recordName(?Model $record): string
    {
        return (string) ($record?->acronym ?? '');
    }
}
