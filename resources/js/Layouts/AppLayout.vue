<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import ToggleSwitch from 'primevue/toggleswitch'

const page = usePage()
const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)
const accountOpen = ref(false)
const darkMode = ref(false)
const logout = useForm({})

const applyDarkMode = (enabled: boolean) => {
    document.documentElement.classList.toggle('dark', enabled)
    localStorage.setItem('marketplace-dark-mode', enabled ? 'true' : 'false')
}

onMounted(() => {
    darkMode.value = localStorage.getItem('marketplace-dark-mode') === 'true'
    sidebarCollapsed.value = localStorage.getItem('marketplace-sidebar-collapsed') === 'true'
    if (window.innerWidth < 1024) {
        sidebarCollapsed.value = false
    }
    applyDarkMode(darkMode.value)
})

watch(darkMode, (enabled) => {
    applyDarkMode(enabled)
})

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem('marketplace-sidebar-collapsed', sidebarCollapsed.value ? 'true' : 'false')
}

const navigation = [
    {
        label: 'MAIN',
        items: [
            { name: 'Dashboard', href: '/', icon: '▦', color: 'text-blue-500' },
        ],
    },
    {
        label: 'OPERATIONS',
        items: [
            { name: 'Orders', href: '/orders', icon: '□', color: 'text-violet-500' },
            { name: 'Returns', href: '/returns', icon: '↩', color: 'text-rose-500' },
            { name: 'Customers', href: '/customers', icon: '♙', color: 'text-cyan-500' },
        ],
    },
    {
        label: 'FINANCE',
        items: [
            { name: 'Income', href: '/finance/income', icon: 'Rp', color: 'text-emerald-500' },
            { name: 'Reconciliation', href: '/finance/reconciliation', icon: '≡', color: 'text-amber-500' },
            { name: 'Profit', href: '/finance/profit', icon: '↗', color: 'text-green-500' },
        ],
    },
    {
        label: 'PRODUCTS',
        items: [
            { name: 'Products', href: '/products', icon: '◇', color: 'text-indigo-500' },
            { name: 'HPP', href: '/products/hpp', icon: '◈', color: 'text-fuchsia-500' },
            { name: 'HPP Mapping', href: '/products/hpp-mapping', icon: '⇄', color: 'text-orange-500' },
        ],
    },
    {
        label: 'IMPORTS',
        items: [
            { name: 'Import History', href: '/imports', icon: '▤', color: 'text-sky-500' },
            { name: 'Upload Files', href: '/imports/upload', icon: '↑', color: 'text-lime-500' },
        ],
    },
    {
        label: 'ANALYTICS',
        items: [
            { name: 'Sales', href: '/analytics/sales', icon: '▥', color: 'text-pink-500' },
            { name: 'Products', href: '/analytics/products', icon: '◫', color: 'text-teal-500' },
            { name: 'Customers', href: '/analytics/customers', icon: '♙', color: 'text-purple-500' },
            { name: 'Profitability', href: '/analytics/profitability', icon: '◎', color: 'text-yellow-500' },
        ],
    },
    {
        label: 'SETTINGS',
        items: [
            { name: 'Shop', href: '/settings/shop', icon: '⚙', color: 'text-slate-500' },
            { name: 'Users', href: '/settings/users', icon: '♙', color: 'text-blue-400' },
        ],
    },
]

const currentUrl = computed(() => page.url)

const isActive = (href: string) => {
    if (href === '/') {
        return currentUrl.value === '/'
    }

    return currentUrl.value === href ||
        currentUrl.value.startsWith(`${href}/`)
}

const closeSidebar = () => {
    sidebarOpen.value = false
}

const submitLogout = () => {
    logout.post('/logout')
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-black dark:text-slate-100">

        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-slate-900/40 dark:bg-black/60 lg:hidden"
            @click="closeSidebar"
        />

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-[width,transform] duration-200 dark:border-slate-800 dark:bg-black lg:translate-x-0"
            :class="[
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarCollapsed ? 'lg:w-20' : 'lg:w-64',
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 px-4 dark:border-slate-800">
                <Link
                    href="/"
                    class="flex min-w-0 items-center gap-3"
                    @click="closeSidebar"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-sm font-bold text-white dark:bg-slate-700">
                        M
                    </div>

                    <div v-if="!sidebarCollapsed" class="min-w-0">
                        <div class="text-sm font-bold tracking-tight">
                            Marketplace
                        </div>
                        <div class="text-[10px] font-medium uppercase tracking-wider text-slate-400">
                            Analytics
                        </div>
                    </div>
                </Link>

                <button
                    type="button"
                    class="hidden shrink-0 rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white lg:block"
                    :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    @click="toggleSidebar"
                >
                    <i
                        class="pi text-sm"
                        :class="sidebarCollapsed ? 'pi-angle-right' : 'pi-angle-left'"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto px-2 py-4 sm:px-3">
                <div
                    v-for="section in navigation"
                    :key="section.label"
                    class="mb-6"
                >
                    <div v-if="!sidebarCollapsed" class="mb-2 px-3 text-[10px] font-bold tracking-widest text-slate-400">
                        {{ section.label }}
                    </div>

                    <div class="space-y-0.5">
                        <Link
                            v-for="item in section.items"
                            :key="item.href"
                            :href="item.href"
                            v-tooltip.right="sidebarCollapsed ? item.name : null"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition lg:justify-start"
                            :class="[
                                sidebarCollapsed ? 'lg:justify-center lg:px-2' : '',
                                isActive(item.href)
                                    ? 'bg-slate-100 text-slate-900'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                            ]"
                            @click="closeSidebar"
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center text-xs"
                                :class="item.color"
                            >
                                {{ item.icon }}
                            </span>

                            <span v-if="!sidebarCollapsed">{{ item.name }}</span>

                            <span
                                v-if="isActive(item.href) && !sidebarCollapsed"
                                class="ml-auto h-1.5 w-1.5 rounded-full bg-slate-900"
                            />
                        </Link>
                    </div>
                </div>
            </nav>

        </aside>

        <!-- Main -->
        <div
            class="min-w-0"
            :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'"
        >

            <!-- Topbar -->
            <header class="sticky top-0 z-30 flex h-16 min-w-0 items-center border-b border-slate-200 bg-white/95 px-3 backdrop-blur dark:border-slate-800 dark:bg-black/95 sm:px-6">

                <button
                    type="button"
                    class="mr-3 rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 lg:hidden"
                    aria-label="Open navigation"
                    @click="sidebarOpen = true"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>

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
            <main class="min-h-[calc(100vh-4rem)] w-full min-w-0 bg-transparent p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>