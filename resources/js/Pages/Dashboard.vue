<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import Card from 'primevue/card'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { formatNominal } from '@/utils/formatters'

type User = { name: string; email: string }
type PageProps = {
    auth?: { user?: User | null }
    stats: Record<string, number>
    dateRange: { min: string | null; max: string | null }
    filters: { from: string | null; to: string | null }
}
const page = usePage<PageProps>()
const from = ref(page.props.filters.from ? new Date(`${page.props.filters.from}T00:00:00`) : null)
const to = ref(page.props.filters.to ? new Date(`${page.props.filters.to}T00:00:00`) : null)
const dateValue = (date: Date | null) => {
    if (!date) {
        return undefined
    }

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}
function applyDateFilter() {
    router.get('/', { from: dateValue(from.value), to: dateValue(to.value) }, { preserveState: true, preserveScroll: true })
}
const cards = [
    ['Total Penjualan / Gross Sales', 'gross_sales', 'gross_order_count', 'info'],
    ['Pesanan Settled', 'settled_sales', 'settled_order_count', 'success'],
    ['Pesanan Unsettled', 'pending_sales', 'pending_order_count', 'warn'],
    ['Penjualan Valid', 'net_sales', 'net_order_count', 'info'],
    ['Total Laba', 'total_profit', null, 'success'],
    ['Batal', 'cancelled_sales', 'cancelled_order_count', 'danger'],
    ['Tidak Valid', 'valid_without_tracking_sales', 'valid_without_tracking', 'secondary'],
] as const
</script>

<template>
    <Head title="Dashboard" />
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><Tag value="OVERVIEW" severity="secondary" /><h1 class="mt-2 text-3xl font-bold">Dashboard</h1><p class="mt-2 text-color-secondary">Selamat datang<span v-if="page.props.auth?.user?.name">, {{ page.props.auth.user.name }}</span>.</p></div>
            <Link href="/finance/reconciliation" class="no-underline">
                <Button label="Lihat Detail Rekonsiliasi" icon="pi pi-list-check" outlined />
            </Link>
        </div>
        <Card>
            <template #content>
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex flex-col gap-2"><label for="from">Tanggal Mulai</label><DatePicker id="from" v-model="from" date-format="yy-mm-dd" show-icon :min-date="page.props.dateRange.min ? new Date(`${page.props.dateRange.min}T00:00:00`) : undefined" :max-date="page.props.dateRange.max ? new Date(`${page.props.dateRange.max}T00:00:00`) : undefined" /></div>
                    <div class="flex flex-col gap-2"><label for="to">Tanggal Akhir</label><DatePicker id="to" v-model="to" date-format="yy-mm-dd" show-icon :min-date="page.props.dateRange.min ? new Date(`${page.props.dateRange.min}T00:00:00`) : undefined" :max-date="page.props.dateRange.max ? new Date(`${page.props.dateRange.max}T00:00:00`) : undefined" /></div>
                    <Button label="Terapkan" icon="pi pi-filter" @click="applyDateFilter" />
                </div>
                <small class="text-color-secondary block mt-3">Periode berdasarkan tanggal order dibuat.</small>
            </template>
        </Card>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="([label, value, count]) in cards" :key="value" class="[&_.p-card-body]:p-3">
                <template #content>
                    <p class="text-xs font-semibold text-color-secondary">{{ label }}</p>
                    <p class="mt-1 text-lg font-bold">{{ formatNominal(page.props.stats[value]) }}</p>
                    <small v-if="count" class="text-xs text-color-secondary">{{ page.props.stats[count] }} order</small>
                </template>
            </Card>
        </div>
    </div>
</template>
