{{--
    Toast host.

    Two sources feed it:
      - session flashes (`->with('success', ...)`) from full page redirects,
        which is what the controllers still do while both stacks coexist;
      - `$this->dispatch('toast', type: 'success', message: '...')` from
        Livewire components, handled by the Alpine store.
--}}
<div class="toast toast-end z-[60]" x-data>
    @foreach (['success' => 'alert-success', 'error' => 'alert-error', 'warning' => 'alert-warning', 'status' => 'alert-info'] as $key => $class)
        @if (session()->has($key))
            <div class="alert {{ $class }} shadow-lg"
                 x-data="{ show: true }"
                 x-show="show"
                 x-init="setTimeout(() => show = false, 5000)">
                <span>{{ session($key) }}</span>
                <button type="button" class="btn btn-circle btn-ghost btn-xs" @click="show = false">
                    <x-icon name="x-mark" class="size-4" />
                </button>
            </div>
        @endif
    @endforeach

    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="alert shadow-lg"
             :class="{
                 'alert-success': toast.type === 'success',
                 'alert-error': toast.type === 'error',
                 'alert-warning': toast.type === 'warning',
                 'alert-info': toast.type === 'info',
             }">
            <span x-text="toast.message"></span>
            <button type="button" class="btn btn-circle btn-ghost btn-xs" @click="$store.toasts.dismiss(toast.id)">
                <x-icon name="x-mark" class="size-4" />
            </button>
        </div>
    </template>
</div>
