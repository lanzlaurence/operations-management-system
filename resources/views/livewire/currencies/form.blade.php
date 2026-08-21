<div class="mx-auto max-w-2xl space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $currency->code : 'Create Currency'"
                   :subtitle="$this->isEditing() ? $currency->name : 'Add a currency the application can display amounts in'">
        <x-slot:actions>
            <a href="{{ route('currencies.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($this->isInUse())
        <div class="alert alert-info alert-soft">
            <x-icon name="information-circle" class="size-5" />
            <span>This is the display currency, so it cannot be made unavailable here.</span>
        </div>
    @endif

    <form wire:submit="save">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field label="Code" for="code" :error="$errors->first('form.code')" required
                              hint="Stored upper case, e.g. PHP or USD.">
                    <input id="code" type="text"
                           class="input input-bordered w-full font-mono uppercase @error('form.code') input-error @enderror"
                           maxlength="10" autofocus
                           wire:model.blur="form.code">
                </x-form.field>

                <x-form.field label="Symbol" for="symbol" :error="$errors->first('form.symbol')" required
                              hint="Shown next to every amount.">
                    <input id="symbol" type="text"
                           class="input input-bordered w-full @error('form.symbol') input-error @enderror"
                           maxlength="10"
                           wire:model.blur="form.symbol">
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.field label="Name" for="name" :error="$errors->first('form.name')" required>
                        <input id="name" type="text"
                               class="input input-bordered w-full @error('form.name') input-error @enderror"
                               placeholder="e.g. Philippine Peso"
                               maxlength="255"
                               wire:model.blur="form.name">
                    </x-form.field>
                </div>

                <x-form.field label="Exchange rate" for="exchange_rate" :error="$errors->first('form.exchange_rate')" required
                              hint="Reference only; documents are recorded in the display currency.">
                    <input id="exchange_rate" type="number" step="0.000001" min="0.000001"
                           class="tabular input input-bordered w-full text-right @error('form.exchange_rate') input-error @enderror"
                           wire:model.blur="form.exchange_rate">
                </x-form.field>

                <x-form.field label="Availability">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" class="toggle toggle-sm"
                               wire:model="form.is_active"
                               @disabled($this->isInUse())>
                        <span>
                            Available in Preferences
                            <span class="block text-xs text-base-content/60">
                                Unavailable currencies stay on record but cannot be selected.
                            </span>
                        </span>
                    </label>
                </x-form.field>
            </div>

            <x-slot:footer>
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Create Currency' }}
                </button>

                @unless ($this->isEditing())
                    <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="saveAndAddAnother" wire:loading.attr="disabled">
                        Save and add another
                    </button>
                @endunless

                <a href="{{ route('currencies.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </x-slot:footer>
        </x-card>
    </form>
</div>
