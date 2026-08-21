@props([
    'title' => 'Password must contain',
])

{{--
    The password policy, spelled out next to the field rather than only after a
    failed attempt. Mirrors Password::defaults() in AppServiceProvider.
--}}
<div {{ $attributes->merge(['class' => 'rounded-box bg-base-200 p-3']) }}>
    <p class="text-xs font-medium text-base-content/70">{{ $title }}</p>

    <ul class="mt-1 grid gap-1 text-xs text-base-content/60 sm:grid-cols-2">
        @foreach ([
            'At least 8 characters',
            'Upper and lower case letters',
            'At least one number',
            'At least one symbol',
            'Not found in known breach lists',
        ] as $rule)
            <li class="flex items-center gap-1">
                <x-icon name="check" class="size-3.5 opacity-50" />
                {{ $rule }}
            </li>
        @endforeach
    </ul>
</div>
