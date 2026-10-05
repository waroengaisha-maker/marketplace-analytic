<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import ToggleSwitch from 'primevue/toggleswitch'
import Sidebar from 'primevue/sidebar'
import SidebarAside from 'primevue/sidebaraside'
import SidebarBackdrop from 'primevue/sidebarbackdrop'
import SidebarContent from 'primevue/sidebarcontent'
import SidebarFooter from 'primevue/sidebarfooter'
import SidebarGroup from 'primevue/sidebargroup'
import SidebarGroupContent from 'primevue/sidebargroupcontent'
import SidebarGroupLabel from 'primevue/sidebargrouplabel'
import SidebarHeader from 'primevue/sidebarheader'
import SidebarLayout from 'primevue/sidebarlayout'
import SidebarMain from 'primevue/sidebarmain'
import SidebarMenu from 'primevue/sidebarmenu'
import SidebarMenuButton from 'primevue/sidebarmenubutton'
import SidebarMenuItem from 'primevue/sidebarmenuitem'
import SidebarPanel from 'primevue/sidebarpanel'
import SidebarSpacer from 'primevue/sidebarspacer'
import SidebarTrigger from 'primevue/sidebartrigger'
import { confirmAction } from '../utils/confirmAction'

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

const page = usePage<AppPageProps>()
const sidebarOpen = ref(true)
const sidebarCollapsed = ref(false)
const isMobile = ref(false)
const accountOpen = ref(false)
const darkMode = ref(false)
const isNavigating = ref(false)
const logout = useForm({})
const isPrivileged = computed(() => ['admin', 'super_admin'].includes(page.props.auth?.user?.role ?? ''))

const removeNavigationStartListener = router.on('start', () => {
    isNavigating.value = true
})

const removeNavigationFinishListener = router.on('finish', () => {
    isNavigating.value = false
})

const applyDarkMode = (enabled: boolean) => {
    document.documentElement.classList.toggle('dark', enabled)
    localStorage.setItem('marketplace-dark-mode', enabled ? 'true' : 'false')
}

const updateViewport = () => {
    const wasMobile = isMobile.value
    isMobile.value = window.matchMedia('(max-width: 1023px)').matches

    if (isMobile.value && !wasMobile) {
        sidebarOpen.value = false
    } else if (!isMobile.value && wasMobile) {
        sidebarOpen.value = !sidebarCollapsed.value
    }
}

onMounted(() => {
    darkMode.value = localStorage.getItem('marketplace-dark-mode') === 'true'
    sidebarCollapsed.value = localStorage.getItem('marketplace-sidebar-collapsed') === 'true'
    if (window.innerWidth < 1024) {
        sidebarCollapsed.value = false
    }
    updateViewport()
    sidebarOpen.value = isMobile.value ? false : !sidebarCollapsed.value
    window.addEventListener('resize', updateViewport)
    applyDarkMode(darkMode.value)
})

onUnmounted(() => {
    removeNavigationStartListener()
    removeNavigationFinishListener()
    window.removeEventListener('resize', updateViewport)
})

watch(darkMode, (enabled) => {
    applyDarkMode(enabled)
})

watch(sidebarOpen, (open) => {
    if (!isMobile.value) {
        sidebarCollapsed.value = !open
        localStorage.setItem('marketplace-sidebar-collapsed', open ? 'false' : 'true')
    }
})

const navigation = computed(() => {
    if (page.props.auth?.user?.role === 'super_admin') {
        return [
            {
                label: 'Access Control',
                items: [
                    { name: 'Kelola Admin', href: '/admin/admins', icon: 'pi pi-shield', color: 'text-slate-400' },
                    { name: 'Kelola Akses User', href: '/admin/users', icon: 'pi pi-user-edit', color: 'text-slate-400' },
                ],
            },
            {
                label: 'Server',
                items: [
                    { name: 'Services', href: '/admin/services', icon: 'pi pi-server', color: 'text-slate-400' },
                ],
            },
            {
                label: 'System Design',
                items: [
                    { name: 'UI Style Guide', href: '/admin/ui-style-guide', icon: 'pi pi-palette', color: 'text-slate-400' },
                ],
            },
        ]
    }

    return [
        {
            label: 'Main',
            items: [
                { name: 'Dashboard', href: '/', icon: 'pi pi-home', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Operations',
            items: [
                { name: 'Returns', href: '/returns', icon: 'pi pi-replay', color: 'text-slate-400' },
                { name: 'Customers', href: '/customers', icon: 'pi pi-users', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Finance',
            items: [
                { name: 'Income', href: '/finance/income', icon: 'pi pi-wallet', color: 'text-slate-400' },
                { name: 'Reconciliation', href: '/finance/reconciliation', icon: 'pi pi-sync', color: 'text-slate-400' },
                { name: 'Income Reconciliation', href: '/finance/income-reconciliation', icon: 'pi pi-wallet', color: 'text-slate-400' },
                { name: 'Profit', href: '/finance/profit', icon: 'pi pi-chart-line', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Products',
            items: [
                { name: 'Products', href: '/products', icon: 'pi pi-box', color: 'text-slate-400' },
                { name: 'HPP', href: '/products/hpp', icon: 'pi pi-tags', color: 'text-slate-400' },
                { name: 'HPP Mapping', href: '/products/hpp-mapping', icon: 'pi pi-share-alt', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Imports',
            items: [
                { name: 'Import History', href: '/imports', icon: 'pi pi-history', color: 'text-slate-400' },
                { name: 'Upload Files', href: '/imports/upload', icon: 'pi pi-upload', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Integrations',
            items: [
                { name: 'Shopee API', href: '/integrations/shopee-api', icon: 'pi pi-link', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Analytics',
            items: [
                { name: 'Sales', href: '/analytics/sales', icon: 'pi pi-chart-bar', color: 'text-slate-400' },
                { name: 'Products', href: '/analytics/products', icon: 'pi pi-box', color: 'text-slate-400' },
                { name: 'Customers', href: '/analytics/customers', icon: 'pi pi-users', color: 'text-slate-400' },
                { name: 'Profitability', href: '/analytics/profitability', icon: 'pi pi-percentage', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Settings',
            items: [
                { name: 'Shop', href: '/settings/shop', icon: 'pi pi-cog', color: 'text-slate-400' },
                { name: 'Akun & Langganan', href: '/account/subscription', icon: 'pi pi-credit-card', color: 'text-slate-400' },
            ],
        },
    ]
})

const currentUrl = computed(() => page.url)

const mainAreaLeft = computed(() => {
    if (isMobile.value) return '0rem'
    return sidebarOpen.value ? '16rem' : '5rem'
})

const isActive = (href: string) => {
    if (href === '/') {
        return currentUrl.value === '/'
    }

    return currentUrl.value === href ||
        currentUrl.value.startsWith(`${href}/`)
}

const closeSidebar = () => {
    if (isMobile.value) {
        sidebarOpen.value = false
    }
}

const submitLogout = () => {
    if (confirmAction('Apakah Anda yakin ingin logout?')) {
        logout.post('/logout')
    }
}
</script>

const navigation = computed(() => {
    if (page.props.auth?.user?.role === 'super_admin') {
        return [
            {
                label: 'Access Control',
                items: [
                    { name: 'Kelola Admin', href: '/admin/admins', icon: 'pi pi-shield', color: 'text-slate-400' },
                    { name: 'Kelola Akses User', href: '/admin/users', icon: 'pi pi-user-edit', color: 'text-slate-400' },
                ],
            },
            {
                label: 'Server',
                items: [
                    { name: 'Services', href: '/admin/services', icon: 'pi pi-server', color: 'text-slate-400' },
                ],
            },
            {
                label: 'System Design',
                items: [
                    { name: 'UI Style Guide', href: '/admin/ui-style-guide', icon: 'pi pi-palette', color: 'text-slate-400' },
                ],
            },
        ]
    }

    return [
        {
            label: 'Main',
            items: [
                { name: 'Dashboard', href: '/', icon: 'pi pi-home', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Operations',
            items: [
                { name: 'Returns', href: '/returns', icon: 'pi pi-replay', color: 'text-slate-400' },
                { name: 'Customers', href: '/customers', icon: 'pi pi-users', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Finance',
            items: [
                { name: 'Income', href: '/finance/income', icon: 'pi pi-wallet', color: 'text-slate-400' },
                { name: 'Reconciliation', href: '/finance/reconciliation', icon: 'pi pi-sync', color: 'text-slate-400' },
                { name: 'Income Reconciliation', href: '/finance/income-reconciliation', icon: 'pi pi-wallet', color: 'text-slate-400' },
                { name: 'Profit', href: '/finance/profit', icon: 'pi pi-chart-line', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Products',
            items: [
                { name: 'Products', href: '/products', icon: 'pi pi-box', color: 'text-slate-400' },
                { name: 'HPP', href: '/products/hpp', icon: 'pi pi-tags', color: 'text-slate-400' },
                { name: 'HPP Mapping', href: '/products/hpp-mapping', icon: 'pi pi-share-alt', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Imports',
            items: [
                { name: 'Import History', href: '/imports', icon: 'pi pi-history', color: 'text-slate-400' },
                { name: 'Upload Files', href: '/imports/upload', icon: 'pi pi-upload', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Integrations',
            items: [
                { name: 'Shopee API', href: '/integrations/shopee-api', icon: 'pi pi-link', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Analytics',
            items: [
                { name: 'Sales', href: '/analytics/sales', icon: 'pi pi-chart-bar', color: 'text-slate-400' },
                { name: 'Products', href: '/analytics/products', icon: 'pi pi-box', color: 'text-slate-400' },
                { name: 'Customers', href: '/analytics/customers', icon: 'pi pi-users', color: 'text-slate-400' },
                { name: 'Profitability', href: '/analytics/profitability', icon: 'pi pi-percentage', color: 'text-slate-400' },
            ],
        },
        {
            label: 'Settings',
            items: [
                { name: 'Shop', href: '/settings/shop', icon: 'pi pi-cog', color: 'text-slate-400' },
                { name: 'Akun & Langganan', href: '/account/subscription', icon: 'pi pi-credit-card', color: 'text-slate-400' },
            ],
        },
    ]
})

const currentUrl = computed(() => page.url)

const isActive = (href: string) => {
    if (href === '/') return currentUrl.value === '/'
    return currentUrl.value === href || currentUrl.value.startsWith(`${href}/`)
}

const closeSidebar = () => {
    if (isMobile.value) sidebarOpen.value = false
}

const submitLogout = () => {
    if (confirmAction('Apakah Anda yakin ingin logout?')) logout.post('/logout')
}
</script>

<template>
    <div class="layout-shell" :class="{ 'layout-shell--collapsed': !sidebarOpen && !isMobile, 'layout-shell--mobile': isMobile }">
        <aside
            class="layout-sidebar"
            :class="{ 'layout-sidebar--mobile-open': isMobile && sidebarOpen }"
            aria-label="Main navigation"
        >
            <div class="layout-sidebar__brand">
                <Link href="/" class="flex min-w-0 items-center gap-3 no-underline" @click="closeSidebar">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 text-sm font-bold text-white shadow-sm">M</span>
                    <span v-if="sidebarOpen || isMobile" class="min-w-0">
                        <span class="block truncate text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-100">Marketplace</span>
                        <span class="block text-[10px] font-medium uppercase tracking-[0.16em] text-slate-400">Analytics</span>
                    </span>
                </Link>
            </div>

            <nav class="layout-sidebar__content">
                <div v-for="section in navigation" :key="section.label" class="layout-menu-section">
                    <div v-if="sidebarOpen || isMobile" class="layout-menu-section__label">{{ section.label }}</div>
                    <div class="layout-menu-items">
                        <Link
                            v-for="item in section.items"
                            :key="item.href"
                            :href="item.href"
                            class="layout-menu-item"
                            :class="{ 'layout-menu-item--active': isActive(item.href) }"
                            :title="!sidebarOpen && !isMobile ? item.name : undefined"
                            @click="closeSidebar"
                        >
                            <i :class="[item.icon, item.color]" aria-hidden="true" />
                            <span v-if="sidebarOpen || isMobile" class="truncate">{{ item.name }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="layout-sidebar__footer">
                <div v-if="sidebarOpen || isMobile" class="truncate text-xs text-slate-400">
                    {{ page.props.auth?.user?.email || '' }}
                </div>
            </div>
        </aside>

        <div v-if="isMobile && sidebarOpen" class="layout-mask" aria-hidden="true" @click="sidebarOpen = false" />

        <div class="layout-main-container">
            <header class="layout-topbar">
                <button
                    type="button"
                    class="layout-menu-button"
                    aria-label="Toggle navigation"
                    @click="sidebarOpen = !sidebarOpen"
                >
                    <i class="pi pi-bars" aria-hidden="true" />
                </button>

                <div class="layout-topbar__identity">Marketplace Analytics</div>

                <div class="layout-topbar__actions">
                    <div class="hidden items-center gap-2 md:flex">
                        <i class="pi text-sm text-slate-500 dark:text-slate-400" :class="darkMode ? 'pi-moon' : 'pi-sun'" aria-hidden="true" />
                        <ToggleSwitch v-model="darkMode" :aria-label="darkMode ? 'Matikan dark mode' : 'Aktifkan dark mode'" />
                    </div>

                    <button type="button" class="layout-topbar__action hidden sm:inline-flex" aria-label="Search">
                        <i class="pi pi-search" aria-hidden="true" />
                    </button>

                    <button type="button" class="layout-topbar__action relative" aria-label="Notifications">
                        <i class="pi pi-bell" aria-hidden="true" />
                        <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-red-500" />
                    </button>

                    <div class="relative">
                        <button
                            type="button"
                            class="layout-profile-button"
                            :aria-expanded="accountOpen"
                            aria-haspopup="menu"
                            @click="accountOpen = !accountOpen"
                        >
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-700 text-xs font-semibold text-white">
                                {{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                            </span>
                            <span class="hidden max-w-32 truncate text-xs font-semibold text-slate-700 dark:text-slate-200 lg:block">
                                {{ page.props.auth?.user?.name || 'User' }}
                            </span>
                            <i class="pi pi-chevron-down hidden text-[10px] text-slate-400 lg:block" aria-hidden="true" />
                        </button>

                        <div v-if="accountOpen" class="absolute right-0 top-11 z-50 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-800" role="menu">
                            <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
                                <div class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ page.props.auth?.user?.name || 'User' }}</div>
                                <div class="truncate text-xs text-slate-400">{{ page.props.auth?.user?.email || '' }}</div>
                            </div>
                            <button type="button" role="menuitem" class="mt-2 flex w-full items-center rounded-lg px-3 py-2 text-left text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 disabled:opacity-50" :disabled="logout.processing" @click="submitLogout">
                                {{ logout.processing ? 'Keluar...' : 'Logout' }}
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="layout-main" :aria-busy="isNavigating">
                <div v-if="isNavigating" class="fixed inset-x-0 top-0 z-[100] h-0.5 overflow-hidden bg-transparent" aria-label="Memuat konten" role="status">
                    <div class="h-full w-1/3 animate-[loading-bar_1.2s_ease-in-out_infinite] rounded-full bg-blue-500" />
                </div>

                <div class="relative" :class="isNavigating ? 'pointer-events-none opacity-60 transition-opacity' : ''">
                    <slot />

                    <div
                        v-if="isNavigating"
                        class="fixed z-40 flex items-center justify-center bg-white/20 backdrop-blur-[1px] dark:bg-black/20"
                        :style="{ left: mainAreaLeft, top: '4rem', right: '0', bottom: '0' }"
                        aria-hidden="true"
                    >
                        <div class="flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-lg dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            <i class="pi pi-spin pi-spinner text-blue-500" />
                            <span>Memuat...</span>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
