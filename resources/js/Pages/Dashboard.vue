<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
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
const dateValue = (date: Date | null) => date ? date.toISOString().slice(0, 10) : undefined
function applyDateFilter() {
    router.get('/', { from: dateValue(from.value), to: dateValue(to.value) }, { preserveState: true, preserveScroll: true })
}
const cards = [
    ['Total Penjualan / Gross Sales', 'gross_sales', 'gross_order_count', 'info'],
    ['Net Sales', 'net_sales', 'net_order_count', 'success'],
    ['Pesanan Settled', 'settled_sales', 'settled_order_count', 'info'],
    ['Pesanan Unsettled', 'pending_sales', 'pending_order_count', 'warn'],
    ['Laba Bersih Settled', 'settled_profit', null, 'success'],
    ['Laba Bersih Unsettled', 'pending_profit', null, 'warn'],
    ['Total Laba Bersih', 'total_profit', null, 'info'],
    ['Valid Tanpa No. Resi', 'valid_without_tracking_sales', 'valid_without_tracking', 'warn'],
    ['Total Nilai Pembatalan', 'cancelled_sales', 'cancelled_order_count', 'warn'],
] as const
</script>

<template>
    <Head title="Dashboard" />
    <div class="flex flex-col gap-6">
        <div><Tag value="OVERVIEW" severity="secondary" /><h1 class="mt-2 text-3xl font-bold">Dashboard</h1><p class="mt-2 text-color-secondary">Selamat datang<span v-if="page.props.auth?.user?.name">, {{ page.props.auth.user.name }}</span>.</p></div>
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
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="([label, value, count, severity]) in cards" :key="value">
                <template #title><span class="text-sm">{{ label }}</span></template>
                <template #content><div class="text-2xl font-bold">{{ formatNominal(page.props.stats[value]) }}</div><small v-if="count" class="text-color-secondary">{{ page.props.stats[count] }} order</small><Tag v-else class="mt-2" :severity="severity" value="Ringkasan" /></template>
            </Card>
        </div>
    </div>
</template>
