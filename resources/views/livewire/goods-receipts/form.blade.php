<div class="space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $goodsReceipt->code : 'Receive goods'"
                   :subtitle="$purchaseOrder->code . ' · ' . $purchaseOrder->vendor?->name">
        <x-slot:actions>
            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="shopping-cart" class="size-4" />
                Order
            </a>

            <a href="{{ route('goods-receipts.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info alert-soft">
        <x-icon name="information-circle" class="size-5" />
        <span>
            Preparing a receipt does not move stock — completing it does. Quantities entered here are held against
            the order in the meantime, so two receipts cannot claim the same units.
        </span>
    </div>

    <form wire:submit="save" class="space-y-4">
        {{-- Where and when --}}
        <x-card title="Delivery">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-form.field label="Location" for="location_id" :error="$errors->first('form.location_id')" required
                              hint="Where this stock is being put away.">
                    <select id="location_id"
                            class="select select-bordered w-full @error('form.location_id') select-error @enderror"
                            wire:model="form.location_id">
                        <option value="">Select location</option>
                        @foreach ($this->locations() as $location)
                            <option value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field label="Receipt date" for="gr_date" :error="$errors->first('form.gr_date')" required>
                    <input id="gr_date" type="date"
                           class="input input-bordered w-full @error('form.gr_date') input-error @enderror"
                           wire:model="form.gr_date">
                </x-form.field>

                <x-form.field label="Transaction date" for="transaction_date"
                              :error="$errors->first('form.transaction_date')" required
                              hint="The date the movement is booked under.">
                    <input id="transaction_date" type="date"
                           class="input input-bordered w-full @error('form.transaction_date') input-error @enderror"
                           wire:model="form.transaction_date">
                </x-form.field>

                <x-form.field label="Remarks" for="remarks" :error="$errors->first('form.remarks')">
                    <input id="remarks" type="text"
                           class="input input-bordered w-full"
                           placeholder="Delivery note number, condition on arrival…"
                           maxlength="2000"
                           wire:model.blur="form.remarks">
                </x-form.field>
            </div>
        </x-card>

        {{-- Lines --}}
        <x-card title="Quantities" subtitle="Leave a line at zero to receive it on a later delivery">
            <x-slot:header>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-ghost btn-xs" wire:click="receiveAll">Receive all</button>
                    <button type="button" class="btn btn-ghost btn-xs" wire:click="receiveNone">Clear</button>
                </div>
            </x-slot:header>

            @error('form.items')
                <p class="mb-3 text-sm text-error">{{ $message }}</p>
            @enderror

            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th class="text-right">Ordered</th>
                            <th class="text-right">Received</th>
                            <th class="text-right">Outstanding</th>
                            <th class="w-40 text-right">Receive now</th>
                            <th class="text-right">Unit cost</th>
                            <th class="text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($form->items as $index => $row)
                            @php
                                $quantity = (float) $row['qty_to_receive'];
                                $complete = $row['outstanding'] <= 0;
                            @endphp

                            <tr wire:key="gr-item-{{ $index }}" @class(['opacity-50' => $complete])>
                                <td>
                                    <div class="font-medium">{{ $row['material'] }}</div>
                                    <div class="font-mono text-xs text-base-content/50">{{ $row['material_code'] }}</div>
                                </td>
                                <td class="tabular text-right">
                                    {{ number_format($row['qty_ordered'], 2) }}
                                    <span class="text-xs text-base-content/50">{{ $row['uom'] }}</span>
                                </td>
                                <td class="tabular text-right">{{ number_format($row['qty_received'], 2) }}</td>
                                <td class="tabular text-right">
                                    @if ($complete)
                                        <span class="badge badge-success badge-sm">Complete</span>
                                    @else
                                        {{ number_format($row['outstanding'], 2) }}
                                    @endif
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="{{ $row['outstanding'] }}"
                                           class="tabular input input-bordered input-sm w-full text-right @error('form.items.' . $index . '.qty_to_receive') input-error @enderror"
                                           @disabled($complete)
                                           wire:model.live.debounce.400ms="form.items.{{ $index }}.qty_to_receive">

                                    @error('form.items.' . $index . '.qty_to_receive')
                                        <p class="mt-1 text-xs text-error">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="tabular text-right">{{ number_format($row['unit_cost'], 2) }}</td>
                                <td class="tabular text-right font-medium">
                                    {{ number_format($quantity * $row['unit_cost'], 2) }}
                                </td>
                            </tr>

                            {{-- Serial and batch, only for tracked materials with a quantity --}}
                            @if ($quantity > 0 && ($row['tracks_serial'] || $row['tracks_batch']))
                                <tr wire:key="gr-track-{{ $index }}">
                                    <td colspan="7" class="bg-base-200/50">
                                        <div class="flex flex-wrap items-center gap-3">
                                            @if ($row['tracks_serial'])
                                                <label class="flex items-center gap-2 text-sm">
                                                    <span class="text-base-content/60">Serial</span>
                                                    <input type="text"
                                                           class="input input-bordered input-xs w-56"
                                                           placeholder="e.g. SN-0001..0012"
                                                           wire:model.blur="form.items.{{ $index }}.serial_number">
                                                </label>
                                            @endif

                                            @if ($row['tracks_batch'])
                                                <label class="flex items-center gap-2 text-sm">
                                                    <span class="text-base-content/60">Batch</span>
                                                    <input type="text"
                                                           class="input input-bordered input-xs w-40"
                                                           placeholder="e.g. BATCH-A7734"
                                                           wire:model.blur="form.items.{{ $index }}.batch_number">
                                                </label>
                                            @endif

                                            <input type="text"
                                                   class="input input-bordered input-xs ml-auto w-full max-w-xs"
                                                   placeholder="Line remarks"
                                                   wire:model.blur="form.items.{{ $index }}.remarks">
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- What this receipt will do --}}
        <x-card title="This receipt">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-box border border-base-300 p-3">
                    <p class="text-sm text-base-content/60">Lines</p>
                    <p class="tabular mt-1 text-xl font-semibold">{{ $summary['lines'] }}</p>
                </div>

                <div class="rounded-box border border-base-300 p-3">
                    <p class="text-sm text-base-content/60">Quantity in</p>
                    <p class="tabular mt-1 text-xl font-semibold text-success">
                        +{{ number_format($summary['quantity'], 2) }}
                    </p>
                </div>

                <div class="rounded-box border border-base-300 p-3">
                    <p class="text-sm text-base-content/60">Stock value added</p>
                    <p class="tabular mt-1 text-xl font-semibold">
                        {{ $currency }} {{ number_format($summary['value'], 2) }}
                    </p>
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Prepare receipt' }}
                </button>

                @can('goods-receipt-complete')
                    <button type="button" class="btn btn-outline btn-sm"
                            wire:click="saveAndComplete" wire:loading.attr="disabled">
                        <span class="loading loading-spinner loading-xs" wire:loading wire:target="saveAndComplete"></span>
                        {{ $this->isEditing() ? 'Save and complete' : 'Receive and complete' }}
                    </button>
                @endcan

                <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
                   class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </div>
        </x-card>
    </form>
</div>
