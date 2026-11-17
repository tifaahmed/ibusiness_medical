import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import { setToastInstance, useNotification } from './composables/useNotification';
import Toast, { useToast } from 'vue-toastification';
import 'vue-toastification/dist/index.css';

import { installSaveAndStay } from './composables/useSaveAndStay';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('invalid', (event) => {
    if (event.detail?.response?.status === 419) {
        event.preventDefault();
        window.location.reload();
    }
});

installSaveAndStay();

/* A page change that dies on the network (server down, connection dropped, a proxy resetting it) used to do
   nothing at all: the click just seemed ignored. Now the admin is told, and a plain page visit is retried once as a
   normal browser load, which also gets past a stale Inertia/asset state. Only GET visits are retried (a form post
   must never be sent twice by magic), and only once per address so a server that is really down cannot loop. */
let lastVisit = null;
router.on('start', (event) => { lastVisit = event.detail?.visit || null; });

const noticeAt = {};
const tell = (message) => {
    // One notice per kind every 20 s, so a polling request failing in the background does not flood the screen.
    const now = Date.now();
    if (now - (noticeAt[message] || 0) < 20000) return;
    noticeAt[message] = now;
    useNotification().error(message);
};

router.on('exception', (event) => {
    const visit = lastVisit;
    const method = String(visit?.method || 'get').toLowerCase();
    const target = visit?.url ? String(visit.url) : null;

    tell('The page could not be loaded — check the connection or try again.');

    if (method === 'get' && target && sessionStorage.getItem('retried-visit') !== target) {
        event.preventDefault();
        try { sessionStorage.setItem('retried-visit', target); } catch { /* storage may be blocked; the notice above still showed */ }
        window.location.href = target;
    }
});

router.on('finish', () => { try { sessionStorage.removeItem('retried-visit'); } catch { /* ignore */ } });

// A background request (the unread-messages poll, a search box, a save) that never reached the server.
window.axios.interceptors.response.use(null, (error) => {
    if (!error.response && error.request && error.code !== 'ERR_CANCELED') {
        tell('No connection to the server — what you just did may not have been saved.');
    }
    return Promise.reject(error);
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
