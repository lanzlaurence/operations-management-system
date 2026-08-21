<div class="mx-auto max-w-2xl space-y-4">
    <x-page-header title="Settings" subtitle="Your personal settings for this application" />

    <x-settings-nav />

    <x-card title="Appearance" subtitle="Applies on this device, and takes effect immediately">
        <div class="grid gap-3 sm:grid-cols-3" x-data>
            @foreach ([
                'light' => ['Light', 'sun'],
                'dark' => ['Dark', 'moon'],
                'system' => ['System', 'computer-desktop'],
            ] as $value => [$label, $icon])
                <button type="button"
                        class="flex cursor-pointer flex-col items-center gap-2 rounded-box border p-4 transition"
                        :class="$store.theme.appearance === '{{ $value }}'
                            ? 'border-primary bg-primary/5'
                            : 'border-base-300 hover:border-base-content/30'"
                        x-on:click="$store.theme.set('{{ $value }}')">
                    <x-icon :name="$icon" class="size-6" />
                    <span class="text-sm font-medium">{{ $label }}</span>
                    <span class="text-xs text-base-content/60">
                        @switch($value)
                            @case('light') Always the light theme @break
                            @case('dark') Always the dark theme @break
                            @default Follow this device
                        @endswitch
                    </span>
                </button>
            @endforeach
        </div>

        <p class="mt-4 text-xs text-base-content/50">
            Stored in a cookie on this device, so it does not affect anyone else using the application.
        </p>
    </x-card>
</div>
