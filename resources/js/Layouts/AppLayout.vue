<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import ToggleSwitch from 'primevue/toggleswitch'
import { confirmAction } from '../utils/confirmAction'
import { useTheme } from '../composables/useTheme'

type AuthUser = {
    role?: string
    name?: string
    email?: string
}

type AppPageProps = {
    auth?: {
        user?: AuthUser
    }
}

type NavigationItem = {
    name: string
    href: string
    icon: string
}

type NavigationSection = {
    label: string
    items: NavigationItem[]
}

const page = usePage<AppPageProps>()
const mobileMenuActive = ref(false)
const staticMenuInactive = ref(false)
const accountOpen = ref(false)
const isNavigating = ref(false)
const logout = useForm({})

const removeNavigationStartListener = router.on('start', () => {
    isNavigating.value = true
})

const removeNavigationFinishListener = router.on('finish', () => {
    isNavigating.value = false
})

const { isDarkTheme, toggleDarkMode } = useTheme()

const isDesktop = () => window.innerWidth > 991

const navigation = computed<NavigationSection[]>(() => {
    if (page.props.auth?.user?.role === 'super_admin') {
        return [
            {
                label: 'Access Control',
                items: [
                    { name: 'Kelola Admin', href: '/admin/admins', icon: 'pi pi-fw pi-shield' },
                    { name: 'Kelola Akses User', href: '/admin/users', icon: 'pi pi-fw pi-users' },
                ],
            },
            {
                label: 'Server',
                items: [
                    { name: 'Services', href: '/admin/services', icon: 'pi pi-fw pi-server' },
                ],
            },
            {
                label: 'System Design',
                items: [
                    { name: 'UI Style Guide', href: '/admin/ui-style-guide', icon: 'pi pi-fw pi-palette' },
                ],
            },
        ]
    }

    return [
        {
            label: 'Home',
            items: [
                { name: 'Dashboard', href: '/', icon: 'pi pi-fw pi-home' },
            ],
        },
        {
            label: 'Operations',
            items: [
                { name: 'Orders', href: '/orders', icon: 'pi pi-fw pi-shopping-cart' },
                { name: 'Returns', href: '/returns', icon: 'pi pi-fw pi-replay' },
                { name: 'Customers', href: '/customers', icon: 'pi pi-fw pi-users' },
            ],
        },
        {
            label: 'Finance',
            items: [
                { name: 'Income', href: '/finance/income', icon: 'pi pi-fw pi-wallet' },
                { name: 'Reconciliation', href: '/finance/reconciliation', icon: 'pi pi-fw pi-sync' },
                { name: 'Income Reconciliation', href: '/finance/income-reconciliation', icon: 'pi pi-fw pi-wallet' },
                { name: 'Profit', href: '/finance/profit', icon: 'pi pi-fw pi-chart-line' },
            ],
        },
        {
            label: 'Products',
            items: [
                { name: 'Products', href: '/products', icon: 'pi pi-fw pi-box' },
                { name: 'HPP', href: '/products/hpp', icon: 'pi pi-fw pi-tags' },
                { name: 'HPP Mapping', href: '/products/hpp-mapping', icon: 'pi pi-fw pi-share-alt' },
            ],
        },
        {
            label: 'Imports',
            items: [
                { name: 'Import History', href: '/imports', icon: 'pi pi-fw pi-history' },
                { name: 'Upload Files', href: '/imports/upload', icon: 'pi pi-fw pi-upload' },
            ],
        },
        {
            label: 'Integrations',
            items: [
                { name: 'Shopee API', href: '/integrations/shopee-api', icon: 'pi pi-fw pi-link' },
            ],
        },
        {
            label: 'Analytics',
            items: [
                { name: 'Sales', href: '/analytics/sales', icon: 'pi pi-fw pi-chart-bar' },
                { name: 'Products', href: '/analytics/products', icon: 'pi pi-fw pi-box' },
                { name: 'Customers', href: '/analytics/customers', icon: 'pi pi-fw pi-users' },
                { name: 'Profitability', href: '/analytics/profitability', icon: 'pi pi-fw pi-percentage' },
            ],
        },
        {
            label: 'Settings',
            items: [
                { name: 'Shop', href: '/settings/shop', icon: 'pi pi-fw pi-cog' },
                { name: 'Akun & Langganan', href: '/account/subscription', icon: 'pi pi-fw pi-credit-card' },
            ],
        },
    ]
})

const currentUrl = computed(() => page.url)

const isActive = (href: string) => href === '/'
    ? currentUrl.value === '/'
    : currentUrl.value === href || currentUrl.value.startsWith(`${href}/`)

const toggleMenu = () => {
    if (isDesktop()) {
        staticMenuInactive.value = !staticMenuInactive.value
        return
    }

    mobileMenuActive.value = !mobileMenuActive.value
}

const hideMobileMenu = () => {
    mobileMenuActive.value = false
}

const closeMenusAfterNavigation = () => {
    staticMenuInactive.value = false
    mobileMenuActive.value = false
}

const handleResize = () => {
    if (isDesktop()) {
        mobileMenuActive.value = false
    }
}

const submitLogout = () => {
    if (confirmAction('Apakah Anda yakin ingin logout?')) {
        logout.post('/logout')
    }
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
    removeNavigationStartListener()
    removeNavigationFinishListener()
    window.removeEventListener('resize', handleResize)
})
</script>

<template>
    <div
        class="layout-wrapper"
        :class="{
            'layout-static': true,
            'layout-static-inactive': staticMenuInactive,
            'layout-mobile-active': mobileMenuActive,
        }"
    >
        <div class="layout-topbar">
            <div class="layout-topbar-logo-container">
                <button
                    type="button"
                    class="layout-menu-button layout-topbar-action"
                    aria-label="Toggle navigation"
                    @click="toggleMenu"
                >
                    <i class="pi pi-bars" aria-hidden="true" />
                </button>

                <Link href="/" class="layout-topbar-logo" aria-label="Marketplace Analytics">
                    <span class="layout-brand-mark">M</span>
                    <span>Marketplace Analytics</span>
                </Link>
            </div>

            <div class="layout-topbar-actions">
                <div class="layout-config-menu">
                    <div class="flex items-center">
                        <button
                            type="button"
                            class="layout-topbar-action"
                            :aria-label="isDarkTheme ? 'Matikan dark mode' : 'Aktifkan dark mode'"
                            @click="toggleDarkMode"
                        >
                            <i :class="['pi', isDarkTheme ? 'pi-moon' : 'pi-sun']" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="layout-topbar-menu-button layout-topbar-action"
                    aria-label="Menu"
                >
                    <i class="pi pi-ellipsis-v" aria-hidden="true" />
                </button>

                <div class="layout-topbar-menu hidden lg:block">
                    <div class="layout-topbar-menu-content">
                        <button type="button" class="layout-topbar-action" aria-label="Calendar">
                            <i class="pi pi-calendar" aria-hidden="true" />
                            <span>Calendar</span>
                        </button>
                        <button type="button" class="layout-topbar-action" aria-label="Messages">
                            <i class="pi pi-inbox" aria-hidden="true" />
                            <span>Messages</span>
                        </button>

                        <div class="relative">
                            <button
                                type="button"
                                class="layout-topbar-action"
                                :aria-expanded="accountOpen"
                                aria-haspopup="menu"
                                aria-label="Profile"
                                @click="accountOpen = !accountOpen"
                            >
                                <i class="pi pi-user" aria-hidden="true" />
                                <span>Profile</span>
                            </button>

                            <div
                                v-if="accountOpen"
                                class="layout-profile-menu"
                                role="menu"
                            >
                                <div class="layout-profile-menu__identity">
                                    <strong>{{ page.props.auth?.user?.name || 'User' }}</strong>
                                    <span>{{ page.props.auth?.user?.email || '' }}</span>
                                </div>
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="layout-profile-menu__logout"
                                    :disabled="logout.processing"
                                    @click="submitLogout"
                                >
                                    {{ logout.processing ? 'Keluar...' : 'Logout' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <aside class="layout-sidebar" aria-label="Main navigation">
            <nav class="layout-menu">
                <template v-for="section in navigation" :key="section.label">
                    <li class="layout-root-menuitem">
                        <div class="layout-menuitem-root-text">{{ section.label }}</div>
                        <ul>
                            <li v-for="item in section.items" :key="item.href">
                                <Link
                                    :href="item.href"
                                    :class="{ 'active-route': isActive(item.href) }"
                                    @click="closeMenusAfterNavigation"
                                >
                                    <i :class="item.icon" class="layout-menuitem-icon" aria-hidden="true" />
                                    <span class="layout-menuitem-text">{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </li>
                </template>
            </nav>
        </aside>

        <div
            class="layout-mask"
            :class="{ 'animate-fadein': mobileMenuActive }"
            aria-hidden="true"
            @click="hideMobileMenu"
        />

        <div class="layout-main-container">
            <main class="layout-main" :aria-busy="isNavigating">
                <div v-if="isNavigating" class="layout-loading-bar" aria-label="Memuat konten" role="status" />

                <div :class="{ 'layout-main--loading': isNavigating }">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
