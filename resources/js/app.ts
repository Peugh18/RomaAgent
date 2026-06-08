import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { MotionPlugin } from '@vueuse/motion';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        readonly VITE_PUSHER_APP_KEY?: string;
        readonly VITE_PUSHER_CLUSTER?: string;
        readonly VITE_PUSHER_HOST?: string;
        readonly VITE_PUSHER_PORT?: string;
        readonly VITE_PUSHER_SCHEME?: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare global {
    interface Window {
         
        Echo: any;
         
        Pusher: any;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(MotionPlugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#10b981',
    },
});

initializeTheme();

if (import.meta.env.VITE_PUSHER_APP_KEY) {
    try {
        window.Pusher = Pusher;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: import.meta.env.VITE_PUSHER_APP_KEY,
            cluster: import.meta.env.VITE_PUSHER_CLUSTER ?? 'us2',
            wsHost: import.meta.env.VITE_PUSHER_HOST || `ws-${import.meta.env.VITE_PUSHER_CLUSTER ?? 'us2'}.pusher.com`,
            wsPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 443),
            forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrf,
                },
            },
            withCredentials: true,
        });
    } catch (error) {
        console.error('No se pudo inicializar Echo/Pusher:', error);
    }
}
