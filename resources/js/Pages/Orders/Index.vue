<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
import {
    AppDataTable,
    AppDataTableToolbar,
    useDataTableContract,
    type TableColumnMeta,
} from '@/Components/DataTable'
import { buildAnalyticsExportFilename } from '@/utils/exportFilename'
import { formatNominal } from '@/utils/formatters'

type OrderRow = {
    order_number: string
    order_created_at: string | null
    buyer_username: string | null
    business_status: string
    line_count: number
    quantity: number
    net_quantity: number
    subtotal: number
    admin: number
    shipping: number
    promo: number
    processing: number
    tax: number
    total_fee: number
    penghasilan: number
    hpp: number
    laba: number
}

type DetailRow = {
    order_number: string
    item_index: number
    order_product_name: string
    variation_name: string | null
    net_quantity: number
    discounted_price: number
    order_subtotal: number
    admin: number
    shipping: number
    promo: number
    processing: number
    tax: number
    total_fee: number
    penghasilan: number
    hpp: number
    hpp_status: string
    laba: number
}

type OrderSummaries = {
    subtotal: number
    total_fee: number
    penghasilan: number
    hpp: number
    laba: number
}

type Pagination = {
    current_page: number
    per_page: number
    total: number
    last_page: number
}

const props = defineProps<{
    orders: OrderRow[]
    pagination: Pagination
    appliedFrom?: string | null
    appliedTo?: string | null
    summaries?: OrderSummaries | null
    details: { order_number: string; rows: DetailRow[] } | null
}>()

const allColumns = [
    ['order_number', 'No. Pesanan'],
    ['order_created_at', 'Tanggal'],
    ['buyer_username', 'Customer'],
    ['business_status', 'Status'],
    ['line_count', 'Jml Baris'],
    ['net_quantity', 'Qty Bersih'],
    ['subtotal', 'Total Transaksi'],
    ['admin', 'Biaya Admin'],
    ['shipping', 'Gratis Ongkir'],
    ['promo', 'Promo XTRA'],
    ['processing', 'Biaya Proses'],
    ['total_fee', 'Total Biaya'],
    ['tax', 'Pajak'],
    ['penghasilan', 'Penghasilan'],
    ['hpp', 'HPP'],
    ['laba', 'Laba Bersih'],
] as const satisfies readonly TableColumnMeta[]

const sortableFields = new Set(['order_number', 'order_created_at', 'line_count', 'subtotal', 'admin', 'shipping', 'promo', 'processing', 'tax', 'hpp', 'penghasilan', 'laba'])
const moneyFields = new Set(['subtotal', 'admin', 'shipping', 'promo', 'processing', 'tax', 'total_fee', 'penghasilan', 'hpp', 'laba'])

const detailColumns = [
    ['order_product_name', 'Nama Produk'],
    ['variation_name', 'Variasi'],
    ['net_quantity', 'Jml Bersih'],
    ['discounted_price', 'Harga (@)'],
    ['order_subtotal', 'Subtotal'],
    ['admin', 'Biaya Admin'],
    ['shipping', 'Gratis Ongkir'],
    ['promo', 'Promo XTRA'],
    ['processing', 'Biaya Proses'],
    ['total_fee', 'Total Biaya'],
    ['tax', 'Pajak'],
    ['penghasilan', 'Penghasilan'],
    ['hpp', 'HPP'],
    ['hpp_status', 'Status HPP'],
    ['laba', 'Laba Bersih'],
] as const satisfies readonly TableColumnMeta[]

const detailMoneyFields = new Set(['discounted_price', 'order_subtotal', 'admin', 'shipping', 'promo', 'processing', 'tax', 'total_fee', 'penghasilan', 'hpp', 'laba'])

const { globalFilter, multiSortMeta, isLoading, selectedRows } = useDataTableContract()
const selectedColumns = ref<TableColumnMeta[]>([...allColumns])
const selectedDetailColumns = ref<TableColumnMeta[]>([...detailColumns])

const statusOptions = ['Settled', 'Refunded', 'Partially Refunded', 'Returned', 'Unmatched', 'Cancelled', 'Invalid']
const selectedStatuses = ref<string[]>([...statusOptions])

const parseDate = (value?: string | null) => (value ? new Date(`${value}T00:00:00`) : null)
const fromDate = ref<Date | null>(parseDate(props.appliedFrom))
const toDate = ref<Date | null>(parseDate(props.appliedTo))
const appliedFromDate = ref<Date | null>(parseDate(props.appliedFrom))
const appliedToDate = ref<Date | null>(parseDate(props.appliedTo))
const hasAppliedFilter = ref(Boolean(props.appliedFrom || props.appliedTo))
const dateValidationError = ref<string | null>(null)

const detailVisible = ref(false)
const activeOrderNumber = ref<string | null>(null)
const detailLoading = ref(false)

const activeDetailsReady = computed(() => props.details?.order_number === activeOrderNumber.value && !detailLoading.value)
const activeOrderStatus = computed(() => props.orders.find((order) => order.order_number === activeOrderNumber.value)?.business_status ?? '')
const detailRows = computed<DetailRow[]>(() => (activeDetailsReady.value ? props.details?.rows ?? [] : []))

const detailTotals = computed(() => {
    const sum = (field: keyof DetailRow) => detailRows.value.reduce((acc, row) => acc + Number(row[field] ?? 0), 0)

    return {
        order_subtotal: sum('order_subtotal'),
        total_fee: sum('total_fee'),
        penghasilan: sum('penghasilan'),
        hpp: sum('hpp'),
        laba: sum('laba'),
    }
})

const summaryCards = [
    { key: 'subtotal', label: 'Total Transaksi (Subtotal)' },
    { key: 'total_fee', label: 'Total Biaya' },
    { key: 'penghasilan', label: 'Total Penghasilan' },
    { key: 'hpp', label: 'Total HPP' },
    { key: 'laba', label: 'Total Laba Bersih' },
] as const

const summaryValue = (key: keyof OrderSummaries) => Number(props.summaries?.[key] ?? 0)

const statusSeverity = (status: string) => {
    if (status === 'Settled') return 'success'
    if (status === 'Unmatched') return 'warn'
    if (status === 'Partially Refunded' || status === 'Returned') return 'info'
    if (status === 'Refunded') return 'danger'
    if (status === 'Cancelled') return 'danger'
    return 'secondary'
}

const statusLabel = (status: string) => {
    const labels: Record<string, string> = {
        Settled: 'Settled',
        Unmatched: 'Belum Settlement',
        Refunded: 'Refunded',
        'Partially Refunded': 'Partially Refunded',
        Returned: 'Returned',
        Cancelled: 'Batal',
        Invalid: 'Tidak Valid',
    }
    return labels[status] ?? status
}

const hppStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        ok: 'OK',
        mapping_missing: 'Mapping Hilang',
        mapping_ambiguous: 'Mapping Ganda',
        hpp_missing: 'HPP Kosong',
        no_allocation: 'Belum Dialokasi',
    }
    return labels[status] ?? '—'
}

const hppStatusSeverity = (status: string) => {
    if (status === 'ok') return 'success'
    if (status === 'no_allocation') return 'info'
    return 'warn'
}

const currentParams = () => {
    const params: Record<string, string | string[]> = {}
    if (appliedFromDate.value) params.from = localDateKey(appliedFromDate.value)
    if (appliedToDate.value) params.to = localDateKey(appliedToDate.value)
    params.statuses = selectedStatuses.value

    return params
}

function localDateKey(date: Date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

function loadData(params: Record<string, unknown> = {}) {
    activeOrderNumber.value = null
    detailVisible.value = false
    router.get('/orders', {
        page: params.page ?? undefined,
        per_page: params.per_page ?? undefined,
        search: globalFilter.value || undefined,
        sort_field: params.sort_field ?? multiSortMeta.value[0]?.field ?? 'order_created_at',
        sort_order: params.sort_order ?? (multiSortMeta.value[0]?.order === -1 ? 'desc' : 'asc'),
        ...currentParams(),
    }, { preserveScroll: true, preserveState: true })
}

function onPage(event: { first: number; rows: number }) {
    loadData({ page: Math.floor(event.first / event.rows) + 1, per_page: event.rows })
}

function onSort() {
    loadData({ page: 1 })
}

function onFilter() {
    loadData({ page: 1 })
}

function onStatusFilterChange() {
    onFilter()
}

function applyDateFilter() {
    if (!fromDate.value || !toDate.value) {
        dateValidationError.value = 'Tanggal belum ditentukan.'
        return
    }
    if (fromDate.value > toDate.value) {
        dateValidationError.value = 'Tanggal mulai harus sebelum tanggal akhir.'
        return
    }

    dateValidationError.value = null
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
    selectedStatuses.value = [...statusOptions]
    router.get('/orders', { statuses: selectedStatuses.value }, { preserveScroll: true, preserveState: true })
}

function openDetail(orderNumber: string) {
    activeOrderNumber.value = orderNumber
    detailVisible.value = true
    detailLoading.value = true

    router.get('/orders', {
        ...currentParams(),
        search: globalFilter.value || undefined,
        order: orderNumber,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['details'],
        onFinish: () => {
            detailLoading.value = false
        },
    })
}

function closeDetail() {
    detailVisible.value = false
    activeOrderNumber.value = null
}

function onRowDblclick({ data }: { data: OrderRow }) {
    openDetail(data.order_number)
}

function formatDate(value: string | null | undefined) {
    if (!value) return '—'
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatQuantity(value: number | null | undefined) {
    return Number(value ?? 0).toLocaleString('id-ID')
}

const exportExcel = async () => {
    const XLSX = await import('xlsx')
    const data = props.orders.map((row) => Object.fromEntries(
        selectedColumns.value.map(([field, header]) => [header, moneyFields.has(field) ? formatNominal(row[field as keyof OrderRow]) : row[field as keyof OrderRow]]),
    ))
    const worksheet = XLSX.utils.json_to_sheet(data)
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Orders')
    const fromLabel = appliedFromDate.value ? localDateKey(appliedFromDate.value) : 'semua'
    const toLabel = appliedToDate.value ? localDateKey(appliedToDate.value) : 'semua'
    XLSX.writeFile(workbook, buildAnalyticsExportFilename(fromLabel, toLabel))
}
</script>

<template>
    <Head title="Orders" />

    <div class="flex w-full min-w-0 flex-col gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Operations</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">Orders</h1>
            <p class="mt-2 text-sm text-slate-500">Ringkasan transaksi per nomor order lengkap dengan rincian biaya hingga laba bersih.</p>
        </div>

        <Card>
            <template #content>
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-filter text-color-secondary" aria-hidden="true"></i>
                        <span class="text-sm font-semibold">Filter periode</span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(15rem,1.4fr)_auto] lg:items-end">
                        <DateRangeFilter id-prefix="orders" v-model:from="fromDate" v-model:to="toDate" />
                        <div class="flex flex-wrap items-center gap-2">
                            <Message v-if="dateValidationError" severity="error" :closable="false" class="w-full">
                                {{ dateValidationError }}
                            </Message>
                            <Button label="Terapkan" icon="pi pi-filter" class="h-11 w-full sm:w-auto" @click="applyDateFilter" />
                            <Button label="Reset" icon="pi pi-refresh" severity="secondary" outlined class="h-11 w-full sm:w-auto" @click="resetDateFilter" />
                        </div>
                    </div>
                </div>
            </template>
        </Card>

        <Card>
            <template #content>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-chart-line text-color-secondary" aria-hidden="true"></i>
                        <span class="text-sm font-semibold">Ringkasan pesanan terfilter</span>
                        <span class="text-xs text-color-secondary">Total dari seluruh halaman sesuai rentang tanggal & pencarian aktif.</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                        <Card v-for="card in summaryCards" :key="card.key" class="[&_.p-card-body]:!p-3 [&_.p-card-content]:!p-0">
                            <template #content>
                                <p class="text-xs font-semibold text-color-secondary">{{ card.label }}</p>
                                <p class="mt-1 text-lg font-bold leading-tight">{{ formatNominal(summaryValue(card.key)) }}</p>
                            </template>
                        </Card>
                    </div>
                </div>
            </template>
        </Card>

        <div class="min-w-0">
            <AppDataTableToolbar
                v-model:global-filter="globalFilter"
                v-model:selected-columns="selectedColumns"
                :all-columns="allColumns"
                search-placeholder="Cari nomor order / produk / variasi / status..."
                @filter="onFilter"
            >
                <template #filters>
                    <MultiSelect
                        v-model="selectedStatuses"
                        :options="statusOptions"
                        :max-selected-labels="1"
                        selected-items-label="{0} status dipilih"
                        placeholder="Semua status"
                        display="comma"
                        class="h-11 w-full text-sm sm:w-[16rem]"
                        :pt="{
                            root: { class: 'h-11 rounded-md shadow-none' },
                            trigger: { class: 'rounded-md border-surface-300 bg-surface-0 transition-colors hover:border-primary dark:bg-surface-950' },
                            panel: { class: 'text-sm' },
                            item: { class: 'py-2' },
                            header: { class: 'px-3 py-2' },
                        }"
                        @change="onStatusFilterChange"
                    />
                </template>
                <template #actions>
                    <Button label="Export Excel" icon="pi pi-download" severity="secondary" outlined class="h-11 px-3" :disabled="orders.length === 0" @click="exportExcel" />
                </template>
            </AppDataTableToolbar>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg bg-surface-0 dark:bg-surface-950" style="height: min(70vh, 48rem)">
                <AppDataTable
                    :loading="isLoading"
                    :value="orders"
                    v-model:multi-sort-meta="multiSortMeta"
                    sort-mode="multiple"
                    lazy
                    :total-records="pagination.total"
                    :first="(pagination.current_page - 1) * pagination.per_page"
                    data-key="order_number"
                    v-model:selection="selectedRows"
                    selection-mode="multiple"
                    @page="onPage"
                    @sort="onSort"
                    @filter="onFilter"
                    @row-dblclick="onRowDblclick"
                    paginator
                    :rows="pagination.per_page"
                    :rows-per-page-options="[25, 50, 100]"
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                    current-page-report-template="{first}–{last} dari {totalRecords}"
                    table-style-min-width="130rem"
                >
                    <template #empty>Belum ada transaksi. Import laporan order terlebih dahulu.</template>

                    <Column
                        v-for="[field, header] in selectedColumns"
                        :key="field"
                        :field="field"
                        :header="header"
                        :sortable="sortableFields.has(field)"
                    >
                        <template #body="{ data }">
                            <template v-if="field === 'order_number'">
                                <span class="font-semibold text-slate-800">{{ data.order_number }}</span>
                            </template>
                            <template v-else-if="field === 'order_created_at'">{{ formatDate(data.order_created_at) }}</template>
                            <template v-else-if="field === 'buyer_username'">{{ data.buyer_username || '—' }}</template>
                            <Tag v-else-if="field === 'business_status'" :value="statusLabel(data.business_status)" :severity="statusSeverity(data.business_status)" />
                            <template v-else-if="moneyFields.has(field)">{{ formatNominal(data[field]) }}</template>
                            <template v-else>{{ formatQuantity(data[field]) }}</template>
                        </template>
                    </Column>

                    <Column header="" :exportable="false" frozen align-frozen="right" style="min-width: 4rem">
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-eye"
                                rounded
                                text
                                size="small"
                                :aria-label="`Lihat detail ${data.order_number}`"
                                @click="openDetail(data.order_number)"
                            />
                        </template>
                    </Column>
                </AppDataTable>
            </div>
        </div>
    </div>

    <Dialog
        v-model:visible="detailVisible"
        modal
        :header="`Detail ${activeOrderNumber ?? ''}`"
        :style="{ width: 'min(76rem, 96vw)' }"
        :maximizable="true"
        @hide="closeDetail"
    >
        <div class="flex flex-col gap-3">
            <div v-if="detailLoading" class="flex items-center justify-center gap-3 p-8 text-color-secondary">
                <ProgressSpinner style="width: 1.5rem; height: 1.5rem" aria-label="Memuat detail" />
                <span class="text-sm">Memuat detail...</span>
            </div>

            <template v-else-if="activeDetailsReady && detailVisible">
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                    <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">{{ activeOrderNumber }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1">{{ detailRows.length }} baris item</span>
                    <Tag v-if="activeOrderStatus" :value="statusLabel(activeOrderStatus)" :severity="statusSeverity(activeOrderStatus)" />
                    <span class="rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-800">Laba Bersih {{ formatNominal(detailTotals.laba) }}</span>
                </div>

                <AppDataTableToolbar
                    v-model:selected-columns="selectedDetailColumns"
                    :all-columns="detailColumns"
                    :columns-label="`Kolom detail (${selectedDetailColumns.length})`"
                >
                    <template #start>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600">
                            <span><span class="text-slate-400">Penghasilan:</span> <strong class="text-slate-800">{{ formatNominal(detailTotals.penghasilan) }}</strong></span>
                            <span><span class="text-slate-400">HPP:</span> <strong class="text-slate-800">{{ formatNominal(detailTotals.hpp) }}</strong></span>
                            <span><span class="text-slate-400">Total Biaya:</span> <strong class="text-slate-800">{{ formatNominal(detailTotals.total_fee) }}</strong></span>
                        </div>
                    </template>
                </AppDataTableToolbar>

                <div class="overflow-auto rounded-lg bg-surface-0 dark:bg-surface-950">
                    <AppDataTable :value="detailRows" paginator :rows="10" :rows-per-page-options="[5, 10, 25]"
                        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                        current-page-report-template="{first}–{last} dari {totalRecords}" table-style-min-width="110rem">
                        <template #empty>Detail tidak ditemukan.</template>
                        <Column v-for="[field, header] in selectedDetailColumns" :key="field" :field="field" :header="header">
                            <template #body="{ data: detailData }">
                                <Tag v-if="field === 'hpp_status'" :value="hppStatusLabel(detailData.hpp_status)" :severity="hppStatusSeverity(detailData.hpp_status)" />
                                <template v-else-if="field === 'variation_name'">{{ detailData.variation_name || '—' }}</template>
                                <template v-else-if="detailMoneyFields.has(field)">{{ formatNominal(detailData[field]) }}</template>
                                <template v-else-if="field === 'net_quantity'">{{ formatQuantity(detailData.net_quantity) }}</template>
                                <template v-else>{{ detailData[field] ?? '—' }}</template>
                            </template>
                        </Column>
                    </AppDataTable>
                </div>
            </template>
        </div>
    </Dialog>
</template>
