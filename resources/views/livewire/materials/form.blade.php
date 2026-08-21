@php
    $options = $this->options();
    $margin = $this->margin();
@endphp

<div class="mx-auto max-w-4xl space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $material->code : 'Create Material'"
                   :subtitle="$this->isEditing()
                        ? $material->name
                        : 'The code is assigned automatically when the material is saved'">
        <x-slot:actions>
            @if ($this->isEditing())
                <a href="{{ route('materials.show', $material) }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="eye" class="size-4" />
                    View
                </a>
            @endif

            <a href="{{ route('materials.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        {{-- Identity --}}
        <x-card title="Identity">
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($this->isEditing())
                    <x-form.field label="Code" for="code" :error="$errors->first('form.code')" required
                                  hint="Changing this does not affect existing documents.">
                        <input id="code"
                               type="text"
                               class="input input-bordered w-full font-mono @error('form.code') input-error @enderror"
                               maxlength="255"
                               wire:model.blur="form.code">
                    </x-form.field>
                @else
                    <x-form.field label="Code" hint="Assigned automatically, e.g. 300016.">
                        <input type="text" class="input input-bordered w-full font-mono" value="Auto" disabled>
                    </x-form.field>
                @endif

                <x-form.field label="SKU" for="sku" :error="$errors->first('form.sku')"
                              hint="Optional supplier or barcode reference.">
                    <input id="sku"
                           type="text"
                           class="input input-bordered w-full font-mono uppercase @error('form.sku') input-error @enderror"
                           maxlength="255"
                           wire:model.blur="form.sku">
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.field label="Material name" for="name" :error="$errors->first('form.name')" required>
                        <input id="name"
                               type="text"
                               class="input input-bordered w-full @error('form.name') input-error @enderror"
                               placeholder="e.g. Steel Rod 12mm"
                               maxlength="255"
                               autofocus
                               wire:model.blur="form.name">
                    </x-form.field>
                </div>

                <div class="sm:col-span-2">
                    <x-form.field label="Description" for="description" :error="$errors->first('form.description')">
                        <textarea id="description"
                                  rows="3"
                                  class="textarea textarea-bordered w-full @error('form.description') textarea-error @enderror"
                                  placeholder="Specification, grade, or anything a buyer needs to know"
                                  wire:model.blur="form.description"></textarea>
                    </x-form.field>
                </div>
            </div>
        </x-card>

        {{-- Classification --}}
        <x-card title="Classification">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.field label="Category" for="category_id" :error="$errors->first('form.category_id')">
                    <select id="category_id"
                            class="select select-bordered w-full @error('form.category_id') select-error @enderror"
                            wire:model="form.category_id">
                        <option value="">Not set</option>
                        @foreach ($options['categories'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field label="Brand" for="brand_id" :error="$errors->first('form.brand_id')">
                    <select id="brand_id"
                            class="select select-bordered w-full @error('form.brand_id') select-error @enderror"
                            wire:model="form.brand_id">
                        <option value="">Not set</option>
                        @foreach ($options['brands'] as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field label="Unit of measurement" for="uom_id" :error="$errors->first('form.uom_id')">
                    <select id="uom_id"
                            class="select select-bordered w-full @error('form.uom_id') select-error @enderror"
                            wire:model="form.uom_id">
                        <option value="">Not set</option>
                        @foreach ($options['uoms'] as $id => $acronym)
                            <option value="{{ $id }}">{{ $acronym }}</option>
                        @endforeach
                    </select>
                </x-form.field>
            </div>
        </x-card>

        {{-- Pricing --}}
        <x-card title="Pricing"
                subtitle="List values. The weighted averages come from actual receipts and issues and are not editable.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field label="Unit cost" for="unit_cost" :error="$errors->first('form.unit_cost')" required>
                    <label class="input input-bordered flex w-full items-center gap-2 @error('form.unit_cost') input-error @enderror">
                        <span class="text-base-content/60">{{ $options['currency'] }}</span>
                        <input id="unit_cost" type="number" step="0.01" min="0"
                               class="tabular grow text-right"
                               wire:model.live.debounce.400ms="form.unit_cost">
                    </label>
                </x-form.field>

                <x-form.field label="Unit price" for="unit_price" :error="$errors->first('form.unit_price')" required>
                    <label class="input input-bordered flex w-full items-center gap-2 @error('form.unit_price') input-error @enderror">
                        <span class="text-base-content/60">{{ $options['currency'] }}</span>
                        <input id="unit_price" type="number" step="0.01" min="0"
                               class="tabular grow text-right"
                               wire:model.live.debounce.400ms="form.unit_price">
                    </label>
                </x-form.field>

                {{-- Live margin, because a cost above the price is the mistake worth catching here. --}}
                <div class="sm:col-span-2">
                    <div @class([
                        'alert alert-soft',
                        'alert-error' => $margin['amount'] < 0,
                        'alert-info' => $margin['amount'] >= 0,
                    ])>
                        <x-icon :name="$margin['amount'] < 0 ? 'exclamation-triangle' : 'calculator'" class="size-5" />
                        <span>
                            @if ($margin['amount'] < 0)
                                The price is below cost: margin
                                <span class="tabular font-semibold">{{ $options['currency'] }} {{ number_format($margin['amount'], 2) }}</span>.
                            @else
                                Margin
                                <span class="tabular font-semibold">{{ $options['currency'] }} {{ number_format($margin['amount'], 2) }}</span>
                                @if ($margin['percent'] !== null)
                                    ({{ $margin['percent'] }}% of price)
                                @endif
                            @endif
                        </span>
                    </div>
                </div>

                @if ($this->isEditing())
                    <div class="sm:col-span-2 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-box border border-base-300 p-3">
                            <p class="text-sm text-base-content/60">Average purchase cost</p>
                            <p class="tabular mt-1 font-semibold">
                                {{ $options['currency'] }} {{ number_format((float) $material->avg_unit_cost, 2) }}
                            </p>
                        </div>
                        <div class="rounded-box border border-base-300 p-3">
                            <p class="text-sm text-base-content/60">Average selling price</p>
                            <p class="tabular mt-1 font-semibold">
                                {{ $options['currency'] }} {{ number_format((float) $material->avg_unit_price, 2) }}
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- Stock levels --}}
        <x-card title="Stock levels" subtitle="Used by the dashboard and the material list to flag reordering">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.field label="Minimum" for="min_stock_level" :error="$errors->first('form.min_stock_level')" required>
                    <input id="min_stock_level" type="number" min="0" step="1"
                           class="tabular input input-bordered w-full text-right @error('form.min_stock_level') input-error @enderror"
                           wire:model.blur="form.min_stock_level">
                </x-form.field>

                <x-form.field label="Reorder level" for="reorder_level" :error="$errors->first('form.reorder_level')" required
                              hint="0 disables the reorder flag.">
                    <input id="reorder_level" type="number" min="0" step="1"
                           class="tabular input input-bordered w-full text-right @error('form.reorder_level') input-error @enderror"
                           wire:model.blur="form.reorder_level">
                </x-form.field>

                <x-form.field label="Maximum" for="max_stock_level" :error="$errors->first('form.max_stock_level')" required>
                    <input id="max_stock_level" type="number" min="0" step="1"
                           class="tabular input input-bordered w-full text-right @error('form.max_stock_level') input-error @enderror"
                           wire:model.blur="form.max_stock_level">
                </x-form.field>
            </div>
        </x-card>

        {{-- Dimensions --}}
        <x-card title="Dimensions and weight" subtitle="Optional, used for logistics and storage planning">
            <div class="grid gap-4 sm:grid-cols-5">
                @foreach ([
                    'weight' => 'Weight',
                    'length' => 'Length',
                    'width' => 'Width',
                    'height' => 'Height',
                    'volume' => 'Volume',
                ] as $field => $label)
                    <x-form.field :label="$label" :for="$field" :error="$errors->first('form.' . $field)">
                        <input id="{{ $field }}" type="number" step="0.01" min="0"
                               class="tabular input input-bordered w-full text-right @error('form.' . $field) input-error @enderror"
                               wire:model.blur="form.{{ $field }}">
                    </x-form.field>
                @endforeach
            </div>
        </x-card>

        {{-- Tracking and status --}}
        <x-card title="Tracking and status">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" class="toggle toggle-sm" wire:model="form.track_serial_number">
                        <span>
                            Track serial numbers
                            <span class="block text-xs text-base-content/60">Captured on receipts and issues</span>
                        </span>
                    </label>

                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" class="toggle toggle-sm" wire:model="form.track_batch_number">
                        <span>
                            Track batch numbers
                            <span class="block text-xs text-base-content/60">For lot or expiry controlled stock</span>
                        </span>
                    </label>
                </div>

                <x-form.field label="Status" for="status" :error="$errors->first('form.status')" required
                              hint="Only active materials can be added to new orders.">
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

        {{-- Audit note, edit only --}}
        @if ($this->isEditing())
            <x-card title="Reason for the change" subtitle="Stored on the change log next to what you edited">
                <x-form.field for="update_remarks" :error="$errors->first('form.update_remarks')">
                    <input id="update_remarks"
                           type="text"
                           class="input input-bordered w-full"
                           placeholder="e.g. Cost updated after supplier price increase"
                           maxlength="500"
                           wire:model.blur="form.update_remarks">
                </x-form.field>
            </x-card>
        @endif

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Create Material' }}
                </button>

                @unless ($this->isEditing())
                    <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="saveAndAddAnother" wire:loading.attr="disabled">
                        Save and add another
                    </button>
                @endunless

                <a href="{{ route('materials.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </div>
        </x-card>
    </form>
</div>
