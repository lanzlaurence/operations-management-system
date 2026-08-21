@php
    $options = $this->options();
    $currency = \App\Models\Preference::get('currency', 'PHP');
    $isPercentage = $form->value_type === \App\Enums\ChargeValueType::Percentage->value;
@endphp

<div class="mx-auto max-w-2xl space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $charge->name : 'Create Charge'"
                   :subtitle="$this->isEditing()
                        ? 'Update this charge'
                        : 'Add a tax, fee or discount that orders can apply'">
        <x-slot:actions>
            <a href="{{ route('charges.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-form.field label="Name" for="name" :error="$errors->first('form.name')" required>
                        <input id="name"
                               type="text"
                               class="input input-bordered w-full @error('form.name') input-error @enderror"
                               placeholder="e.g. Delivery Charge"
                               maxlength="255"
                               autofocus
                               wire:model.blur="form.name">
                    </x-form.field>
                </div>

                <x-form.field label="Type" for="type" :error="$errors->first('form.type')" required
                              hint="Tax adds to the total, discount subtracts from it.">
                    <select id="type"
                            class="select select-bordered w-full @error('form.type') select-error @enderror"
                            wire:model.live="form.type">
                        @foreach ($options['types'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field label="Value type" for="value_type" :error="$errors->first('form.value_type')" required>
                    <select id="value_type"
                            class="select select-bordered w-full @error('form.value_type') select-error @enderror"
                            wire:model.live="form.value_type">
                        @foreach ($options['valueTypes'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field label="Value" for="value" :error="$errors->first('form.value')" required>
                    <label class="input input-bordered flex w-full items-center gap-2 @error('form.value') input-error @enderror">
                        @unless ($isPercentage)
                            <span class="text-base-content/60">{{ $currency }}</span>
                        @endunless

                        <input id="value"
                               type="number"
                               step="0.01"
                               min="0"
                               max="{{ $isPercentage ? 100 : 999999999.99 }}"
                               class="tabular grow text-right"
                               wire:model.live.debounce.400ms="form.value">

                        @if ($isPercentage)
                            <span class="text-base-content/60">%</span>
                        @endif
                    </label>
                </x-form.field>

                <x-form.field label="Status" for="status" :error="$errors->first('form.status')" required
                              hint="Only active charges can be added to new orders.">
                    <select id="status"
                            class="select select-bordered w-full @error('form.status') select-error @enderror"
                            wire:model="form.status">
                        @foreach ($options['statuses'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.field label="Description" for="description" :error="$errors->first('form.description')">
                        <textarea id="description"
                                  rows="3"
                                  class="textarea textarea-bordered w-full @error('form.description') textarea-error @enderror"
                                  placeholder="When this charge applies"
                                  wire:model.blur="form.description"></textarea>
                    </x-form.field>
                </div>

                {{-- Live effect on a sample order, so tax vs discount is unmistakable. --}}
                <div class="sm:col-span-2">
                    <div class="alert alert-info alert-soft">
                        <x-icon name="calculator" class="size-5" />
                        <span>
                            On a {{ $currency }} 10,000.00 order this charge gives a total of
                            <span class="tabular font-semibold">{{ $currency }} {{ number_format($this->preview(), 2) }}</span>.
                        </span>
                    </div>
                </div>
            </div>

            <x-slot:footer>
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Create Charge' }}
                </button>

                @unless ($this->isEditing())
                    <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="saveAndAddAnother" wire:loading.attr="disabled">
                        Save and add another
                    </button>
                @endunless

                <a href="{{ route('charges.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </x-slot:footer>
        </x-card>
    </form>
</div>
