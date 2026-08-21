import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            // One stack: Blade + Livewire + Alpine, so one CSS and one JS entry.
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: [
                'routes/**',
                'resources/views/**',
                'app/Livewire/**',
                'config/navigation.php',
            ],
        }),
        tailwindcss(),
    ],
});
