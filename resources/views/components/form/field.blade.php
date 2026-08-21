@props([
    'label' => null,
    'for' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

{{--
    Label + control + validation message, so every form field on every screen
    reports errors the same way.

        <x-form.field label="Acronym" for="acronym" :error="$errors->first('form.acronym')" required>
            <input id="acronym" type="text" class="input input-bordered w-full" wire:model="form.acronym">
        </x-form.field>
--}}
<div {{ $attributes->merge(['class' => 'form-control w-full']) }}>
    @if ($label)
        <label class="label" @if ($for) for="{{ $for }}" @endif>
            <span class="label-text font-medium">
                {{ $label }}
                @if ($required)
                    <span class="text-error">*</span>
                @endif
            </span>
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        <p class="mt-1 flex items-center gap-1 text-sm text-error">
            <x-icon name="exclamation-circle" class="size-4" />
            {{ $error }}
        </p>
    @elseif ($hint)
        <p class="mt-1 text-sm text-base-content/60">{{ $hint }}</p>
    @endif
</div>
