<div class="mx-auto max-w-2xl space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $category->name : 'Create Category'"
                   :subtitle="$this->isEditing() ? 'Update this category' : 'Add a material grouping'">
        <x-slot:actions>
            <a href="{{ route('categories.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-card>
            <div class="space-y-4">
                <x-form.field label="Name" for="name" :error="$errors->first('form.name')" required>
                    <input id="name"
                           type="text"
                           class="input input-bordered w-full @error('form.name') input-error @enderror"
                           placeholder="e.g. Cement"
                           maxlength="255"
                           autofocus
                           wire:model.blur="form.name">
                </x-form.field>

                <x-form.field label="Description" for="description" :error="$errors->first('form.description')">
                    <textarea id="description"
                              rows="4"
                              class="textarea textarea-bordered w-full @error('form.description') textarea-error @enderror"
                              placeholder="What this category covers"
                              wire:model.blur="form.description"></textarea>
                </x-form.field>
            </div>

            <x-slot:footer>
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Create Category' }}
                </button>

                @unless ($this->isEditing())
                    <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="saveAndAddAnother" wire:loading.attr="disabled">
                        Save and add another
                    </button>
                @endunless

                <a href="{{ route('categories.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </x-slot:footer>
        </x-card>
    </form>
</div>
