<div class="mx-auto max-w-3xl space-y-4">
    <x-page-header title="Preferences"
                   subtitle="Name, logo and the formats every screen displays numbers and dates in">
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        {{-- Identity --}}
        <x-card title="Identity">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field label="Application name" for="app_name" :error="$errors->first('form.app_name')" required
                              hint="Shown in the sidebar, the browser tab and page titles.">
                    <input id="app_name" type="text"
                           class="input input-bordered w-full @error('form.app_name') input-error @enderror"
                           maxlength="255"
                           wire:model.blur="form.app_name">
                </x-form.field>

                <x-form.field label="Logo" for="app_logo" :error="$errors->first('form.app_logo')"
                              hint="PNG, JPG, SVG or WebP, up to 5 MB.">
                    <div class="flex items-center gap-3">
                        {{-- Preview: the pending upload if there is one, otherwise the stored logo --}}
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-box border border-base-300 bg-base-200">
                            @if ($form->app_logo)
                                <img src="{{ $form->app_logo->temporaryUrl() }}" alt="" class="size-full object-contain">
                            @else
                                <img src="{{ $this->logoUrl() }}" alt="" class="size-full object-contain">
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 space-y-2">
                            <input id="app_logo" type="file"
                                   class="file-input file-input-bordered file-input-sm w-full @error('form.app_logo') file-input-error @enderror"
                                   accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                   wire:model="form.app_logo">

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="loading loading-spinner loading-xs" wire:loading wire:target="form.app_logo"></span>

                                @if ($form->app_logo)
                                    <button type="button" class="btn btn-ghost btn-xs"
                                            wire:click="removeSelectedLogo">
                                        Discard selection
                                    </button>
                                @elseif (! $this->usingDefaultLogo())
                                    <button type="button" class="btn btn-ghost btn-xs text-error"
                                            wire:click="resetLogo">
                                        Reset to default
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-form.field>
            </div>
        </x-card>

        {{-- Numbers --}}
        <x-card title="Numbers and currency">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field label="Currency" for="currency" :error="$errors->first('form.currency')" required
                              hint="Used as the symbol on every amount.">
                    <select id="currency"
                            class="select select-bordered w-full @error('form.currency') select-error @enderror"
                            wire:model="form.currency">
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->code }}">
                                {{ $currency->code }} — {{ $currency->name }} ({{ $currency->symbol }})
                            </option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field label="Decimal places" for="decimal_places" :error="$errors->first('form.decimal_places')" required
                              hint="How quantities and amounts are displayed; stored values keep full precision.">
                    <input id="decimal_places" type="number" min="0" max="6" step="1"
                           class="tabular input input-bordered w-full text-right @error('form.decimal_places') input-error @enderror"
                           wire:model.blur="form.decimal_places">
                </x-form.field>
            </div>
        </x-card>

        {{-- Dates --}}
        <x-card title="Dates and time">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field label="Timezone" for="timezone" :error="$errors->first('form.timezone')" required>
                    <select id="timezone"
                            class="select select-bordered w-full @error('form.timezone') select-error @enderror"
                            wire:model="form.timezone">
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}">{{ str_replace('_', ' ', $timezone) }}</option>
                        @endforeach
                    </select>
                </x-form.field>

                <x-form.field label="Time format" for="time_format" :error="$errors->first('form.time_format')" required>
                    <select id="time_format"
                            class="select select-bordered w-full @error('form.time_format') select-error @enderror"
                            wire:model="form.time_format">
                        <option value="12h">12-hour (2:30 PM)</option>
                        <option value="24h">24-hour (14:30)</option>
                    </select>
                </x-form.field>

                <div class="sm:col-span-2">
                    <x-form.field label="Date format" :error="$errors->first('form.date_format')" required>
                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach ($dateFormats as $format => $example)
                                <label @class([
                                    'flex cursor-pointer items-center gap-2 rounded-box border p-2 transition',
                                    'border-primary bg-primary/5' => $form->date_format === $format,
                                    'border-base-300 hover:border-base-content/30' => $form->date_format !== $format,
                                ])>
                                    <input type="radio" class="radio radio-xs"
                                           value="{{ $format }}"
                                           wire:model.live="form.date_format">
                                    <span class="text-sm">{{ $example }}</span>
                                </label>
                            @endforeach
                        </div>
                    </x-form.field>
                </div>
            </div>
        </x-card>

        {{-- Appearance --}}
        <x-card title="Appearance" subtitle="Accent colour used for buttons, links and charts across the application">
            <x-form.field label="Colour theme" :error="$errors->first('form.color_theme')" required>
                <div class="flex flex-wrap gap-2">
                    @foreach ($themes as $theme)
                        <label @class([
                            'flex cursor-pointer items-center gap-2 rounded-box border px-3 py-2 transition',
                            'border-primary bg-primary/5' => $form->color_theme === $theme,
                            'border-base-300 hover:border-base-content/30' => $form->color_theme !== $theme,
                        ])>
                            <input type="radio" class="radio radio-xs" value="{{ $theme }}"
                                   wire:model.live="form.color_theme">
                            <span class="accent-swatch size-4 rounded-full" data-accent="{{ $theme }}"></span>
                            <span class="text-sm capitalize">{{ $theme }}</span>
                        </label>
                    @endforeach
                </div>
            </x-form.field>
        </x-card>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    Save preferences
                </button>

                <p class="ml-auto text-xs text-base-content/50">
                    Changes apply everywhere immediately; the preference cache is cleared on save.
                </p>
            </div>
        </x-card>
    </form>
</div>
