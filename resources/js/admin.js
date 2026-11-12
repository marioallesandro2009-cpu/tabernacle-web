import '../css/admin.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AdminLayout from './Layouts/AdminLayout.vue';
import GuestLayout from './Layouts/GuestLayout.vue';

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    title: (t) => t,
    resolve: async (name) => {
        const page = (await pages[`./Pages/${name}.vue`]()).default;
        page.layout ??= name.startsWith('Admin/Auth/') ? GuestLayout : AdminLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: '#e8973f' },
});
