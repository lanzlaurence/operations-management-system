/*
| Application JavaScript.
|
| Livewire bundles Alpine, so this file only carries the few browser behaviours
| the Blade views need: toasts, the theme, and the Alpine components for the
| address selects and the charts. Keep it small - anything heavier belongs in an
| Alpine component next to its markup.
*/

import addressCascade from './address-cascade';
import chart from './chart';

/**
 * Toasts are pushed from PHP with `$this->dispatch('toast', ...)` and from
 * Blade with `$flash`. Alpine picks them up through this store.
 */
document.addEventListener('alpine:init', () => {
    // Address forms opt into this; the dataset it needs loads on demand.
    window.Alpine.data('addressCascade', addressCascade);

    // Charts; Chart.js itself is imported on first use.
    window.Alpine.data('chart', chart);

    window.Alpine.store('toasts', {
        items: [],

        /** @param {{type?: string, message: string, timeout?: number}} toast */
        push({ type = 'info', message, timeout = 4000 }) {
            const id = Date.now() + Math.random();

            this.items.push({ id, type, message });

            if (timeout > 0) {
                setTimeout(() => this.dismiss(id), timeout);
            }
        },

        dismiss(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    });

    window.Alpine.store('theme', {
        /** 'light' | 'dark' | 'system' */
        appearance: document.documentElement.dataset.appearance ?? 'system',

        init() {
            this.apply(this.appearance);
        },

        set(appearance) {
            this.appearance = appearance;
            document.cookie = `appearance=${appearance};path=/;max-age=31536000;SameSite=Lax`;
            this.apply(appearance);
        },

        apply(appearance) {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const dark = appearance === 'dark' || (appearance === 'system' && prefersDark);

            document.documentElement.dataset.theme = dark ? 'business' : 'corporate';
            document.documentElement.classList.toggle('dark', dark);
        },
    });
});

/** Server-dispatched toasts: `$this->dispatch('toast', type: 'success', message: '...')`. */
document.addEventListener('livewire:init', () => {
    window.Livewire.on('toast', (payload) => {
        const toast = Array.isArray(payload) ? payload[0] : payload;

        window.Alpine.store('toasts').push(toast ?? {});
    });
});
