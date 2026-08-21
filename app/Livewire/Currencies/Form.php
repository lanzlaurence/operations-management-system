<?php

namespace App\Livewire\Currencies;

use App\Livewire\Forms\CurrencyForm;
use App\Livewire\Support\MasterForm;
use App\Models\Currency;
use App\Models\Preference;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;

/**
 * Create and edit screen for a currency.
 */
class Form extends MasterForm
{
    public CurrencyForm $form;

    public ?Currency $currency = null;

    public function mount(?Currency $currency = null): void
    {
        if ($currency?->exists) {
            $this->currency = $currency;
            $this->form->setCurrency($currency);
        }
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->currency;
    }

    protected function indexRoute(): string
    {
        return 'currencies.index';
    }

    protected function label(): string
    {
        return 'Currency';
    }

    protected function view(): string
    {
        return 'livewire.currencies.form';
    }

    /**
     * Whether this is the currency preferences currently display amounts in.
     */
    public function isInUse(): bool
    {
        return $this->currency !== null
            && $this->currency->code === (string) Preference::get('currency', 'PHP');
    }
}
