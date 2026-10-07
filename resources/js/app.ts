import '../css/app.css'
import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { configureEcho } from '@laravel/echo-vue'
import AppLayout from './Layouts/AppLayout.vue'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import Tooltip from 'primevue/tooltip'
import 'primeicons/primeicons.css'
import { initializeTheme } from './composables/useTheme'

initializeTheme()

configureEcho({
    broadcaster: 'reverb',
})

createInertiaApp({
    title: (title) => `${title} - Marketplace Analytics`,

    resolve: async (name) => {
        const page = await resolvePageComponent<DefineComponent>(`./Pages/${name}.vue`, import.meta.glob<DefineComponent>('./Pages/**/*.vue'))

        if (!name.startsWith('Auth/') && name !== 'Account/Status') {
            page.default.layout = page.default.layout || AppLayout
        }

        return page
    },

    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .use(PrimeVue, {
                license: import.meta.env.VITE_PRIMEVUE_LICENSE_KEY,
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: '.app-dark',
                    },
                },
            })
            .directive('tooltip', Tooltip)
            .mount(el)
    },
})
