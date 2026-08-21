@php
    $options = $this->options();
@endphp

<div class="mx-auto max-w-4xl space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $vendor->code : 'Create Vendor'"
                   :subtitle="$this->isEditing()
                        ? $vendor->name
                        : 'The code is assigned automatically when the vendor is saved'">
        <x-slot:actions>
            @if ($this->isEditing())
                <a href="{{ route('vendors.show', $vendor) }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="eye" class="size-4" />
                    View
                </a>
            @endif

            <a href="{{ route('vendors.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        {{-- Basic details --}}
        <x-card title="Basic information">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-form.field label="Vendor name" for="name" :error="$errors->first('form.name')" required>
                        <input id="name"
                               type="text"
                               class="input input-bordered w-full @error('form.name') input-error @enderror"
                               placeholder="e.g. Holcim Philippines Inc"
                               maxlength="255"
                               autofocus
                               wire:model.blur="form.name">
                    </x-form.field>
                </div>

                <x-form.field label="Payment terms" for="payment_terms" :error="$errors->first('form.payment_terms')">
                    <input id="payment_terms"
                           type="text"
                           list="payment-terms-options"
                           class="input input-bordered w-full @error('form.payment_terms') input-error @enderror"
                           placeholder="Net 30"
                           maxlength="255"
                           wire:model.blur="form.payment_terms">

                    <datalist id="payment-terms-options">
                        @foreach ($options['paymentTerms'] as $term)
                            <option value="{{ $term }}"></option>
                        @endforeach
                    </datalist>
                </x-form.field>

                <x-form.field label="Credit limit" for="credit_amount" :error="$errors->first('form.credit_amount')" required
                              hint="Credit the vendor extends to us. Use 0 for cash terms.">
                    <label class="input input-bordered flex w-full items-center gap-2 @error('form.credit_amount') input-error @enderror">
                        <span class="text-base-content/60">{{ $options['currency'] }}</span>
                        <input id="credit_amount"
                               type="number"
                               step="0.01"
                               min="0"
                               class="tabular grow text-right"
                               wire:model.blur="form.credit_amount">
                    </label>
                </x-form.field>

                <x-form.field label="Status" for="status" :error="$errors->first('form.status')" required
                              hint="Only active vendors can be selected on new purchase orders.">
                    <select id="status"
                            class="select select-bordered w-full @error('form.status') select-error @enderror"
                            wire:model="form.status">
                        @foreach ($options['statuses'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-form.field>
            </div>
        </x-card>

        {{-- Address: country → state → city cascade, loaded on demand --}}
        <x-card title="Address">
            <div class="grid gap-4 sm:grid-cols-2"
                 x-data="addressCascade({
                     country: @js($form->country),
                     state: @js($form->state_province),
                     city: @js($form->city),
                     prefix: 'form',
                 })">

                <x-form.field label="Country" for="country" :error="$errors->first('form.country')">
                    <select id="country"
                            class="select select-bordered w-full"
                            x-model="countryCode"
                            x-on:change="onCountry()"
                            :disabled="loading">
                        <option value="">Select country</option>
                        <template x-for="option in countries" :key="option.code">
                            <option :value="option.code" x-text="option.name"></option>
                        </template>
                    </select>
                </x-form.field>

                <x-form.field label="State / province" for="state_province" :error="$errors->first('form.state_province')">
                    <select id="state_province"
                            class="select select-bordered w-full"
                            x-model="stateCode"
                            x-on:change="onState()"
                            :disabled="loading || ! countryCode">
                        <option value="">Select state or province</option>
                        <template x-for="option in states" :key="option.code">
                            <option :value="option.code" x-text="option.name"></option>
                        </template>
                    </select>
                </x-form.field>

                <x-form.field label="City" for="city" :error="$errors->first('form.city')">
                    <select id="city"
                            class="select select-bordered w-full"
                            x-model="city"
                            x-on:change="onCity()"
                            :disabled="loading || loadingCities || ! stateCode">
                        <option value="" x-text="loadingCities ? 'Loading cities…' : 'Select city'"></option>
                        <template x-for="option in cities" :key="option.name">
                            <option :value="option.name" x-text="option.name"></option>
                        </template>
                    </select>
                </x-form.field>

                <x-form.field label="Suburb / barangay" for="suburb_barangay" :error="$errors->first('form.suburb_barangay')">
                    <input id="suburb_barangay"
                           type="text"
                           class="input input-bordered w-full"
                           maxlength="255"
                           wire:model.blur="form.suburb_barangay">
                </x-form.field>

                <x-form.field label="Address line 1" for="address_line_1" :error="$errors->first('form.address_line_1')">
                    <input id="address_line_1"
                           type="text"
                           class="input input-bordered w-full"
                           placeholder="Street and number"
                           maxlength="255"
                           wire:model.blur="form.address_line_1">
                </x-form.field>

                <x-form.field label="Address line 2" for="address_line_2" :error="$errors->first('form.address_line_2')">
                    <input id="address_line_2"
                           type="text"
                           class="input input-bordered w-full"
                           placeholder="Building, floor, unit"
                           maxlength="255"
                           wire:model.blur="form.address_line_2">
                </x-form.field>

                <x-form.field label="Postal code" for="postal_code" :error="$errors->first('form.postal_code')">
                    <input id="postal_code"
                           type="text"
                           class="input input-bordered w-full"
                           maxlength="32"
                           wire:model.blur="form.postal_code">
                </x-form.field>
            </div>
        </x-card>

        {{-- Contact persons repeater --}}
        <x-card title="Contact persons" subtitle="At least one is recommended; blank rows are ignored">
            <x-slot:header>
                <button type="button" class="btn btn-outline btn-sm" wire:click="addContactPerson">
                    <x-icon name="plus" class="size-4" />
                    Add contact
                </button>
            </x-slot:header>

            <div class="space-y-3">
                @foreach ($form->contact_persons as $index => $contact)
                    <div class="grid gap-3 rounded-box border border-base-300 p-3 sm:grid-cols-[1fr_1fr_1fr_auto]"
                         wire:key="contact-{{ $index }}">

                        <x-form.field label="Name" :error="$errors->first('form.contact_persons.' . $index . '.name')">
                            <input type="text"
                                   class="input input-bordered w-full @error('form.contact_persons.' . $index . '.name') input-error @enderror"
                                   placeholder="Juan Dela Cruz"
                                   wire:model.blur="form.contact_persons.{{ $index }}.name">
                        </x-form.field>

                        <x-form.field label="Email" :error="$errors->first('form.contact_persons.' . $index . '.email')">
                            <input type="email"
                                   class="input input-bordered w-full @error('form.contact_persons.' . $index . '.email') input-error @enderror"
                                   placeholder="juan@example.com"
                                   wire:model.blur="form.contact_persons.{{ $index }}.email">
                        </x-form.field>

                        <x-form.field label="Phone" :error="$errors->first('form.contact_persons.' . $index . '.phone')">
                            <input type="tel"
                                   class="input input-bordered w-full @error('form.contact_persons.' . $index . '.phone') input-error @enderror"
                                   placeholder="+63 917 000 0000"
                                   wire:model.blur="form.contact_persons.{{ $index }}.phone">
                        </x-form.field>

                        <div class="flex items-end">
                            <button type="button"
                                    class="btn btn-ghost btn-sm text-error"
                                    wire:click="removeContactPerson({{ $index }})"
                                    title="Remove this contact">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Audit note, edit only --}}
        @if ($this->isEditing())
            <x-card title="Reason for the change"
                    subtitle="Stored on the change log next to what you edited">
                <x-form.field for="update_remarks" :error="$errors->first('form.update_remarks')">
                    <input id="update_remarks"
                           type="text"
                           class="input input-bordered w-full"
                           placeholder="e.g. Credit limit raised after review"
                           maxlength="500"
                           wire:model.blur="form.update_remarks">
                </x-form.field>
            </x-card>
        @endif

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Create Vendor' }}
                </button>

                @unless ($this->isEditing())
                    <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="saveAndAddAnother" wire:loading.attr="disabled">
                        Save and add another
                    </button>
                @endunless

                <a href="{{ route('vendors.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </div>
        </x-card>
    </form>
</div>
