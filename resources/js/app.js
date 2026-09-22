import '../css/app.css';

import { createApp, createSSRApp, h } from 'vue';
import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { applyTheme } from './useTheme';
import { seedPreferences } from './useSettings';
import './composables/useDateFormat';
import { watchTextMode } from './composables/useTextMode';
import { twemojiDirective } from './directives/twemoji';

createInertiaApp({
    // Called while rendering <Head>, by which point Inertia's App component
    // has already set the current page's props, so the shared identity is
    // readable here despite running outside a component's own setup().
    title: (title) => {
        const { name } = usePage().props.identity;

        return title ? `${title} | ${name}` : name;
    },
    // Resolved lazily (no `eager: true`) so Vite splits each page into its own
    // chunk. Eager loading put every page in one 1.7MB file, so a visitor
    // reading a note also downloaded the stats charts and the 404 Snake game.
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue'),
    ),
    setup({ el, App, props, plugin }) {
        // Before anything renders: the stores must hold what the server used,
        // or the first paint corrects itself in front of the reader.
        const preferences = props.initialPage.props.preferences;

        seedPreferences(preferences);
        applyTheme(preferences);

        // createSSRApp hydrates the markup ssr.js already rendered rather
        // than throwing it away. With SSR off (the local default) the
        // container is empty, and hydrating nothing warns on every page load,
        // so mount fresh instead.
        const create = el.hasChildNodes() ? createSSRApp : createApp;

        create({ render: () => h(App, props) })
            .use(plugin)
            .directive('twemoji', twemojiDirective)
            .mount(el);

        watchTextMode(document.body);
    },
    progress: {
        color: '#3858E9',
    },
});
