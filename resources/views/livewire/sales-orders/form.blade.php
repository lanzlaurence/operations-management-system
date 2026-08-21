@php
    $options = $this->options();
    $lineTotals = $this->lineTotals();
    $totals = $this->documentTotals();
    $customer = $this->selectedCustomer();
    $materials = $this->materialsById();
    $charges = $this->form->selectedCharges();
    $currency = $options['currency'];
@endphp

<div class="space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $salesOrder->code : 'New Sales Order'"
                   :subtitle="$this->isEditing()
                        ? 'Draft order — changes are saved against the same document'
                        : 'The order number is assigned when the draft is saved'">
        <x-slot:actions>
            @if ($this->isEditing())
                <a href="{{ route('sales-orders.show', $salesOrder) }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="eye" class="size-4" />
                    View
                </a>
            @endif

            <a href="{{ route('sales-orders.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        {{-- Header --}}
        <x-card title="Order details">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <x-form.field label="Customer" for="customer_id" :error="$errors->first('form.customer_id')" required>
                        <select id="customer_id"
                                class="select select-bordered w-full @error('form.customer_id') select-error @enderror"
                                wire:model.live="form.customer_id">
                            <option value="">Select customer</option>
                            @foreach ($options['customers'] as $option)
                                <option value="{{ $option->id }}">{{ $option->code }} — {{ $option->name }}</option>
                            @endforeach
                        </select>
                    </x-form.field>

                    @if ($customer)
                        <p class="mt-1 text-xs text-base-content/60">
                            Terms: {{ $customer->payment_terms ?: 'not set' }}
                            · Credit limit {{ $currency }} {{ number_format((float) $customer->credit_amount, 2) }}
                        </p>
                    @endif
                </div>

                <x-form.field label="Order date" for="order_date" :error="$errors->first('form.order_date')" required>
                    <input id="order_date" type="date"
                           class="input input-bordered w-full @error('form.order_date') input-error @enderror"
                           wire:model="form.order_date">
                </x-form.field>

                <x-form.field label="Delivery date" for="delivery_date" :error="$errors->first('form.delivery_date')"
                              hint="Leave blank if the customer has not committed.">
                    <input id="delivery_date" type="date"
                           class="input input-bordered w-full @error('form.delivery_date') input-error @enderror"
                           wire:model="form.delivery_date">
                </x-form.field>

                <x-form.field label="Reference" for="reference_no" :error="$errors->first('form.reference_no')"
                              hint="Your purchase requisition or the customer's quote number.">
                    <input id="reference_no" type="text"
                           class="input input-bordered w-full @error('form.reference_no') input-error @enderror"
                           maxlength="255"
                           wire:model.blur="form.reference_no">
                </x-form.field>

                <div class="lg:col-span-3">
                    <x-form.field label="Remarks" for="remarks" :error="$errors->first('form.remarks')">
                        <input id="remarks" type="text"
                               class="input input-bordered w-full"
                               placeholder="Anything the warehouse or the customer should know"
                               maxlength="2000"
                               wire:model.blur="form.remarks">
                    </x-form.field>
                </div>
            </div>
        </x-card>

        {{-- Line items --}}
        <x-card title="Items" subtitle="Line totals update as you type">
            <x-slot:header>
                <button type="button" class="btn btn-outline btn-sm" wire:click="addItem">
                    <x-icon name="plus" class="size-4" />
                    Add item
                </button>
            </x-slot:header>

            @error('form.items')
                <p class="mb-3 text-sm text-error">{{ $message }}</p>
            @enderror

            <div class="space-y-3">
                @foreach ($form->items as $index => $item)
                    @php
                        $line = $lineTotals[$index] ?? null;
                        $material = $materials->get((int) ($item['material_id'] ?: 0));
                        $shipped = (float) ($item['qty_shipped'] ?? 0);
                    @endphp

                    <div class="rounded-box border border-base-300 p-3" wire:key="item-{{ $index }}">
                        <div class="grid gap-3 lg:grid-cols-12">
                            {{-- Material --}}
                            <div class="lg:col-span-4">
                                <x-form.field label="Material"
                                              :error="$errors->first('form.items.' . $index . '.material_id')">
                                    <select class="select select-bordered w-full @error('form.items.' . $index . '.material_id') select-error @enderror"
                                            wire:model.live="form.items.{{ $index }}.material_id">
                                        <option value="">Select material</option>
                                        @foreach ($options['materials'] as $option)
                                            <option value="{{ $option->id }}">
                                                {{ $option->code }} — {{ $option->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </x-form.field>
                            </div>

                            {{-- Quantity --}}
                            <div class="lg:col-span-2">
                                <x-form.field label="Quantity"
                                              :error="$errors->first('form.items.' . $index . '.qty_ordered')">
                                    <label class="input input-bordered flex w-full items-center gap-1 @error('form.items.' . $index . '.qty_ordered') input-error @enderror">
                                        <input type="number" step="0.01" min="0"
                                               class="tabular grow text-right"
                                               wire:model.live.debounce.400ms="form.items.{{ $index }}.qty_ordered">
                                        @if ($material?->uom)
                                            <span class="text-xs text-base-content/60">{{ $material->uom->acronym }}</span>
                                        @endif
                                    </label>
                                </x-form.field>

                                @if ($shipped > 0)
                                    <p class="mt-1 text-xs text-base-content/60">
                                        {{ number_format($shipped, 2) }} already shipped
                                    </p>
                                @endif
                            </div>

                            {{-- Unit cost --}}
                            <div class="lg:col-span-2">
                                <x-form.field label="Unit cost"
                                              :error="$errors->first('form.items.' . $index . '.unit_price')">
                                    <label class="input input-bordered flex w-full items-center gap-1 @error('form.items.' . $index . '.unit_price') input-error @enderror">
                                        <span class="text-xs text-base-content/60">{{ $currency }}</span>
                                        <input type="number" step="0.01" min="0"
                                               class="tabular grow text-right"
                                               wire:model.live.debounce.400ms="form.items.{{ $index }}.unit_price">
                                    </label>
                                </x-form.field>
                            </div>

                            {{-- Line discount --}}
                            <div class="lg:col-span-2">
                                <x-form.field label="Discount"
                                              :error="$errors->first('form.items.' . $index . '.discount_amount')">
                                    <div class="join w-full">
                                        <select class="join-item select select-bordered w-20"
                                                wire:model.live="form.items.{{ $index }}.discount_type">
                                            <option value="">—</option>
                                            <option value="fixed">{{ $currency }}</option>
                                            <option value="percentage">%</option>
                                        </select>

                                        <input type="number" step="0.01" min="0"
                                               class="tabular join-item input input-bordered w-full text-right"
                                               @disabled(($item['discount_type'] ?? '') === '')
                                               wire:model.live.debounce.400ms="form.items.{{ $index }}.discount_amount">
                                    </div>
                                </x-form.field>
                            </div>

                            {{-- Line total --}}
                            <div class="flex items-end justify-between gap-2 lg:col-span-2">
                                <div class="min-w-0">
                                    <p class="text-xs text-base-content/60">Line total</p>
                                    <p class="tabular truncate font-semibold">
                                        {{ $line === null ? '—' : number_format($line->grossPrice, 2) }}
                                    </p>
                                    @if ($line && $line->vatPrice > 0)
                                        <p class="tabular text-xs text-base-content/50">
                                            incl. VAT {{ number_format($line->vatPrice, 2) }}
                                        </p>
                                    @endif
                                </div>

                                <button type="button"
                                        class="btn btn-ghost btn-sm text-error"
                                        wire:click="removeItem({{ $index }})"
                                        title="Remove this line">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            </div>
                        </div>

                        {{-- VAT and line remarks --}}
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-base-300 pt-2">
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" class="checkbox checkbox-xs"
                                       wire:model.live="form.items.{{ $index }}.is_vatable">
                                VAT
                            </label>

                            @if ($item['is_vatable'])
                                <select class="select select-bordered select-xs w-36"
                                        wire:model.live="form.items.{{ $index }}.vat_type">
                                    @foreach ($options['vatTypes'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>

                                <label class="input input-bordered input-xs flex w-24 items-center gap-1">
                                    <input type="number" step="0.01" min="0" max="100"
                                           class="tabular grow text-right"
                                           wire:model.live.debounce.400ms="form.items.{{ $index }}.vat_rate">
                                    <span class="text-xs text-base-content/60">%</span>
                                </label>

                                @if ($line)
                                    <span class="tabular text-xs text-base-content/50">
                                        net {{ number_format($line->netPrice, 2) }}
                                        + vat {{ number_format($line->vatPrice, 2) }}
                                    </span>
                                @endif
                            @endif

                            <input type="text"
                                   class="input input-bordered input-xs ml-auto w-full max-w-xs"
                                   placeholder="Line remarks"
                                   maxlength="1000"
                                   wire:model.blur="form.items.{{ $index }}.remarks">
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Charges --}}
            <x-card title="Charges" subtitle="Applied after the header discount">
                <x-slot:header>
                    <button type="button" class="btn btn-outline btn-sm" wire:click="addCharge">
                        <x-icon name="plus" class="size-4" />
                        Add charge
                    </button>
                </x-slot:header>

                @if ($form->charges === [])
                    <p class="text-sm text-base-content/60">No charges on this order.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($form->charges as $index => $row)
                            @php
                                $charge = $charges[$index] ?? null;
                                $amount = $totals->amountForCharge($index);
                            @endphp

                            <div class="flex items-center gap-2" wire:key="charge-{{ $index }}">
                                <select class="select select-bordered select-sm flex-1"
                                        wire:model.live="form.charges.{{ $index }}.charge_id">
                                    <option value="">Select charge</option>
                                    @foreach ($options['charges'] as $option)
                                        <option value="{{ $option->id }}">
                                            {{ $option->name }}
                                            ({{ $option->type->label() }},
                                            {{ $option->value_type === \App\Enums\ChargeValueType::Percentage
                                                ? rtrim(rtrim(number_format((float) $option->value, 2), '0'), '.') . '%'
                                                : $currency . ' ' . number_format((float) $option->value, 2) }})
                                        </option>
                                    @endforeach
                                </select>

                                <span @class([
                                    'tabular w-28 text-right text-sm',
                                    'text-error' => $charge?->type === \App\Enums\ChargeType::Discount,
                                ])>
                                    {{ $charge === null ? '—' : ($charge->type->sign() < 0 ? '−' : '+') . number_format($amount, 2) }}
                                </span>

                                <button type="button" class="btn btn-ghost btn-sm text-error"
                                        wire:click="removeCharge({{ $index }})">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- Totals --}}
            <x-card title="Totals">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-base-content/60">Subtotal before discounts</span>
                        <span class="tabular">{{ number_format($totals->totalBeforeDiscount, 2) }}</span>
                    </div>

                    @if ($totals->totalItemDiscount > 0)
                        <div class="flex justify-between text-error">
                            <span>Line discounts</span>
                            <span class="tabular">−{{ number_format($totals->totalItemDiscount, 2) }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="text-base-content/60">Net</span>
                        <span class="tabular">{{ number_format($totals->totalNetPrice, 2) }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-base-content/60">VAT</span>
                        <span class="tabular">{{ number_format($totals->totalVat, 2) }}</span>
                    </div>

                    <div class="flex justify-between border-t border-base-300 pt-2">
                        <span class="text-base-content/60">Gross</span>
                        <span class="tabular font-medium">{{ number_format($totals->totalGross, 2) }}</span>
                    </div>

                    {{-- Header discount --}}
                    <div class="flex items-center justify-between gap-2 pt-1">
                        <div class="join">
                            <select class="join-item select select-bordered select-xs w-20"
                                    wire:model.live="form.discount_type">
                                <option value="">No discount</option>
                                <option value="fixed">{{ $currency }}</option>
                                <option value="percentage">%</option>
                            </select>

                            <input type="number" step="0.01" min="0"
                                   class="tabular join-item input input-bordered input-xs w-24 text-right"
                                   @disabled($form->discount_type === '')
                                   wire:model.live.debounce.400ms="form.discount_amount">
                        </div>

                        <span class="tabular text-error">
                            {{ $totals->headerDiscountTotal > 0 ? '−' . number_format($totals->headerDiscountTotal, 2) : '0.00' }}
                        </span>
                    </div>
                    @error('form.discount_amount')
                        <p class="text-xs text-error">{{ $message }}</p>
                    @enderror

                    @if ($totals->totalCharges != 0)
                        <div class="flex justify-between">
                            <span class="text-base-content/60">Charges</span>
                            <span class="tabular">
                                {{ $totals->totalCharges < 0 ? '−' : '+' }}{{ number_format(abs($totals->totalCharges), 2) }}
                            </span>
                        </div>
                    @endif

                    <div class="flex items-baseline justify-between border-t-2 border-base-300 pt-2">
                        <span class="font-semibold">Grand total</span>
                        <span class="tabular text-xl font-semibold">
                            {{ $currency }} {{ number_format($totals->grandTotal, 2) }}
                        </span>
                    </div>
                </div>
            </x-card>
        </div>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Save as draft' }}
                </button>

                @unless ($this->isEditing())
                    @can('sales-order-post')
                        <button type="button" class="btn btn-outline btn-sm"
                                wire:click="saveAndPost" wire:loading.attr="disabled">
                            <span class="loading loading-spinner loading-xs" wire:loading wire:target="saveAndPost"></span>
                            Save and post
                        </button>
                    @endcan
                @endunless

                <a href="{{ route('sales-orders.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </div>
        </x-card>
    </form>
</div>
