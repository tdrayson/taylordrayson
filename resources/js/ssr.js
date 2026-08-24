import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { seedPreferences } from './useSettings';
import { twemojiDirective } from './directives/twemoji';

/**
 * The server half of the app, kept as a separate entry point because app.js
 * provides its own `setup` callback, which stops @inertiajs/vite generating
 * one automatically.
 *
 * Whatever app.js does before mounting has to happen here too, or the server
 * renders one thing and the client hydrates into another. Theme is the
 * exception: the <html> class is written by Blade from the cookie, so there is
 * nothing here for applyTheme() to correct.
 */
createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} | Taylor Drayson` : 'Taylor Drayson'),
        resolve: (name) => {
            const pages = import.meta.glob('./Pages/**/*.vue');

            return pages[`./Pages/${name}.vue`]();
        },
        setup({ App, props, plugin }) {
            // Units and the rest of the display preferences, so a subtitle
            // renders in miles here if it will render in miles in the browser.
            seedPreferences(props.initialPage.props.preferences);

            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .directive('twemoji', twemojiDirective);
        },
    }),
);
