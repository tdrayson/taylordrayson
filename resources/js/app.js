import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { applyTheme } from './useTheme';
import { twemojiDirective } from './directives/twemoji';

createInertiaApp({
    title: (title) => (title ? `${title} | Taylor Drayson` : 'Taylor Drayson'),
    // Resolved lazily (no `eager: true`) so Vite splits each page into its own
    // chunk. Eager loading put every page in one 1.7MB file, so a visitor
    // reading a note also downloaded the stats charts and the 404 Snake game.
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue'),
    ),
    setup({ el, App, props, plugin }) {
        applyTheme();
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('twemoji', twemojiDirective)
            .mount(el);
    },
    progress: {
        color: '#3858E9',
    },
});
