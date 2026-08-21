<?php

namespace App\Livewire\Charges;

use App\Enums\ChargeType;
use App\Enums\ChargeValueType;
use App\Enums\RecordStatus;
use App\Livewire\Forms\ChargeForm;
use App\Livewire\Support\MasterForm;
use App\Models\Charge;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;

/**
 * Create and edit screen for a charge.
 */
class Form extends MasterForm
{
    public ChargeForm $form;

    public ?Charge $charge = null;

    public function mount(?Charge $charge = null): void
    {
        if ($charge?->exists) {
            $this->charge = $charge;
            $this->form->setCharge($charge);
        }
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->charge;
    }

    protected function indexRoute(): string
    {
        return 'charges.index';
    }

    protected function label(): string
    {
        return 'Charge';
    }

    protected function view(): string
    {
        return 'livewire.charges.form';
    }

    /**
     * Option lists, taken from the enums so the form cannot offer a value the
     * domain would reject.
     *
     * @return array<string, array<string, string>>
     */
    public function options(): array
    {
        return [
            'types' => ChargeType::options(),
            'valueTypes' => ChargeValueType::options(),
            'statuses' => RecordStatus::options(),
        ];
    }

    /**
     * What this charge does to a sample PHP 10,000 order, shown live under the
     * value field so the tax/discount direction is unmistakable.
     */
    public function preview(): float
    {
        return $this->form->previewOn(10000);
    }
}
