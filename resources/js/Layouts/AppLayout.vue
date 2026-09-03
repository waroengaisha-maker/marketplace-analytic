<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const sidebarOpen = ref(false)

const navigation = [
    {
        label: 'MAIN',
        items: [
            { name: 'Dashboard', href: '/', icon: '▦' },
        ],
    },
    {
        label: 'OPERATIONS',
        items: [
            { name: 'Orders', href: '/orders', icon: '□' },
            { name: 'Returns', href: '/returns', icon: '↩' },
            { name: 'Customers', href: '/customers', icon: '♙' },
        ],
    },
    {
        label: 'FINANCE',
        items: [
            { name: 'Income', href: '/finance/income', icon: 'Rp' },
            { name: 'Reconciliation', href: '/finance/reconciliation', icon: '≡' },
            { name: 'Profit', href: '/finance/profit', icon: '↗' },
        ],
    },
    {
        label: 'PRODUCTS',
        items: [
            { name: 'Products', href: '/products', icon: '◇' },
            { name: 'HPP', href: '/products/hpp', icon: '◈' },
            { name: 'HPP Mapping', href: '/products/hpp-mapping', icon: '⇄' },
        ],
    },
    {
        label: 'IMPORTS',
        items: [
            { name: 'Import History', href: '/imports', icon: '▤' },
            { name: 'Upload Files', href: '/imports/upload', icon: '↑' },
        ],
    },
    {
        label: 'ANALYTICS',
        items: [
            { name: 'Sales', href: '/analytics/sales', icon: '▥' },
            { name: 'Products', href: '/analytics/products', icon: '◫' },
            { name: 'Customers', href: '/analytics/customers', icon: '♙' },
            { name: 'Profitability', href: '/analytics/profitability', icon: '◎' },
        ],
    },
    {
        label: 'SETTINGS',
        items: [
            { name: 'Shop', href: '/settings/shop', icon: '⚙' },
            { name: 'Users', href: '/settings/users', icon: '♙' },
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
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">

        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden"
            @click="closeSidebar"
        />

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- Logo -->
            <div class="flex h-16 shrink-0 items-center border-b border-slate-200 px-5">
                <Link
                    href="/"
                    class="flex items-center gap-3"
                    @click="closeSidebar"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-900 text-sm font-bold text-white">
                        M
                    </div>

                    <div>
                        <div class="text-sm font-bold tracking-tight">
                            Marketplace
                        </div>
                        <div class="text-[10px] font-medium uppercase tracking-wider text-slate-400">
                            Analytics
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <div
                    v-for="section in navigation"
                    :key="section.label"
                    class="mb-6"
                >
                    <div class="mb-2 px-3 text-[10px] font-bold tracking-widest text-slate-400">
                        {{ section.label }}
                    </div>

                    <div class="space-y-0.5">
                        <Link
                            v-for="item in section.items"
                            :key="item.href"
                            :href="item.href"
                            class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="isActive(item.href)
                                ? 'bg-slate-100 text-slate-900'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'"
                            @click="closeSidebar"
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center text-xs"
                                :class="isActive(item.href)
                                    ? 'text-slate-900'
                                    : 'text-slate-400 group-hover:text-slate-600'"
                            >
                                {{ item.icon }}
                            </span>

                            <span>{{ item.name }}</span>

                            <span
                                v-if="isActive(item.href)"
                                class="ml-auto h-1.5 w-1.5 rounded-full bg-slate-900"
                            />
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- Sidebar footer -->
            <div class="border-t border-slate-200 p-3">
                <div class="rounded-lg bg-slate-50 p-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-600">
                            {{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                        </div>

                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-800">
                                {{ page.props.auth?.user?.name || 'User' }}
                            </div>
                            <div class="truncate text-xs text-slate-400">
                                {{ page.props.auth?.user?.email || '' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main -->
        <div class="lg:pl-64">

            <!-- Topbar -->
            <header class="sticky top-0 z-30 flex h-16 items-center border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">

                <button
                    type="button"
                    class="mr-3 rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
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
                    <div class="hidden text-sm font-medium text-slate-400 sm:block">
                        Marketplace Analytics
                    </div>
                </div>

                <!-- Search -->
                <button
                    type="button"
                    class="hidden items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-400 transition hover:border-slate-300 hover:bg-white sm:flex"
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
                    <kbd class="ml-4 rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px]">
                        /
                    </kbd>
                </button>

                <!-- Notifications -->
                <button
                    type="button"
                    class="relative ml-2 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
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
                <div class="ml-2 hidden h-8 w-px bg-slate-200 sm:block" />

                <div class="ml-3 flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">
                        {{ page.props.auth?.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>

                    <div class="hidden text-left lg:block">
                        <div class="max-w-32 truncate text-xs font-semibold text-slate-800">
                            {{ page.props.auth?.user?.name || 'User' }}
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="mx-auto max-w-[1600px] p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>