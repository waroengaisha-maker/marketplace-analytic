<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

type MenuItem = {
    label: string
    href: string
    icon?: string
}

const props = withDefaults(defineProps<{
    items?: MenuItem[]
}>(), {
    items: () => [
        { label: 'Dashboard', href: '/', icon: 'pi pi-home' },
        { label: 'Orders', href: '/orders', icon: 'pi pi-shopping-cart' },
        { label: 'Analytics', href: '/analytics/profitability', icon: 'pi pi-chart-line' },
    ],
})

const page = usePage()

const isActive = (href: string) => {
    if (href === '/') {
        return page.url === '/'
    }

    return page.url === href || page.url.startsWith(`${href}/`)
}

const visibleItems = computed(() => props.items)
</script>

<template>
    <nav
        aria-label="Page menu"
        class="mb-5 flex min-w-0 items-center gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-950"
    >
        <Link
            v-for="item in visibleItems"
            :key="item.href"
            :href="item.href"
            class="inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium no-underline transition-colors"
            :class="isActive(item.href)
                ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100'"
        >
            <i v-if="item.icon" :class="item.icon" aria-hidden="true" />
            <span>{{ item.label }}</span>
        </Link>
    </nav>
</template>
