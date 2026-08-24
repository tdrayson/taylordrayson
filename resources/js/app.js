import '../css/app.css';

import { createSSRApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { applyTheme } from './useTheme';
import { seedPreferences } from './useSettings';
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
        // Before anything renders: the stores must hold what the server used,
        // or the first paint corrects itself in front of the reader.
        const preferences = props.initialPage.props.preferences;

        seedPreferences(preferences);
        applyTheme(preferences);

        // createSSRApp, not createApp: it hydrates the markup ssr.js already
        // rendered rather than throwing it away and mounting fresh. With no
        // server-rendered markup present it falls back to a normal mount, so
        // this is also correct when SSR is off.
        createSSRApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('twemoji', twemojiDirective)
            .mount(el);
    },
    progress: {
        color: '#3858E9',
    },
});
