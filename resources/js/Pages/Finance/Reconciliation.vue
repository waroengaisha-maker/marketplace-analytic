<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import MultiSelect from 'primevue/multiselect'
import Toolbar from 'primevue/toolbar'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { formatNominal } from '@/utils/formatters'
import { buildAnalyticsExportFilename } from '@/utils/exportFilename'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
import { FilterMatchMode } from '@primevue/core/api'

type Row = Record<string, unknown>
type DataTableInstance = { exportCSV: () => void; filteredValue?: Row[] }
type Pagination = { current_page: number; per_page: number; total: number; last_page: number } | null
const props = defineProps<{ rows: Row[]; summaryRows: Row[]; pagination: Pagination; hasAppliedFilter: boolean; appliedFrom?: string | null; appliedTo?: string | null }>()
const hasAppliedFilter = ref(props.hasAppliedFilter)
const dataTable = ref<DataTableInstance | null>(null)
const isFullscreen = ref(false)
const parseDate = (value?: string | null) => value ? new Date(`${value}T00:00:00`) : null
const fromDate = ref<Date | null>(parseDate(props.appliedFrom))
const toDate = ref<Date | null>(parseDate(props.appliedTo))
const appliedFromDate = ref<Date | null>(parseDate(props.appliedFrom))
const appliedToDate = ref<Date | null>(parseDate(props.appliedTo))
const selectedOrderStatuses = ref<string[]>(['Settled', 'Unsettled'])
const selectedRows = ref<Row[]>([])
const multiSortMeta = ref<{ field: string; order: number }[]>([])
const clearButtonClass = 'absolute right-1 top-1/2 z-10 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border-0 bg-transparent p-0 text-color-secondary hover:bg-emphasis hover:text-color'
const money = ['discounted_price', 'order_subtotal', 'platform_fee', 'free_shipping_xtra_fee', 'promo_xtra_service_fee', 'fee_subtotal', 'order_processing_fee', 'total_fee', 'tax', 'penghasilan', 'hpp', 'laba']
const formulaTooltips: Record<string, string> = {
    net_quantity: 'Jumlah Bersih = Jumlah - Retur',
    order_subtotal: 'Subtotal = Harga setelah diskon x (Jumlah - Retur)',
    admin_fee_percent: 'Admin (%) = Biaya Administrasi / Subtotal x 100',
    free_shipping_xtra_fee_percent: 'Gratis Ongkir (%) = Gratis Ongkir / Subtotal x 100',
    promo_xtra_fee_percent: 'Promo XTRA (%) = Promo XTRA / Subtotal x 100',
    fee_subtotal: 'Subtotal Biaya = Biaya Administrasi + Gratis Ongkir + Promo XTRA',
    fee_subtotal_percent: 'Subtotal Biaya (%) = Subtotal Biaya / Subtotal x 100',
    total_fee: 'Total Biaya = Subtotal Biaya + Biaya Proses',
    penghasilan: 'Penghasilan = Subtotal + (Total Biaya + Pajak)',
    hpp: 'HPP saat ini = 0',
    laba: 'Laba = Penghasilan - HPP',
}
const columns = [
    ['order_number', 'No. Pesanan'], ['order_product_name', 'Nama Produk'],
    ['net_quantity', 'Jumlah Bersih'], ['discounted_price', 'Harga (@)'], ['quantity', 'Jumlah'], ['returned_quantity', 'Retur'],
    ['order_subtotal', 'Subtotal'], ['platform_fee', 'Biaya Administrasi'], ['admin_fee_percent', 'Admin (%)'],
    ['free_shipping_xtra_fee', 'Gratis Ongkir'], ['free_shipping_xtra_fee_percent', 'Gratis Ongkir (%)'],
    ['promo_xtra_service_fee', 'Promo XTRA'], ['promo_xtra_fee_percent', 'Promo XTRA (%)'],
    ['fee_subtotal', 'Subtotal Biaya'], ['fee_subtotal_percent', 'Subtotal Biaya (%)'],
    ['order_processing_fee', 'Biaya Proses'], ['total_fee', 'Total Biaya'], ['tax', 'Pajak'],
    ['penghasilan', 'Penghasilan'], ['hpp', 'HPP'], ['laba', 'Laba'],
] as const
const allColumns = [
    ['settlement_status', 'Status'],
    ...columns,
] as const
const selectedColumns = ref([...allColumns])
const filters = ref<Record<string, { value: string | null; matchMode: string }>>(
    {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        ...Object.fromEntries(allColumns.map(([field]) => [field, { value: null, matchMode: FilterMatchMode.CONTAINS }])),
    },
)
const tableFilters = Object.fromEntries(
    allColumns.map(([field]) => [field, { value: null, matchMode: FilterMatchMode.CONTAINS }]),
)
const visibleColumns = computed(() => selectedColumns.value)
const orderStatusOptions = ['Settled', 'Unsettled', 'Batal', 'Tidak Valid']
function applyDateFilter() {
    appliedFromDate.value = fromDate.value
    appliedToDate.value = toDate.value
    hasAppliedFilter.value = true
    loadData({ page: 1 })
}
function resetDateFilter() {
    fromDate.value = null
    toDate.value = null
    appliedFromDate.value = null
    appliedToDate.value = null
    hasAppliedFilter.value = false
    router.get('/finance/reconciliation', {}, { preserveScroll: true })
}
function localDateKey(date: Date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}
function severity(status: string) { return status === 'Settled' ? 'success' : status === 'Unsettled' ? 'warn' : 'danger' }
function orderCategory(row: Row) {
    const rawStatus = String(row.order_status ?? '').trim().toLowerCase()
    const hasTracking = String(row.tracking_number ?? '').trim() !== ''
    const hasIncome = numericValue(row, 'total_income') > 0

    if (rawStatus === 'batal') {
        return 'Batal'
    }

    if (!hasTracking) {
        return 'Tidak Valid'
    }

    return hasIncome ? 'Settled' : 'Unsettled'
}
function clearColumnFilter(field: string) {
    filters.value[field].value = null
    onFilter()
}
function exportValue(row: Row, field: string) {
    if (field === 'settlement_status') {
        return orderCategory(row)
    }

    if (field === 'order_product_name') {
        const productName = String(row[field] ?? '')
        const variationName = String(row.order_variation_name ?? '').trim()

        return variationName ? `${productName} - ${variationName}` : productName
    }

    return row[field] ?? ''
}
function searchableValue(row: Row, field: string) {
    return String(exportValue(row, field) ?? '').toLocaleLowerCase('id-ID')
}
const filteredRows = computed(() => props.rows || [])
const summaryFilteredRows = computed(() => (props.summaryRows || []).filter((row) =>
    selectedOrderStatuses.value.length === 0 || selectedOrderStatuses.value.includes(orderCategory(row)),
))
function numericValue(row: Row, field: string) {
    const value = Number(row[field] ?? 0)

    return Number.isFinite(value) ? value : 0
}
function sumRows(rows: Row[], field: string) {
    return rows.reduce((total, row) => total + numericValue(row, field), 0)
}
function countOrders(rows: Row[]) {
    return new Set(rows.map((row) => String(row.order_number ?? '').trim()).filter(Boolean)).size
}
const summaryMetrics = [
    ['total_fee', 'Total Biaya'],
    ['tax', 'Total Pajak'],
    ['penghasilan', 'Total Penghasilan'],
    ['hpp', 'Total HPP'],
    ['laba', 'Total Laba'],
] as const
function buildSummaryCards(rows: Row[], subtotalOnly = false) {
    const subtotal = sumRows(rows, 'order_subtotal')

    if (subtotalOnly) {
        return [
            { field: 'subtotal', label: 'Nilai Subtotal', value: subtotal, percentage: 100, type: 'money', orderCount: countOrders(rows) },
        ]
    }

    return [
        { field: 'subtotal', label: 'Nilai Subtotal', value: subtotal, percentage: 100, type: 'money', orderCount: countOrders(rows) },
        ...summaryMetrics.map(([field, label]) => ({
            field,
            label,
            value: sumRows(rows, field),
            percentage: subtotal ? sumRows(rows, field) / subtotal * 100 : 0,
            type: 'money',
            orderCount: countOrders(rows),
            breakdown: field === 'total_fee' ? [
                ['Admin', sumRows(rows, 'platform_fee')],
                ['Gratis Ongkir', sumRows(rows, 'free_shipping_xtra_fee')],
                ['Promo XTRA', sumRows(rows, 'promo_xtra_service_fee')],
                ['Biaya Proses', sumRows(rows, 'order_processing_fee')],
            ] : undefined,
        })),
    ]
}
const totalSummary = computed(() => ({
    count: summaryFilteredRows.value.length,
    cards: buildSummaryCards(summaryFilteredRows.value),
}))
const summaryGroups = computed(() => {
    const groups = [
        { label: 'Settled', severity: 'success', icon: 'pi pi-check-circle' },
        { label: 'Unsettled', severity: 'warn', icon: 'pi pi-clock' },
        { label: 'Batal', severity: 'danger', icon: 'pi pi-times-circle' },
        { label: 'Tidak Valid', severity: 'secondary', icon: 'pi pi-ban' },
    ]
    return groups.map((group) => {
        const rows = summaryFilteredRows.value.filter((row) => orderCategory(row) === group.label)

        return {
            ...group,
            count: rows.length,
            cards: buildSummaryCards(rows, ['Batal', 'Tidak Valid'].includes(group.label)),
        }
    })
})
function buildProductSummary(rows: Row[]) {
    const summary = new Map<string, Row>()

    rows.forEach((row) => {
        const productName = String(exportValue(row, 'order_product_name'))
        const unitPrice = numericValue(row, 'discounted_price')
        const key = `${productName}\u0000${unitPrice}`
        const existing = summary.get(key)

        if (existing) {
            ;['quantity', 'returned_quantity', 'net_quantity', 'order_subtotal', 'platform_fee', 'free_shipping_xtra_fee',
                'promo_xtra_service_fee', 'fee_subtotal', 'order_processing_fee', 'total_fee', 'tax', 'penghasilan', 'hpp', 'laba']
                .forEach((field) => {
                    existing[field] = numericValue(existing, field) + numericValue(row, field)
                })
        } else {
            summary.set(key, {
                product_name: productName,
                unit_price: unitPrice,
                quantity: numericValue(row, 'quantity'),
                returned_quantity: numericValue(row, 'returned_quantity'),
                net_quantity: numericValue(row, 'net_quantity'),
                order_subtotal: numericValue(row, 'order_subtotal'),
                platform_fee: numericValue(row, 'platform_fee'),
                free_shipping_xtra_fee: numericValue(row, 'free_shipping_xtra_fee'),
                promo_xtra_service_fee: numericValue(row, 'promo_xtra_service_fee'),
                fee_subtotal: numericValue(row, 'fee_subtotal'),
                order_processing_fee: numericValue(row, 'order_processing_fee'),
                total_fee: numericValue(row, 'total_fee'),
                tax: numericValue(row, 'tax'),
                penghasilan: numericValue(row, 'penghasilan'),
                hpp: numericValue(row, 'hpp'),
                laba: numericValue(row, 'laba'),
            })
        }
    })

    return Array.from(summary.values()).sort((first, second) =>
        String(first.product_name).localeCompare(String(second.product_name), 'id', { sensitivity: 'base' }) ||
        numericValue(first, 'unit_price') - numericValue(second, 'unit_price'),
    )
}
function exportCsv() { dataTable.value?.exportCSV() }
async function exportExcel() {
    const XLSX = await import('xlsx')
    const visibleFields = visibleColumns.value
    const exportRows = [...summaryFilteredRows.value].sort((first, second) =>
        String(exportValue(first, 'order_product_name')).localeCompare(String(exportValue(second, 'order_product_name')), 'id', { sensitivity: 'base' }) ||
        numericValue(first, 'discounted_price') - numericValue(second, 'discounted_price'),
    )
    const data = exportRows.map((row) => Object.fromEntries(
        visibleFields.map(([field, header]) => [header, exportValue(row, field)]),
    ))
    const worksheet = XLSX.utils.json_to_sheet(data)
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Reconciliation')
    const summaryRows = buildProductSummary(exportRows).map((row) => ({
        'Nama Produk': row.product_name,
        'Jumlah Bersih': row.net_quantity,
        'Harga (@)': row.unit_price,
        'Jumlah': row.quantity,
        'Retur': row.returned_quantity,
        'Subtotal': row.order_subtotal,
        'Biaya Administrasi': row.platform_fee,
        'Gratis Ongkir': row.free_shipping_xtra_fee,
        'Promo XTRA': row.promo_xtra_service_fee,
        'Subtotal Biaya': row.fee_subtotal,
        'Biaya Proses': row.order_processing_fee,
        'Total Biaya': row.total_fee,
        'Pajak': row.tax,
        'Penghasilan': row.penghasilan,
        'HPP': row.hpp,
        'Laba': row.laba,
    }))
    const summaryWorksheet = XLSX.utils.json_to_sheet(summaryRows)
    XLSX.utils.book_append_sheet(workbook, summaryWorksheet, 'Rekapan Produk')
    const fromLabel = appliedFromDate.value ? localDateKey(appliedFromDate.value) : 'awal'
    const toLabel = appliedToDate.value ? localDateKey(appliedToDate.value) : 'akhir'
    XLSX.writeFile(workbook, buildAnalyticsExportFilename(fromLabel, toLabel))
}
function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value
}
function loadData(overrides: Record<string, unknown> = {}) {
    if (!fromDate.value && !toDate.value && !hasAppliedFilter.value) {
        return
    }

    router.get('/finance/reconciliation', {
        from: fromDate.value ? localDateKey(fromDate.value) : undefined,
        to: toDate.value ? localDateKey(toDate.value) : undefined,
        search: filters.value.global.value || undefined,
        statuses: selectedOrderStatuses.value,
        column_filters: JSON.stringify(Object.fromEntries(
            allColumns.map(([field]) => [field, filters.value[field]?.value || null]),
        )),
        ...overrides,
    }, { preserveScroll: true, preserveState: true })
}
function onPage(event: { first: number; rows: number }) {
    loadData({ page: Math.floor(event.first / event.rows) + 1, per_page: event.rows })
}
function onSort(event: { sortField?: string; sortOrder?: number }) {
    loadData({ page: 1, multi_sort_meta: JSON.stringify(multiSortMeta.value) })
}
function onFilter() {
    loadData({ page: 1 })
}
</script>

<template>
    <Head title="Reconciliation" />
    <div class="flex flex-col gap-6">
        <div v-if="!isFullscreen"><h1 class="text-3xl font-bold">Reconciliation</h1><p class="mt-2 text-color-secondary">Detail Order dan Income dengan pencocokan aman.</p></div>
        <Card v-if="!isFullscreen">
            <template #content>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-filter text-color-secondary" aria-hidden="true"></i>
                            <span class="text-sm font-semibold">Filter data</span>
                        </div>
                        <span class="text-xs text-color-secondary">Gunakan filter untuk mempersempit hasil rekonsiliasi</span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-[minmax(15rem,1.3fr)_minmax(11rem,1fr)_minmax(11rem,1fr)_minmax(14rem,1.2fr)_minmax(12rem,1fr)]">
                        <DateRangeFilter
                            v-model:from="fromDate"
                            v-model:to="toDate"
                            id-prefix="reconciliation"
                            class="md:col-span-2 xl:col-span-2"
                        />
                        <div class="flex min-w-0 flex-col gap-1">
                            <label for="reconciliation-order-status" class="text-xs font-medium text-color-secondary">Status order</label>
                            <MultiSelect input-id="reconciliation-order-status" v-model="selectedOrderStatuses" :options="orderStatusOptions" placeholder="Pilih status" display="chip" filter show-clear class="h-11 w-full" @change="onFilter" />
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-start gap-2">
                        <Button label="Terapkan" icon="pi pi-filter" class="h-11 w-full sm:w-auto" @click="applyDateFilter" />
                        <Button label="Reset" icon="pi pi-refresh" severity="secondary" outlined class="h-11 w-full sm:w-auto" @click="resetDateFilter" />
                    </div>
                    <div class="flex flex-col gap-2 border-t border-surface pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-xs text-color-secondary">{{ (pagination?.total ?? 0).toLocaleString('id-ID') }} baris tersedia</span>
                        <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                            <Button label="Export Excel" icon="pi pi-file-excel" severity="secondary" outlined class="w-full sm:w-auto" :disabled="filteredRows.length === 0" @click="exportExcel" />
                            <Button label="Export CSV" icon="pi pi-download" severity="secondary" outlined class="w-full sm:w-auto" :disabled="filteredRows.length === 0" @click="exportCsv" />
                            <Button :label="isFullscreen ? 'Keluar Fullscreen' : 'Fullscreen'" :icon="isFullscreen ? 'pi pi-window-minimize' : 'pi pi-window-maximize'" severity="secondary" outlined class="w-full sm:w-auto" @click="toggleFullscreen" />
                        </div>
                    </div>
                </div>
            </template>
        </Card>
        <div v-if="!isFullscreen && hasAppliedFilter" class="flex flex-col gap-4">
            <section class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <Tag severity="info" value="Total Semua Status" icon="pi pi-chart-bar" />
                    <span class="text-sm text-color-secondary">{{ totalSummary.count.toLocaleString('id-ID') }} baris</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Card v-for="card in totalSummary.cards" :key="`total-${card.field}`" :class="card.field === 'total_fee' ? 'xl:col-span-2 [&_.p-card-body]:p-4' : '[&_.p-card-body]:p-3'">
                        <template #content>
                            <p class="text-xs font-semibold text-color-secondary">{{ card.label }}</p>
                            <p class="mt-1 text-lg font-bold">
                                {{ card.type === 'count' ? Number(card.value).toLocaleString('id-ID') : formatNominal(card.value) }}
                            </p>
                            <small v-if="card.field === 'subtotal'" class="text-xs text-color-secondary">{{ card.orderCount.toLocaleString('id-ID') }} order</small>
                            <div v-if="card.breakdown" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-surface pt-3 text-xs text-color-secondary sm:grid-cols-4">
                                <span v-for="[label, value] in card.breakdown" :key="label" class="flex min-w-0 flex-col gap-0.5">
                                    <span class="font-medium">{{ label }}</span>
                                    <span class="whitespace-nowrap font-semibold text-color">{{ formatNominal(value) }}</span>
                                </span>
                            </div>
                            <small v-if="card.type !== 'count'" class="text-xs text-color-secondary">{{ card.percentage.toFixed(2) }}% dari subtotal</small>
                        </template>
                    </Card>
                </div>
            </section>
            <section v-for="group in summaryGroups" :key="group.label" class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <Tag :severity="group.severity" :value="group.label" :icon="group.icon" />
                    <span class="text-sm text-color-secondary">{{ group.count.toLocaleString('id-ID') }} baris</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Card v-for="card in group.cards" :key="`${group.label}-${card.field}`" :class="card.field === 'total_fee' ? 'xl:col-span-2 [&_.p-card-body]:p-4' : '[&_.p-card-body]:p-3'">
                        <template #content>
                            <p class="text-xs font-semibold text-color-secondary">{{ card.label }}</p>
                            <p class="mt-1 text-lg font-bold">
                                {{ card.type === 'count' ? Number(card.value).toLocaleString('id-ID') : formatNominal(card.value) }}
                            </p>
                            <small v-if="card.field === 'subtotal'" class="text-xs text-color-secondary">{{ card.orderCount.toLocaleString('id-ID') }} order</small>
                            <div v-if="card.breakdown" class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-surface pt-3 text-xs text-color-secondary sm:grid-cols-4">
                                <span v-for="[label, value] in card.breakdown" :key="label" class="flex min-w-0 flex-col gap-0.5">
                                    <span class="font-medium">{{ label }}</span>
                                    <span class="whitespace-nowrap font-semibold text-color">{{ formatNominal(value) }}</span>
                                </span>
                            </div>
                            <small v-if="card.type !== 'count'" class="text-xs text-color-secondary">{{ card.percentage.toFixed(2) }}% dari subtotal</small>
                        </template>
                    </Card>
                </div>
            </section>
        </div>
        <Card v-else-if="!isFullscreen">
            <template #content>
                <div class="py-8 text-center text-color-secondary">
                    Pilih periode tanggal, lalu klik <strong>Terapkan</strong> untuk menampilkan data rekonsiliasi.
                </div>
            </template>
        </Card>
        <div
            v-if="hasAppliedFilter"
            class="relative min-w-0"
            :class="isFullscreen ? 'fixed inset-0 z-50 overflow-hidden bg-white p-3 dark:bg-black sm:p-4' : ''"
        >
            <div v-if="isFullscreen" class="flex h-10 items-center justify-end border-b border-surface pb-2">
                <Button
                    label="Keluar Fullscreen"
                    icon="pi pi-window-minimize"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="toggleFullscreen"
                />
            </div>
            <Toolbar class="mb-3 flex-wrap gap-3">
                <template #start>
                    <div class="relative w-full sm:w-[20rem] lg:w-[22rem]">
                        <i class="pi pi-search absolute left-3 top-1/2 z-10 -translate-y-1/2 text-color-secondary" aria-hidden="true"></i>
                        <InputText
                            id="reconciliation-global-filter"
                            v-model="filters.global.value"
                            aria-label="Filter semua kolom"
                            placeholder="Cari semua kolom..."
                            class="h-11 w-full pl-10 pr-10"
                            @keyup.enter="onFilter"
                        />
                        <button
                            v-if="filters.global.value"
                            type="button"
                            aria-label="Hapus pencarian"
                            :class="clearButtonClass"
                            @click="filters.global.value = null; onFilter()"
                        >
                            <i class="pi pi-times text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </template>
                <template #end>
                    <div class="w-full sm:ml-auto sm:w-[20rem] lg:w-[22rem]">
                        <MultiSelect
                            input-id="reconciliation-columns"
                            v-model="selectedColumns"
                            :options="allColumns"
                            option-label="1"
                            placeholder="Pilih kolom"
                            display="chip"
                            filter
                            :max-selected-labels="2"
                            selected-items-label="{0} kolom dipilih"
                            class="h-11 w-full"
                        />
                    </div>
                </template>
            </Toolbar>
            <div
                class="flex min-h-0 flex-1 flex-col"
                :style="{ height: isFullscreen ? 'calc(100vh - 8rem)' : 'min(70vh, 48rem)' }"
            >
                <DataTable
                    ref="dataTable"
                    v-model:selection="selectedRows"
                    :value="filteredRows"
                    v-model:filters="filters"
                    filter-display="row"
                    :global-filter-fields="allColumns.map(([field]) => field)"
                    v-model:multi-sort-meta="multiSortMeta"
                    sort-mode="multiple"
                    lazy
                    :total-records="pagination?.total ?? 0"
                    :first="((pagination?.current_page ?? 1) - 1) * (pagination?.per_page ?? 100)"
                    data-key="id"
                    selection-mode="multiple"
                    meta-key-selection
                    @page="onPage"
                    @sort="onSort"
                    @filter="onFilter"
                    paginator
                    :rows="pagination?.per_page ?? 100"
                    :rows-per-page-options="[25, 50, 100]"
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                    current-page-report-template="{first}–{last} dari {totalRecords}"
                    scrollable
                    scroll-height="flex"
                    resizable-columns
                    column-resize-mode="expand"
                    reorderable-columns
                    striped-rows
                    row-hover
                    show-gridlines
                    removable-sort
                    size="large"
                    table-style="min-width: 108rem"
                    class="min-h-0 flex-1 text-xs"
                >
                    <template #empty>Belum ada data rekonsiliasi.</template>
                    <Column v-for="[field, header] in visibleColumns" :key="field" :field="field" sortable :show-filter-menu="false">
                        <template #header>
                            <span v-tooltip.top="formulaTooltips[field] || undefined">{{ header }}</span>
                        </template>
                        <template #filter="{ filterModel }">
                            <div class="relative">
                                <InputText v-model="filters[field].value" :aria-label="`Filter ${header}`" placeholder="Cari..." class="w-full pr-8" />
                                <button v-if="filterModel.value" type="button" :aria-label="`Hapus filter ${header}`" :class="clearButtonClass" @click="clearColumnFilter(field)">
                                    <i class="pi pi-times text-xs" aria-hidden="true"></i>
                                </button>
                            </div>
                        </template>
                        <template #body="{ data }">
                            <Tag v-if="field === 'settlement_status'" :value="orderCategory(data)" :severity="severity(orderCategory(data))" />
                            <span v-else-if="field === 'order_product_name'">{{ exportValue(data, field) }}</span>
                            <span v-else-if="money.includes(field)">{{ formatNominal(data[field]) }}</span>
                            <span v-else-if="field.endsWith('_percent')">{{ Number(data[field] || 0).toFixed(2) }}%</span>
                            <span v-else>{{ data[field] ?? 0 }}</span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>
</template>
