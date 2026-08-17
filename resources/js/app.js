import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import { setToastInstance } from './composables/useNotification';

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
        
        // Toast notifications: Uncomment after running 'npm install vue-toastification'
        // The app will work without it, using console logs for notifications
        /*
        import('vue-toastification').then(module => {
            const Toast = module.default;
            const { useToast } = module;
            import('vue-toastification/dist/index.css');
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
        }).catch(() => {
            console.warn('vue-toastification not installed');
        });
        */
        
        return app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
