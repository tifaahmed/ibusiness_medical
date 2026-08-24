import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import { setToastInstance } from './composables/useNotification';
import Toast, { useToast } from 'vue-toastification';
import 'vue-toastification/dist/index.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('invalid', (event) => {
    if (event.detail?.response?.status === 419) {
        event.preventDefault();
        window.location.reload();
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia();
        const app = createApp({ render: () => h(App, props) });
        
        app.use(plugin);
        app.use(pinia);
        app.use(ZiggyVue);
        
        // Toast notifications. Without this the notification composable falls
        // back to console logs, so nothing reaches the screen.
        app.use(Toast, {
            transition: 'Vue-Toastification__bounce',
            maxToasts: 20,
            newestOnTop: true,
            position: 'top-right',
            timeout: 5000,
            closeOnClick: true,
            pauseOnFocusLoss: true,
            pauseOnHover: true,
            draggable: true,
            draggablePercent: 0.6,
            showCloseButtonOnHover: false,
            hideProgressBar: false,
            closeButton: 'button',
            icon: true,
            rtl: false,
            toastClassName: 'custom-toast',
            bodyClassName: 'custom-toast-body',
            containerClassName: 'custom-toast-container'
        });
        setToastInstance(useToast());

        return app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
    defaults: {
        prefetch: {
            // Wait a beat before a hover counts as intent, so sweeping the
            // mouse down the sidebar doesn't fire a request per item.
            hoverDelay: 120,
        },
    },
});
