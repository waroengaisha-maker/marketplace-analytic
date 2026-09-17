<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
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

type CustomerRow = {
    buyer_username: string
    order_count: number
    line_count: number
    net_quantity: number
    subtotal: number
    total_fee: number
    penghasilan: number
    hpp: number
    laba: number
}

type HistoryRow = {
    order_number: string
    order_created_at: string | null
    business_status: string
    product_name: string
    variation_name: string | null
    net_quantity: number
    subtotal: number
    total_fee: number
    penghasilan: number
    hpp: number
    laba: number
}

type CustomerSummaries = {
    order_count: number
    subtotal: number
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
    customers: CustomerRow[]
    pagination: Pagination
    appliedFrom?: string | null
    appliedTo?: string | null
    summaries?: CustomerSummaries | null
    details: { buyer_username: string; rows: HistoryRow[] } | null
}>()

const allColumns = [
    ['buyer_username', 'Username'],
    ['order_count', 'Jml Pesanan'],
    ['net_quantity', 'Qty Beli'],
    ['subtotal', 'Total Belanja'],
    ['hpp', 'Total HPP'],
    ['laba', 'Laba Bersih'],
] as const satisfies readonly TableColumnMeta[]

const sortableFields = new Set(allColumns.map(([field]) => field))
const moneyFields = new Set(['subtotal', 'hpp', 'laba'])

const historyColumns = [
    ['order_number', 'No. Pesanan'],
    ['order_created_at', 'Tanggal'],
    ['business_status', 'Status'],
    ['product_name', 'Produk'],
    ['variation_name', 'Variasi'],
    ['net_quantity', 'Qty Bersih'],
    ['subtotal', 'Total Belanja'],
    ['hpp', 'HPP'],
    ['laba', 'Laba Bersih'],
] as const satisfies readonly TableColumnMeta[]

const historyMoneyFields = new Set(['subtotal', 'hpp', 'laba'])

const { globalFilter, multiSortMeta, isLoading, selectedRows } = useDataTableContract()
const selectedColumns = ref<TableColumnMeta[]>([...allColumns])
const selectedHistoryColumns = ref<TableColumnMeta[]>([...historyColumns])

const appliedSearch = ref('')

const parseDate = (value?: string | null) => (value ? new Date(`${value}T00:00:00`) : null)
const fromDate = ref<Date | null>(parseDate(props.appliedFrom))
const toDate = ref<Date | null>(parseDate(props.appliedTo))
const appliedFromDate = ref<Date | null>(parseDate(props.appliedFrom))
const appliedToDate = ref<Date | null>(parseDate(props.appliedTo))
const hasAppliedFilter = ref(Boolean(props.appliedFrom || props.appliedTo))
const dateValidationError = ref<string | null>(null)

const detailVisible = ref(false)
const activeBuyer = ref<string | null>(null)
const detailLoading = ref(false)
const detailGlobalFilter = ref<string | null>(null)

const activeDetailsReady = computed(() => props.details?.buyer_username === activeBuyer.value && !detailLoading.value)
const historyRows = computed<HistoryRow[]>(() => (activeDetailsReady.value ? props.details?.rows ?? [] : []))

const historyFilteredRows = computed<HistoryRow[]>(() => {
    const query = (detailGlobalFilter.value ?? '').trim().toLowerCase()
    if (!query) return historyRows.value

    return historyRows.value.filter((row) => `${Object.values(row).join(' ')}`.toLowerCase().includes(query))
})

const historyTotals = computed(() => {
    const sum = (field: keyof HistoryRow) => historyRows.value.reduce((acc, row) => acc + Number(row[field] ?? 0), 0)

    return {
        subtotal: sum('subtotal'),
        hpp: sum('hpp'),
        laba: sum('laba'),
    }
})

const summaryCards = [
    { key: 'order_count', label: 'Jumlah Pesanan' },
    { key: 'subtotal', label: 'Total Belanja' },
    { key: 'hpp', label: 'Total HPP' },
    { key: 'laba', label: 'Total Laba Bersih' },
] as const

const summaryValue = (key: keyof CustomerSummaries) => Number(props.summaries?.[key] ?? 0)

const periodLabel = computed(() => {
    const format = (value: Date | null) => value?.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    const from = format(appliedFromDate.value)
    const to = format(appliedToDate.value)

    return from && to ? `Periode: ${from} – ${to}` : 'Semua tanggal'
})

const statusSeverity = (status: string) => {
    if (status === 'Settled') return 'success'
    if (status === 'Unmatched') return 'warn'
    if (status === 'Partially Refunded' || status === 'Returned') return 'info'
    if (status === 'Refunded') return 'danger'
    if (status === 'Cancelled') return 'danger'
    return 'secondary'
}

const currentParams = () => {
    const params: Record<string, string> = {}
    if (appliedFromDate.value) params.from = localDateKey(appliedFromDate.value)
    if (appliedToDate.value) params.to = localDateKey(appliedToDate.value)

    return params
}

function localDateKey(date: Date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

function loadData(params: Record<string, unknown> = {}) {
    activeBuyer.value = null
    detailVisible.value = false
    router.get('/customers', {
        page: params.page ?? undefined,
        per_page: params.per_page ?? undefined,
        search: appliedSearch.value || undefined,
        sort_field: params.sort_field ?? multiSortMeta.value[0]?.field ?? 'laba',
        sort_order: params.sort_order ?? (multiSortMeta.value[0] ? (multiSortMeta.value[0].order === -1 ? 'desc' : 'asc') : 'desc'),
        ...currentParams(),
    }, { preserveScroll: true, preserveState: true })
}

function onPage(event: { first: number; rows: number }) {
    loadData({ page: Math.floor(event.first / event.rows) + 1, per_page: event.rows })
}

function onSort() {
    loadData({ page: 1 })
}

function applyFilters() {
    if (fromDate.value && toDate.value && fromDate.value > toDate.value) {
        dateValidationError.value = 'Tanggal mulai harus sebelum tanggal akhir.'
        return
    }

    dateValidationError.value = null
    appliedFromDate.value = fromDate.value
    appliedToDate.value = toDate.value
    appliedSearch.value = globalFilter.value || ''
    hasAppliedFilter.value = true
    loadData({ page: 1 })
}

function resetDateFilter() {
    fromDate.value = null
    toDate.value = null
    appliedFromDate.value = null
    appliedToDate.value = null
    hasAppliedFilter.value = true
    globalFilter.value = ''
    appliedSearch.value = ''
    loadData({ page: 1 })
}

function openDetail(buyer: string) {
    activeBuyer.value = buyer
    detailVisible.value = true
    detailLoading.value = true
    detailGlobalFilter.value = null

    router.get('/customers', {
        ...currentParams(),
        search: appliedSearch.value || undefined,
        customer: buyer,
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
    activeBuyer.value = null
}

function onRowDblclick({ data }: { data: CustomerRow }) {
    openDetail(data.buyer_username)
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

    const params = new URLSearchParams()
    if (appliedFromDate.value) params.set('from', localDateKey(appliedFromDate.value))
    if (appliedToDate.value) params.set('to', localDateKey(appliedToDate.value))
    if (appliedSearch.value) params.set('search', appliedSearch.value)

    const response = await fetch(`/customers/export-data?${params.toString()}`)
    const payload: { customers: CustomerRow[] } = await response.json()

    const data = payload.customers.map((row) => Object.fromEntries(
        selectedColumns.value.map(([field, header]) => [header, row[field as keyof CustomerRow] ?? '']),
    ))
    const worksheet = XLSX.utils.json_to_sheet(data)
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Customers')
    XLSX.writeFile(workbook, buildAnalyticsExportFilename(
        appliedFromDate.value ? localDateKey(appliedFromDate.value) : 'awal',
        appliedToDate.value ? localDateKey(appliedToDate.value) : 'akhir',
    ))
}
</script>

<template>
    <Head title="Customers" />

    <div class="flex w-full min-w-0 flex-col gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Operations</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">Customers</h1>
            <p class="mt-2 text-sm text-slate-500">Ringkasan belanja per pembeli: total belanja, total HPP, hingga laba bersih, beserta histori transaksinya.</p>
        </div>

        <Card>
            <template #content>
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-filter text-color-secondary" aria-hidden="true"></i>
                        <span class="text-sm font-semibold">Filter data</span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-[minmax(15rem,1.3fr)_minmax(14rem,1fr)_minmax(14rem,1.2fr)]">
                        <DateRangeFilter id-prefix="customers" v-model:from="fromDate" v-model:to="toDate" class="md:col-span-2 xl:col-span-2" />
                        <div class="flex min-w-0 flex-col gap-1">
                            <label for="customers-search" class="text-xs font-medium text-color-secondary">Cari</label>
                            <div class="relative w-full">
                                <IconField icon-position="left" class="w-full">
                                    <InputIcon class="pi pi-search text-sm text-color-secondary" />
                                    <InputText
                                        id="customers-search"
                                        v-model="globalFilter"
                                        placeholder="Cari username..."
                                        class="h-11 w-full pl-10 pr-10 text-sm"
                                    />
                                </IconField>
                                <button
                                    v-if="globalFilter"
                                    type="button"
                                    aria-label="Hapus pencarian"
                                    class="absolute right-2 top-1/2 z-10 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full border-0 bg-transparent p-0 text-color-secondary transition-colors hover:bg-surface-200 hover:text-color dark:hover:bg-surface-700"
                                    @click="globalFilter = null"
                                >
                                    <i class="pi pi-times text-xs" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flex min-w-0 flex-col gap-1">
                            <label for="customers-columns" class="text-xs font-medium text-color-secondary">Kolom tampil</label>
                            <MultiSelect
                                input-id="customers-columns"
                                v-model="selectedColumns"
                                :options="allColumns"
                                option-label="1"
                                :placeholder="`Pilih kolom (${selectedColumns.length})`"
                                display="comma"
                                filter
                                :max-selected-labels="2"
                                selected-items-label="{0} kolom dipilih"
                                class="h-11 w-full text-sm"
                                :pt="{
                                    root: { class: 'h-11 rounded-md shadow-none' },
                                    trigger: { class: 'rounded-md border-surface-300 bg-surface-0 transition-colors hover:border-primary dark:bg-surface-950' },
                                    panel: { class: 'text-sm' },
                                    item: { class: 'py-2' },
                                    header: { class: 'px-3 py-2' },
                                }"
                            />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Message v-if="dateValidationError" severity="error" :closable="false" class="w-full">
                            {{ dateValidationError }}
                        </Message>
                        <Button label="Terapkan" icon="pi pi-filter" class="h-11 w-full sm:w-auto" @click="applyFilters" />
                        <Button label="Reset" icon="pi pi-refresh" severity="secondary" outlined class="h-11 w-full sm:w-auto" @click="resetDateFilter" />
                    </div>
                </div>
            </template>
        </Card>

        <Card>
            <template #content>
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <i class="pi pi-chart-line text-color-secondary" aria-hidden="true"></i>
                        <span class="text-sm font-semibold">Ringkasan customer terfilter</span>
                        <Tag v-if="periodLabel" :value="periodLabel" severity="info" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Card v-for="card in summaryCards" :key="card.key" class="[&_.p-card-body]:!p-3 [&_.p-card-content]:!p-0">
                            <template #content>
                                <p class="text-xs font-semibold text-color-secondary">{{ card.label }}</p>
                                <p class="mt-1 text-lg font-bold leading-tight">
                                    <template v-if="card.key === 'order_count'">{{ formatQuantity(summaryValue(card.key)) }}</template>
                                    <template v-else>{{ formatNominal(summaryValue(card.key)) }}</template>
                                </p>
                            </template>
                        </Card>
                    </div>
                </div>
            </template>
        </Card>

        <Card>
            <template #content>
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <i class="pi pi-table text-color-secondary" aria-hidden="true"></i>
                        <span class="text-sm font-semibold">Data customer</span>
                    </div>

                    <AppDataTableToolbar :hide-search="true">
                        <template #actions>
                            <Button label="Export Excel" icon="pi pi-download" severity="secondary" outlined class="h-11 px-3" :disabled="customers.length === 0" @click="exportExcel" />
                        </template>
                    </AppDataTableToolbar>

                    <div class="flex min-h-0 flex-col overflow-hidden rounded-lg bg-surface-0 dark:bg-surface-950" style="height: min(70vh, 48rem)">
                        <AppDataTable
                            :loading="isLoading"
                            :value="customers"
                            v-model:multi-sort-meta="multiSortMeta"
                            sort-mode="multiple"
                            lazy
                            :total-records="pagination.total"
                            :first="(pagination.current_page - 1) * pagination.per_page"
                            data-key="buyer_username"
                            v-model:selection="selectedRows"
                            selection-mode="multiple"
                            @page="onPage"
                            @sort="onSort"
                            @row-dblclick="onRowDblclick"
                            paginator
                            :rows="pagination.per_page"
                            :rows-per-page-options="[25, 50, 100]"
                            paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                            current-page-report-template="{first}–{last} dari {totalRecords}"
                            table-style-min-width="88rem"
                        >
                            <template #empty>Belum ada customer. Import laporan order terlebih dahulu.</template>

                            <Column
                                v-for="[field, header] in selectedColumns"
                                :key="field"
                                :field="field"
                                :header="header"
                                :sortable="sortableFields.has(field)"
                                :frozen="field === 'buyer_username'"
                                align-frozen="left"
                            >
                                <template #body="{ data }">
                                    <template v-if="field === 'buyer_username'">
                                        <span class="font-semibold text-slate-800">{{ data.buyer_username }}</span>
                                    </template>
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
                                        :aria-label="`Lihat detail ${data.buyer_username}`"
                                        @click="openDetail(data.buyer_username)"
                                    />
                                </template>
                            </Column>
                        </AppDataTable>
                    </div>
                </div>
            </template>
        </Card>
    </div>

    <Dialog
        v-model:visible="detailVisible"
        modal
        :header="`Detail ${activeBuyer ?? ''}`"
        :style="{ width: 'min(76rem, 96vw)' }"
        :maximizable="true"
        :dismissable-mask="true"
        @hide="closeDetail"
    >
        <div class="flex flex-col gap-3">
            <div v-if="detailLoading" class="flex items-center justify-center gap-3 p-8 text-color-secondary">
                <ProgressSpinner style="width: 1.5rem; height: 1.5rem" aria-label="Memuat detail" />
                <span class="text-sm">Memuat detail...</span>
            </div>

            <template v-else-if="activeDetailsReady && detailVisible">
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                    <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">{{ activeBuyer }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1">{{ historyRows.length }} baris item</span>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-800">Laba Bersih {{ formatNominal(historyTotals.laba) }}</span>
                </div>

                <AppDataTableToolbar
                    v-model:global-filter="detailGlobalFilter"
                    v-model:selected-columns="selectedHistoryColumns"
                    :all-columns="historyColumns"
                    search-placeholder="Cari nomor pesanan / produk / status..."
                    :columns-label="`Kolom histori (${selectedHistoryColumns.length})`"
                >
                    <template #start>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600">
                            <span><span class="text-slate-400">Total Belanja:</span> <strong class="text-slate-800">{{ formatNominal(historyTotals.subtotal) }}</strong></span>
                            <span><span class="text-slate-400">HPP:</span> <strong class="text-slate-800">{{ formatNominal(historyTotals.hpp) }}</strong></span>
                        </div>
                    </template>
                </AppDataTableToolbar>

                <div class="overflow-auto rounded-lg bg-surface-0 dark:bg-surface-950">
                    <AppDataTable :value="historyFilteredRows" paginator :rows="10" :rows-per-page-options="[10, 25, 50]"
                        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                        current-page-report-template="{first}–{last} dari {totalRecords}" table-style-min-width="96rem">
                        <template #empty>Histori belanja tidak ditemukan.</template>
                        <Column v-for="[field, header] in selectedHistoryColumns" :key="field" :field="field" :header="header">
                            <template #body="{ data: historyData }">
                                <template v-if="field === 'order_number'">
                                    <span class="font-semibold text-slate-800">{{ historyData.order_number }}</span>
                                </template>
                                <template v-else-if="field === 'order_created_at'">{{ formatDate(historyData.order_created_at) }}</template>
                                <Tag v-else-if="field === 'business_status'" :value="historyData.business_status" :severity="statusSeverity(historyData.business_status)" />
                                <template v-else-if="field === 'product_name'">
                                    <span class="font-medium text-slate-700">{{ historyData.product_name }}</span>
                                </template>
                                <template v-else-if="field === 'variation_name'">{{ historyData.variation_name || '—' }}</template>
                                <template v-else-if="historyMoneyFields.has(field)">{{ formatNominal(historyData[field]) }}</template>
                                <template v-else>{{ formatQuantity(historyData[field]) }}</template>
                            </template>
                        </Column>
                    </AppDataTable>
                </div>
            </template>
        </div>
    </Dialog>
</template>