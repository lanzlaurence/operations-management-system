<div class="mx-auto max-w-2xl space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $uom->acronym : 'Create UOM'"
                   :subtitle="$this->isEditing()
                        ? 'Update this unit of measurement'
                        : 'Add a unit materials can be counted or weighed in'">
        <x-slot:actions>
            <a href="{{ route('uoms.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-card>
            <div class="space-y-4">
                <x-form.field label="Acronym" for="acronym" :error="$errors->first('form.acronym')" required
                              hint="Stored in upper case, for example KG, PC or BOX.">
                    <input id="acronym"
                           type="text"
                           class="input input-bordered w-full uppercase @error('form.acronym') input-error @enderror"
                           placeholder="KG"
                           maxlength="50"
                           autofocus
                           wire:model.blur="form.acronym">
                </x-form.field>

                <x-form.field label="Description" for="description" :error="$errors->first('form.description')">
                    <textarea id="description"
                              rows="4"
                              class="textarea textarea-bordered w-full @error('form.description') textarea-error @enderror"
                              placeholder="Kilogram"
                              wire:model.blur="form.description"></textarea>
                </x-form.field>
            </div>

            <x-slot:footer>
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Create UOM' }}
                </button>

                @unless ($this->isEditing())
                    <button type="button"
                            class="btn btn-ghost btn-sm"
                            wire:click="saveAndAddAnother"
                            wire:loading.attr="disabled">
                        Save and add another
                    </button>
                @endunless

                <a href="{{ route('uoms.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </x-slot:footer>
        </x-card>
    </form>
</div>
