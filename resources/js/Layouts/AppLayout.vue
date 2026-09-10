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

const navigation = computed(() => [
    ...(!isPrivileged.value ? [{
        label: 'Main',
        items: [
            { name: 'Dashboard', href: '/', icon: 'pi pi-home', color: 'text-slate-400' },
        ],
    }] : []),
    ...(isPrivileged.value ? [] : [{
        label: 'Operations',
        items: [
            { name: 'Orders', href: '/orders', icon: 'pi pi-shopping-cart', color: 'text-slate-400' },
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
        label: 'Analytics',
        items: [
            { name: 'Sales', href: '/analytics/sales', icon: 'pi pi-chart-bar', color: 'text-slate-400' },
            { name: 'Products', href: '/analytics/products', icon: 'pi pi-box', color: 'text-slate-400' },
            { name: 'Customers', href: '/analytics/customers', icon: 'pi pi-users', color: 'text-slate-400' },
            { name: 'Profitability', href: '/analytics/profitability', icon: 'pi pi-percentage', color: 'text-slate-400' },
        ],
    }]),
    ...(isPrivileged.value ? [{
        label: 'Access Control',
        items: [
            ...(page.props.auth?.user?.role === 'super_admin' ? [
                { name: 'Kelola Admin', href: '/admin/admins', icon: 'pi pi-shield', color: 'text-slate-400' },
            ] : []),
            { name: 'Kelola Akses User', href: '/admin/users', icon: 'pi pi-user-edit', color: 'text-slate-400' },
        ],
    }] : []),
    ...(isPrivileged.value ? [] : [{
        label: 'Settings',
        items: [
            { name: 'Shop', href: '/settings/shop', icon: 'pi pi-cog', color: 'text-slate-400' },
            { name: 'Akun & Langganan', href: '/account/subscription', icon: 'pi pi-credit-card', color: 'text-slate-400' },
        ],
    }]),
])

const currentUrl = computed(() => page.url)

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

<template>
    <div class="h-screen w-full overflow-hidden bg-slate-50 p-0 text-slate-900 dark:bg-black dark:text-slate-100">
        <SidebarLayout class="h-full min-h-0 w-full overflow-hidden border-0 bg-white dark:bg-black">
            <SidebarBackdrop v-if="isMobile && sidebarOpen" class="fixed!" />
        <Sidebar
            id="main-sidebar"
            v-model:open="sidebarOpen"
            :variant="isMobile ? 'floating' : 'sidebar'"
            :collapsible="isMobile ? 'offcanvas' : 'icon'"
            :overlay="isMobile"
            width="16rem"
            icon-width="5rem"
            class="border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-black"
        >
            <SidebarSpacer />
            <SidebarAside>
                <SidebarPanel>
                    <SidebarHeader class="border-b border-slate-200 px-4 py-4 dark:border-slate-800">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton as-child class="px-1">
                                    <template #default="{ class: buttonClass = '', a11yAttrs = {} }">
                                        <Link v-bind="a11yAttrs" href="/" :class="[buttonClass, 'no-underline']" @click="closeSidebar">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-gradient-to-br from-violet-500 to-indigo-600 text-xs font-bold leading-none text-white shadow-sm">M</span>
                                            <span class="sidebar-brand-text min-w-0">
                                                <span class="block text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100">Marketplace</span>
                                                <span class="block text-[10px] font-medium uppercase tracking-wider text-slate-400">Analytics</span>
                                            </span>
                                        </Link>
                                    </template>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarHeader>
                    <SidebarContent class="overflow-y-auto px-3 py-5">
                        <SidebarGroup v-for="section in navigation" :key="section.label" class="mb-6">
                            <SidebarGroupLabel class="px-3 pb-2 text-[10px] font-medium tracking-normal text-slate-400 dark:text-slate-500">{{ section.label }}</SidebarGroupLabel>
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    <SidebarMenuItem v-for="item in section.items" :key="item.href">
                                        <SidebarMenuButton as-child :is-active="isActive(item.href)">
                                            <template #default="{ class: buttonClass = '', a11yAttrs = {} }">
                                                <Link
                                                    v-bind="a11yAttrs"
                                                    :href="item.href"
                                                    :class="[buttonClass, 'h-8 rounded-md px-3 text-xs font-normal text-slate-600 no-underline hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white']"
                                                    @click="closeSidebar"
                                                >
                                                    <i :class="[item.icon, item.color, 'text-[11px]']" aria-hidden="true" />
                                                    <span class="sidebar-menu-label">{{ item.name }}</span>
                                                </Link>
                                            </template>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </SidebarContent>
                    <!-- <SidebarFooter class="border-t border-slate-200 p-3 dark:border-slate-800">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton class="p-1">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                                        {{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                    </span>
                                    <span class="sidebar-footer-label truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ page.props.auth?.user?.email || '' }}
                                    </span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarFooter> -->
                </SidebarPanel>
            </SidebarAside>
        </Sidebar>

        <SidebarMain class="min-w-0 overflow-y-auto bg-slate-50 dark:bg-black">

            <!-- Topbar -->
            <header class="sticky top-0 z-30 flex h-12 min-w-0 items-center border-b border-slate-200 bg-white/95 px-3 backdrop-blur dark:border-slate-800 dark:bg-black/95 sm:px-4">

                <SidebarTrigger target="main-sidebar" as-child>
                    <template #default="{ class: triggerClass = '', a11yAttrs = {}, onClick = () => {} }">
                        <button v-bind="a11yAttrs" type="button" :class="[triggerClass, 'mr-3 rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800']" aria-label="Toggle navigation" @click="onClick">
                            <span class="flex h-5 w-5 items-center justify-center">
                            <i class="pi pi-bars" aria-hidden="true" />
                            </span>
                        </button>
                    </template>
                </SidebarTrigger>

                <div class="flex-1">
                    <div class="hidden text-sm font-medium text-slate-400 dark:text-slate-500 sm:block">
                        Marketplace Analytics
                    </div>
                </div>

                <div class="mr-2 flex items-center gap-2">
                    <i
                        class="pi text-sm text-slate-500 dark:text-slate-400"
                        :class="darkMode ? 'pi-moon' : 'pi-sun'"
                        aria-hidden="true"
                    />
                    <ToggleSwitch
                        v-model="darkMode"
                        :aria-label="darkMode ? 'Matikan dark mode' : 'Aktifkan dark mode'"
                    />
                </div>

                <!-- Search -->
                <button
                    type="button"
                    class="hidden items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-400 transition hover:border-slate-300 hover:bg-white dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600 dark:hover:bg-slate-700 sm:flex"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"
                        />
                    </svg>

                    <span>Search</span>
                    <kbd class="ml-4 rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] dark:border-slate-700 dark:bg-slate-900">
                        /
                    </kbd>
                </button>

                <!-- Notifications -->
                <button
                    type="button"
                    class="relative ml-2 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    aria-label="Notifications"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 17h5l-1.5-2V10a6.5 6.5 0 0 0-13 0v5L4 17h5m6 0a3 3 0 0 1-6 0"
                        />
                    </svg>

                    <span class="absolute right-2 top-2 h-1.5 w-1.5 rounded-full bg-red-500" />
                </button>

                <!-- User -->
                <div class="ml-2 hidden h-8 w-px bg-slate-200 dark:bg-slate-700 sm:block" />

                <div class="relative ml-3">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg p-1.5 transition hover:bg-slate-100 dark:hover:bg-slate-800"
                        :aria-expanded="accountOpen"
                        aria-haspopup="menu"
                        aria-label="Buka menu akun"
                        @click="accountOpen = !accountOpen"
                    >
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white dark:bg-slate-700">
                            {{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                        </div>

                        <div class="hidden text-left lg:block">
                            <div class="max-w-32 truncate text-xs font-semibold text-slate-800 dark:text-slate-100">
                                {{ page.props.auth?.user?.name || 'User' }}
                            </div>
                        </div>

                        <svg class="h-4 w-4 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                    </button>

                    <div
                        v-if="accountOpen"
                        class="absolute right-0 top-11 z-50 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-800"
                        role="menu"
                    >
                        <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
                            <div class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ page.props.auth?.user?.name || 'User' }}
                            </div>
                            <div class="truncate text-xs text-slate-400">
                                {{ page.props.auth?.user?.email || '' }}
                            </div>
                        </div>

                        <button
                            type="button"
                            role="menuitem"
                            class="mt-2 flex w-full items-center rounded-lg px-3 py-2 text-left text-sm text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white disabled:opacity-50"
                            :disabled="logout.processing"
                            @click="submitLogout"
                        >
                            {{ logout.processing ? 'Keluar...' : 'Logout' }}
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main
                class="w-full min-w-0 bg-transparent p-4 sm:p-6 lg:p-8"
                :aria-busy="isNavigating"
            >
                <div
                    v-if="isNavigating"
                    class="fixed inset-x-0 top-0 z-[100] h-0.5 overflow-hidden bg-transparent lg:left-20"
                    aria-label="Memuat konten"
                    role="status"
                >
                    <div class="h-full w-1/3 animate-[loading-bar_1.2s_ease-in-out_infinite] rounded-full bg-blue-500" />
                </div>

                <div
                    class="relative"
                    :class="isNavigating ? 'pointer-events-none opacity-60 transition-opacity' : ''"
                >
                    <slot />

                    <div
                        v-if="isNavigating"
                        class="absolute inset-0 z-10 flex items-start justify-center bg-white/20 pt-16 backdrop-blur-[1px] dark:bg-black/20"
                        aria-hidden="true"
                    >
                        <div class="flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-lg dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            <i class="pi pi-spin pi-spinner text-blue-500" />
                            <span>Memuat...</span>
                        </div>
                    </div>
                </div>
            </main>
        </SidebarMain>
        </SidebarLayout>
    </div>
</template>