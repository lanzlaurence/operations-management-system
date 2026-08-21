@props([
    'name',
    'title' => null,
    'closable' => true,
])

{{--
    DaisyUI dialog driven from Livewire.

    Open it from a component with `$this->dispatch('open-modal', name: 'delete-uom')`
    (or `x-on:click="$dispatch('open-modal', { name: 'delete-uom' })"` from Blade)
    and close it with `close-modal`. Keeping the state in Alpine means opening a
    confirmation costs no round trip.
--}}
<div x-data="{
        open: false,
        show(event) { if (event.detail?.name === '{{ $name }}') this.open = true },
        hide(event) { if (! event.detail?.name || event.detail.name === '{{ $name }}') this.open = false },
     }"
     x-on:open-modal.window="show($event)"
     x-on:close-modal.window="hide($event)"
     x-on:keydown.escape.window="{{ $closable ? 'open = false' : '' }}">

    <dialog class="modal" :class="open && 'modal-open'" aria-modal="true">
        <div class="modal-box max-w-lg" x-on:click.outside="{{ $closable ? 'open = false' : '' }}">
            @if ($title)
                <h3 class="text-lg font-semibold">{{ $title }}</h3>
            @endif

            <div class="py-3">
                {{ $slot }}
            </div>

            @if (isset($actions))
                <div class="modal-action">
                    {{ $actions }}
                </div>
            @endif

            @if ($closable)
                <button type="button" class="btn btn-circle btn-ghost btn-sm absolute right-2 top-2"
                        x-on:click="open = false" aria-label="Close">
                    <x-icon name="x-mark" class="size-4" />
                </button>
            @endif
        </div>
    </dialog>
</div>
