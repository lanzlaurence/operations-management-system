<div class="mx-auto max-w-2xl space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $location->code : 'Create Location'"
                   :subtitle="$this->isEditing() ? 'Update this location' : 'Add a warehouse, store or hub'">
        <x-slot:actions>
            <a href="{{ route('locations.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field label="Code" for="code" :error="$errors->first('form.code')" required
                              hint="Short reference shown on stock movements.">
                    <input id="code"
                           type="text"
                           class="input input-bordered w-full font-mono uppercase @error('form.code') input-error @enderror"
                           placeholder="WH-MNL"
                           maxlength="50"
                           autofocus
                           wire:model.blur="form.code">
                </x-form.field>

                <x-form.field label="Name" for="name" :error="$errors->first('form.name')" required>
                    <input id="name"
                           type="text"
                           class="input input-bordered w-full @error('form.name') input-error @enderror"
                           placeholder="Manila Warehouse"
                           maxlength="255"
                           wire:model.blur="form.name">
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.field label="Description" for="description" :error="$errors->first('form.description')">
                        <textarea id="description"
                                  rows="4"
                                  class="textarea textarea-bordered w-full @error('form.description') textarea-error @enderror"
                                  placeholder="Address or what this location is used for"
                                  wire:model.blur="form.description"></textarea>
                    </x-form.field>
                </div>
            </div>

            <x-slot:footer>
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Create Location' }}
                </button>

                @unless ($this->isEditing())
                    <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="saveAndAddAnother" wire:loading.attr="disabled">
                        Save and add another
                    </button>
                @endunless

                <a href="{{ route('locations.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </x-slot:footer>
        </x-card>
    </form>
</div>
