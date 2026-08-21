<?php

namespace App\Livewire\Forms;

use App\Models\Currency;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The currency form, shared by the create and edit screens.
 *
 * Codes are ISO-style and stored upper case, because the preference that picks
 * the display currency matches on the code.
 */
class CurrencyForm extends Form
{
    public ?Currency $currency = null;

    public string $code = '';

    public string $name = '';

    public string $symbol = '';

    public string $exchange_rate = '1';

    public bool $is_active = true;

    public function setCurrency(Currency $currency): void
    {
        $this->currency = $currency;
        $this->code = $currency->code;
        $this->name = $currency->name;
        $this->symbol = $currency->symbol;
        $this->exchange_rate = (string) $currency->exchange_rate;
        $this->is_active = (bool) $currency->is_active;
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
                'max:10',
                Rule::unique('currencies', 'code')->ignore($this->currency?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001', 'max:99999999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Enter the currency code, e.g. PHP.',
            'code.unique' => 'That currency code already exists.',
            'symbol.required' => 'Enter the symbol shown next to amounts.',
            'exchange_rate.min' => 'The exchange rate must be greater than zero.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'exchange_rate' => 'exchange rate',
            'is_active' => 'active',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['code'] = strtoupper(trim((string) ($attributes['code'] ?? '')));
        $attributes['name'] = trim((string) ($attributes['name'] ?? ''));
        $attributes['symbol'] = trim((string) ($attributes['symbol'] ?? ''));

        return $attributes;
    }

    public function save(): Currency
    {
        $data = $this->validate();

        $attributes = [
            'code' => $data['code'],
            'name' => $data['name'],
            'symbol' => $data['symbol'],
            'exchange_rate' => $data['exchange_rate'],
            'is_active' => (bool) $data['is_active'],
        ];

        if ($this->currency === null) {
            return Currency::create($attributes);
        }

        $this->currency->update($attributes);

        return $this->currency;
    }
}
