<?php

namespace App\Livewire\Locations;

use App\Livewire\Forms\LocationForm;
use App\Livewire\Support\MasterForm;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;

/**
 * Create and edit screen for a location.
 */
class Form extends MasterForm
{
    public LocationForm $form;

    public ?Location $location = null;

    public function mount(?Location $location = null): void
    {
        if ($location?->exists) {
            $this->location = $location;
            $this->form->setLocation($location);
        }
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->location;
    }

    protected function indexRoute(): string
    {
        return 'locations.index';
    }

    protected function label(): string
    {
        return 'Location';
    }

    protected function view(): string
    {
        return 'livewire.locations.form';
    }
}
