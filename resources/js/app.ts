import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';
import AppLayout from './AppLayout.vue';
import './bootstrap';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true });
        let page = pages[`./Pages/${name}.vue`];
        page.default.layout = page.default.layout || AppLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        delay: 10,
        color: '#7e22ce',
        showSpinner: true,
    },
});
