import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { applyTheme } from './useTheme';
import { twemojiDirective } from './directives/twemoji';

createInertiaApp({
    title: (title) => (title ? `${title} | Taylor Drayson` : 'Taylor Drayson'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
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
