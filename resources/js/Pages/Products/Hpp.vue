<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { AppDataTable, AppDataTableToolbar, useDataTableContract, type TableColumnMeta } from '@/Components/DataTable'

type ProductRow = {
    code: string
    name: string
    baseUnit: string
    units: Array<{ code: string; conversion: number; hpp: string }>
    hpp: string
    status: 'active' | 'ambiguous' | 'missing'
    match: string
}

type Pagination = {
    current_page: number
    per_page: number
    last_page: number
    total: number
}

const props = defineProps<{
    products: ProductRow[]
    pagination: Pagination
    summary: { total: number; active: number; ambiguous: number; missing: number }
}>()

const allColumns = [
    ['code', 'Kode Item'],
    ['name', 'Nama Produk'],
    ['baseUnit', 'Base Unit'],
    ['units', 'Multi Satuan'],
    ['hpp', 'HPP Saat Ini'],
    ['status', 'Status Mapping'],
    ['match', 'Match'],
] as const satisfies readonly TableColumnMeta[]

const { globalFilter, multiSortMeta, isLoading } = useDataTableContract()
const selectedColumns = ref<TableColumnMeta[]>([...allColumns])

function loadData(params: Record<string, unknown> = {}) {
    router.get('/products/hpp', {
        page: params.page ?? undefined,
        per_page: params.per_page ?? undefined,
        search: globalFilter.value || undefined,
        sort_field: params.sort_field ?? multiSortMeta.value[0]?.field ?? 'code',
        sort_order: params.sort_order ?? (multiSortMeta.value[0]?.order === -1 ? 'desc' : 'asc'),
    }, { preserveScroll: true })
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

const summary = computed(() => props.summary)
const totalRecords = computed(() => props.pagination.total)
const currentPage = computed(() => props.pagination.current_page)
const perPage = computed(() => props.pagination.per_page)

const statusSeverity = (status: ProductRow['status']) => {
    if (status === 'active') {
        return 'success'
    }

    if (status === 'ambiguous') {
        return 'warn'
    }

    return 'danger'
}

const statusLabel = (status: ProductRow['status']) => {
    if (status === 'active') {
        return 'Tersambung'
    }

    if (status === 'ambiguous') {
        return 'Ambigu'
    }

    return 'Belum Map'
}
</script>

<template>
    <Head title="Master HPP" />

    <div class="flex w-full min-w-0 flex-col gap-6">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Products</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Master HPP</h1>
            </div>
            <div class="flex gap-2">
                <Button severity="secondary" outlined>
                    Import Template
                </Button>
                <Button>
                    Review Mapping
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Total Produk</p>
                            <p class="mt-2 text-2xl font-bold">{{ summary.total }}</p>
                        </div>
                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Catalog</span>
                    </div>
                </template>
            </Card>

            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Tersambung</p>
                            <p class="mt-2 text-2xl font-bold text-green-600">{{ summary.active }}</p>
                        </div>
                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">OK</span>
                    </div>
                </template>
            </Card>

            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Ambigu</p>
                            <p class="mt-2 text-2xl font-bold text-amber-600">{{ summary.ambiguous }}</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Review</span>
                    </div>
                </template>
            </Card>

            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Belum Map</p>
                            <p class="mt-2 text-2xl font-bold text-red-600">{{ summary.missing }}</p>
                        </div>
                        <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">Action</span>
                    </div>
                </template>
            </Card>
        </div>

        <Card>
            <template #content>
                <AppDataTableToolbar
                    v-model:global-filter="globalFilter"
                    v-model:selected-columns="selectedColumns"
                    :all-columns="allColumns"
                    search-placeholder="Cari KodeItem / NamaItem..."
                    @filter="onFilter"
                />
                <div class="overflow-hidden rounded-xl border border-surface-200 shadow-sm dark:border-surface-700" style="height: min(70vh, 48rem)">
                    <AppDataTable
                        :loading="isLoading"
                        :value="props.products"
                        v-model:multi-sort-meta="multiSortMeta"
                        lazy
                        :total-records="totalRecords"
                        :first="(currentPage - 1) * perPage"
                        data-key="code"
                        @page="onPage"
                        @sort="onSort"
                        @filter="onFilter"
                        paginator
                        :rows="perPage"
                        :rows-per-page-options="[10, 25, 50]"
                        paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                        current-page-report-template="{first}–{last} dari {totalRecords}"
                        table-style-min-width="88rem"
                    >
                        <template #empty>Tidak ada produk.</template>
                        <Column v-for="[field, header] in selectedColumns" :key="field" :field="field" :header="header" sortable>
                            <template #body="slotProps">
                                <template v-if="field === 'units'">
                                    <div class="flex flex-wrap gap-1">
                                        <Tag v-for="unit in slotProps.data.units" :key="`${slotProps.data.code}-${unit.code}`" :value="`${unit.code} (${unit.conversion})`" severity="secondary" />
                                    </div>
                                </template>
                                <Tag v-else-if="field === 'status'" :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" />
                                <template v-else>{{ slotProps.data[field] }}</template>
                            </template>
                        </Column>
                        <Column header="Aksi">
                            <template #body>
                                <Button severity="secondary" outlined size="small">
                                    Detail
                                </Button>
                            </template>
                        </Column>
                    </AppDataTable>
                </div>
            </template>
        </Card>
    </div>
</template>
