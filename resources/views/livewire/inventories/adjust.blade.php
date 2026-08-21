@php
    $action = $this->form->selectedAction();
    $selected = $this->form->selectedInventory();
    $preview = $this->preview();
    $uom = $selected?->material?->uom?->acronym;

    $isInitial = $action === \App\Enums\StockAdjustmentAction::Initial;
    $isAdjust = $action === \App\Enums\StockAdjustmentAction::Adjust;
    $isTransfer = $action === \App\Enums\StockAdjustmentAction::Transfer;
@endphp

<div class="mx-auto max-w-3xl space-y-4">
    <x-page-header title="Manual stock adjustment"
                   subtitle="Open a stock record, correct a balance, or move stock between locations">
        <x-slot:actions>
            <a href="{{ route('inventories.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        {{-- What are we doing --}}
        <x-card title="Action">
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($actions as $option)
                    <label @class([
                        'cursor-pointer rounded-box border p-3 transition',
                        'border-primary bg-primary/5' => $action === $option,
                        'border-base-300 hover:border-base-content/30' => $action !== $option,
                    ])>
                        <div class="flex items-start gap-2">
                            <input type="radio"
                                   class="radio radio-sm mt-0.5"
                                   value="{{ $option->value }}"
                                   wire:model.live="form.action">
                            <div>
                                <p class="font-medium">{{ $option->label() }}</p>
                                <p class="mt-0.5 text-xs text-base-content/60">
                                    @switch($option)
                                        @case(\App\Enums\StockAdjustmentAction::Initial)
                                            Open a record for a material that has none at a location.
                                            @break
                                        @case(\App\Enums\StockAdjustmentAction::Adjust)
                                            Correct a balance after a physical count.
                                            @break
                                        @case(\App\Enums\StockAdjustmentAction::Transfer)
                                            Move stock from one location to another.
                                            @break
                                    @endswitch
                                </p>
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            @error('form.action')
                <p class="mt-2 flex items-center gap-1 text-sm text-error">
                    <x-icon name="exclamation-circle" class="size-4" />
                    {{ $message }}
                </p>
            @enderror
        </x-card>

        @if ($action)
            {{-- Where and what --}}
            <x-card :title="$isTransfer ? 'Source' : 'Stock record'">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.field :label="$isTransfer ? 'From location' : 'Location'"
                                  for="location_id"
                                  :error="$errors->first('form.location_id')"
                                  required>
                        <select id="location_id"
                                class="select select-bordered w-full @error('form.location_id') select-error @enderror"
                                wire:model.live="form.location_id">
                            <option value="">Select location</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    @if ($isInitial)
                        <x-form.field label="Material" for="material_id" :error="$errors->first('form.material_id')" required
                                      hint="Only materials without a record at this location can be opened.">
                            <select id="material_id"
                                    class="select select-bordered w-full @error('form.material_id') select-error @enderror"
                                    wire:model="form.material_id"
                                    @disabled($this->form->location_id === '')>
                                <option value="">Select material</option>
                                @foreach ($materials as $material)
                                    <option value="{{ $material->id }}">{{ $material->code }} — {{ $material->name }}</option>
                                @endforeach
                            </select>
                        </x-form.field>
                    @else
                        <x-form.field label="Stock record" for="inventory_id" :error="$errors->first('form.inventory_id')" required
                                      :hint="$isTransfer ? 'Only records holding stock can be moved.' : null">
                            <select id="inventory_id"
                                    class="select select-bordered w-full @error('form.inventory_id') select-error @enderror"
                                    wire:model.live="form.inventory_id"
                                    @disabled($this->form->location_id === '')>
                                <option value="">
                                    {{ $this->form->location_id === '' ? 'Select a location first' : 'Select stock record' }}
                                </option>
                                @foreach ($records as $record)
                                    <option value="{{ $record->id }}">
                                        {{ $record->material?->code }} — {{ $record->material?->name }}
                                        ({{ number_format((float) $record->quantity, 2) }} {{ $record->material?->uom?->acronym }})
                                    </option>
                                @endforeach
                            </select>
                        </x-form.field>
                    @endif

                    @if ($isTransfer)
                        <x-form.field label="To location" for="transfer_location_id"
                                      :error="$errors->first('form.transfer_location_id')" required>
                            <select id="transfer_location_id"
                                    class="select select-bordered w-full @error('form.transfer_location_id') select-error @enderror"
                                    wire:model="form.transfer_location_id">
                                <option value="">Select destination</option>
                                @foreach ($locations as $location)
                                    @continue((string) $location->id === $this->form->location_id)
                                    <option value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>
                                @endforeach
                            </select>
                        </x-form.field>
                    @endif

                    <x-form.field :label="$isAdjust ? 'Counted quantity' : 'Quantity'"
                                  for="quantity"
                                  :error="$errors->first('form.quantity')"
                                  required
                                  :hint="$isAdjust ? 'Enter what the count found, not the difference.' : null">
                        <label class="input input-bordered flex w-full items-center gap-2 @error('form.quantity') input-error @enderror">
                            <input id="quantity" type="number" step="0.01" min="0"
                                   class="tabular grow text-right"
                                   wire:model.live.debounce.400ms="form.quantity">
                            @if ($uom)
                                <span class="text-base-content/60">{{ $uom }}</span>
                            @endif
                        </label>
                    </x-form.field>

                    <x-form.field label="Transaction date" for="transaction_date"
                                  :error="$errors->first('form.transaction_date')" required>
                        <input id="transaction_date" type="date"
                               class="input input-bordered w-full @error('form.transaction_date') input-error @enderror"
                               wire:model="form.transaction_date">
                    </x-form.field>

                    <div class="sm:col-span-2">
                        <x-form.field label="Remarks" for="remarks" :error="$errors->first('form.remarks')"
                                      hint="Recorded on the movement, and the only explanation anyone will see later.">
                            <input id="remarks" type="text"
                                   class="input input-bordered w-full"
                                   placeholder="e.g. Physical count 30 June, two boxes damaged"
                                   maxlength="2000"
                                   wire:model.blur="form.remarks">
                        </x-form.field>
                    </div>
                </div>
            </x-card>

            {{-- Effect before committing --}}
            @if ($preview)
                <x-card title="Effect">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-box border border-base-300 p-3">
                            <p class="text-sm text-base-content/60">Before</p>
                            <p class="tabular mt-1 text-xl font-semibold">{{ number_format($preview['before'], 2) }}</p>
                        </div>

                        <div class="rounded-box border border-base-300 p-3">
                            <p class="text-sm text-base-content/60">Change</p>
                            <p class="tabular mt-1 text-xl font-semibold {{ $preview['change'] >= 0 ? 'text-success' : 'text-error' }}">
                                {{ $preview['change'] > 0 ? '+' : '' }}{{ number_format($preview['change'], 2) }}
                            </p>
                        </div>

                        <div class="rounded-box border border-base-300 p-3">
                            <p class="text-sm text-base-content/60">After</p>
                            <p class="tabular mt-1 text-xl font-semibold">{{ number_format($preview['after'], 2) }}</p>
                        </div>
                    </div>

                    @if ($isTransfer && $this->form->transfer_location_id !== '')
                        <p class="mt-3 text-sm text-base-content/60">
                            The destination gains {{ number_format(abs($preview['change']), 2) }}
                            {{ $uom }} as a matching transfer-in movement.
                        </p>
                    @endif
                </x-card>
            @endif
        @endif

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" @disabled(! $action)>
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    Record movement
                </button>

                <a href="{{ route('inventories.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </div>
        </x-card>
    </form>
</div>
