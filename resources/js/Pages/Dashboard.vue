<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { formatNominal } from '@/utils/formatters'
import { buildAnalyticsExportFilename } from '@/utils/exportFilename'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'

type User = { name: string; email: string }
type PageProps = {
    auth?: { user?: User | null }
    stats: Record<string, number>
    dateRange: { min: string | null; max: string | null }
    filters: { from: string | null; to: string | null }
    rows: Record<string, unknown>[]
    hasAppliedFilter: boolean
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
function resetDateFilter() {
    from.value = null
    to.value = null
    router.get('/', {}, { preserveState: true, preserveScroll: true })
}
const exportColumns = [
    ['settlement_status', 'Status'], ['order_number', 'No. Pesanan'], ['order_product_name', 'Nama Produk'],
    ['net_quantity', 'Jumlah Bersih'], ['discounted_price', 'Harga (@)'], ['quantity', 'Jumlah'], ['returned_quantity', 'Retur'],
    ['order_subtotal', 'Subtotal'], ['platform_fee', 'Biaya Administrasi'], ['admin_fee_percent', 'Admin (%)'],
    ['free_shipping_xtra_fee', 'Gratis Ongkir'], ['free_shipping_xtra_fee_percent', 'Gratis Ongkir (%)'],
    ['promo_xtra_service_fee', 'Promo XTRA'], ['promo_xtra_fee_percent', 'Promo XTRA (%)'],
    ['fee_subtotal', 'Subtotal Biaya'], ['fee_subtotal_percent', 'Subtotal Biaya (%)'],
    ['order_processing_fee', 'Biaya Proses'], ['total_fee', 'Total Biaya'], ['tax', 'Pajak'],
    ['penghasilan', 'Penghasilan'], ['hpp', 'HPP'], ['laba', 'Laba'],
] as const
function numericValue(row: Record<string, unknown>, field: string) {
    const value = Number(row[field] ?? 0)
    return Number.isFinite(value) ? value : 0
}
function orderCategory(row: Record<string, unknown>) {
    if (String(row.order_status ?? '').trim().toLowerCase() === 'batal') return 'Batal'
    if (String(row.tracking_number ?? '').trim() === '') return 'Tidak Valid'
    return numericValue(row, 'total_income') > 0 ? 'Settled' : 'Unsettled'
}
function exportValue(row: Record<string, unknown>, field: string) {
    if (field === 'settlement_status') return orderCategory(row)
    if (field === 'order_product_name') {
        const name = String(row[field] ?? '')
        const variation = String(row.order_variation_name ?? '').trim()
        return variation ? `${name} - ${variation}` : name
    }
    return row[field] ?? ''
}
function buildProductSummary(rows: Record<string, unknown>[]) {
    const summary = new Map<string, Record<string, number | string>>()
    rows.forEach((row) => {
        const productName = String(exportValue(row, 'order_product_name'))
        const unitPrice = numericValue(row, 'discounted_price')
        const key = `${productName}\u0000${unitPrice}`
        const current = summary.get(key) ?? { product_name: productName, unit_price: unitPrice }
        ;['quantity', 'returned_quantity', 'net_quantity', 'order_subtotal', 'platform_fee', 'free_shipping_xtra_fee', 'promo_xtra_service_fee', 'fee_subtotal', 'order_processing_fee', 'total_fee', 'tax', 'penghasilan', 'hpp', 'laba'].forEach((field) => {
            current[field] = Number(current[field] ?? 0) + numericValue(row, field)
        })
        summary.set(key, current)
    })
    return Array.from(summary.values())
}
async function exportExcel() {
    const XLSX = await import('xlsx')
    const rows = [...page.props.rows].sort((first, second) =>
        String(exportValue(first, 'order_product_name')).localeCompare(String(exportValue(second, 'order_product_name')), 'id', { sensitivity: 'base' }) ||
        numericValue(first, 'discounted_price') - numericValue(second, 'discounted_price'),
    )
    const data = rows.map((row) => Object.fromEntries(exportColumns.map(([field, header]) => [header, exportValue(row, field)])))
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(Object.entries(page.props.stats).map(([key, value]) => ({ Metrik: key, Nilai: value }))), 'Dashboard')
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(data), 'Rekonsiliasi')
    const summaryRows = buildProductSummary(rows).map((row) => ({
        'Nama Produk': row.product_name, 'Jumlah Bersih': row.net_quantity, 'Harga (@)': row.unit_price,
        'Jumlah': row.quantity, 'Retur': row.returned_quantity, 'Subtotal': row.order_subtotal,
        'Biaya Administrasi': row.platform_fee, 'Gratis Ongkir': row.free_shipping_xtra_fee,
        'Promo XTRA': row.promo_xtra_service_fee, 'Subtotal Biaya': row.fee_subtotal,
        'Biaya Proses': row.order_processing_fee, 'Total Biaya': row.total_fee, 'Pajak': row.tax,
        'Penghasilan': row.penghasilan, 'HPP': row.hpp, 'Laba': row.laba,
    }))
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(summaryRows), 'Rekonsiliasi Rekap Produk')
    XLSX.writeFile(workbook, buildAnalyticsExportFilename(page.props.filters.from, page.props.filters.to))
}
const cards = [
    ['Total Penjualan / Gross Sales', 'gross_sales', 'gross_order_count', 'info'],
    ['Pesanan Settled', 'settled_sales', 'settled_order_count', 'success'],
    ['Pesanan Unsettled', 'pending_sales', 'pending_order_count', 'warn'],
    ['Penjualan Valid', 'net_sales', 'net_order_count', 'info'],
    ['Total Biaya', 'total_fee', null, 'info'],
    ['Total Pajak', 'total_tax', null, 'info'],
    ['Total Laba Kotor', 'gross_profit', null, 'success'],
    ['Total HPP', 'total_hpp', null, 'secondary'],
    ['Laba Bersih', 'net_profit', null, 'success'],
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
                <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-filter text-color-secondary" aria-hidden="true"></i>
                        <span class="text-sm font-semibold">Filter data</span>
                    </div>
                    <span class="text-xs text-color-secondary">Gunakan filter untuk mempersempit ringkasan dashboard</span>
                </div>
                <DateRangeFilter
                    v-model:from="from"
                    v-model:to="to"
                    id-prefix="dashboard"
                    :min-date="page.props.dateRange.min ? new Date(`${page.props.dateRange.min}T00:00:00`) : undefined"
                    :max-date="page.props.dateRange.max ? new Date(`${page.props.dateRange.max}T00:00:00`) : undefined"
                />
                <div class="flex flex-wrap justify-start gap-2">
                    <Button label="Terapkan" icon="pi pi-filter" class="h-11 w-full sm:w-auto" @click="applyDateFilter" />
                    <Button label="Reset" icon="pi pi-refresh" severity="secondary" outlined class="h-11 w-full sm:w-auto" @click="resetDateFilter" />
                </div>
                <div class="flex flex-col gap-2 border-t border-surface pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <small class="text-xs text-color-secondary">Periode berdasarkan tanggal order dibuat.</small>
                    <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                        <Button label="Export Excel" icon="pi pi-file-excel" severity="secondary" outlined class="w-full sm:w-auto" :disabled="page.props.rows.length === 0" @click="exportExcel" />
                    </div>
                </div>
                </div>
            </template>
        </Card>
        <div v-if="page.props.hasAppliedFilter" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="([label, value, count]) in cards" :key="value" class="[&_.p-card-body]:p-3">
                <template #content>
                    <p class="text-xs font-semibold text-color-secondary">{{ label }}</p>
                    <p class="mt-1 text-lg font-bold">{{ formatNominal(page.props.stats[value]) }}</p>
                    <small v-if="count" class="text-xs text-color-secondary">{{ page.props.stats[count] }} order</small>
                </template>
            </Card>
        </div>
        <Card v-else>
            <template #content>
                <div class="py-8 text-center text-color-secondary">
                    Pilih periode tanggal, lalu klik <strong>Terapkan</strong> untuk menampilkan data dashboard.
                </div>
            </template>
        </Card>
    </div>
</template>
