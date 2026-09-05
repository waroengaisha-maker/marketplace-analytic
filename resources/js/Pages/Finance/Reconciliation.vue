<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import MultiSelect from 'primevue/multiselect'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { formatNominal } from '@/utils/formatters'
import { FilterMatchMode } from '@primevue/core/api'

type Row = Record<string, unknown>
type DataTableInstance = { exportCSV: () => void; filteredValue?: Row[] }
const props = defineProps<{ rows: Row[] }>()
const dataTable = ref<DataTableInstance | null>(null)
const isFullscreen = ref(false)
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
const visibleColumns = computed(() => selectedColumns.value)
const filteredRows = computed(() => props.rows || [])
function severity(status: string) { return status === 'Settled' || status === 'Grouped Match' ? 'success' : status === 'Ambiguous' ? 'warn' : 'danger' }
function exportValue(row: Row, field: string) {
    if (field === 'order_product_name') {
        const productName = String(row[field] ?? '')
        const variationName = String(row.order_variation_name ?? '').trim()

        return variationName ? `${productName} - ${variationName}` : productName
    }

    return row[field] ?? ''
}
function numericValue(row: Row, field: string) {
    const value = Number(row[field] ?? 0)

    return Number.isFinite(value) ? value : 0
}
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
    const exportRows = dataTable.value?.filteredValue || filteredRows.value
    const data = exportRows.map((row) => Object.fromEntries(
        visibleFields.map(([field, header]) => [header, exportValue(row, field)]),
    ))
    const worksheet = XLSX.utils.json_to_sheet(data)
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Reconciliation')
    const summaryRows = buildProductSummary(exportRows).map((row) => ({
        'Nama Produk': row.product_name,
        'Harga (@)': row.unit_price,
        'Jumlah': row.quantity,
        'Retur': row.returned_quantity,
        'Jumlah Bersih': row.net_quantity,
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
    XLSX.writeFile(workbook, 'reconciliation.xlsx')
}
function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value
}
</script>

<template>
    <Head title="Reconciliation" />
    <div class="flex flex-col gap-6">
        <div v-if="!isFullscreen"><h1 class="text-3xl font-bold">Reconciliation</h1><p class="mt-2 text-color-secondary">Detail Order dan Income dengan pencocokan aman.</p></div>
        <Card v-if="!isFullscreen">
            <template #content>
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row">
                        <InputText v-model="filters.global.value" aria-label="Filter semua kolom" placeholder="Filter semua kolom..." class="w-full sm:w-64" />
                        <MultiSelect
                            v-model="selectedColumns"
                            :options="allColumns"
                            option-label="1"
                            placeholder="Tampilkan kolom"
                            display="chip"
                            class="w-full sm:w-72"
                        />
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Button label="Export Excel" icon="pi pi-file-excel" severity="success" outlined class="w-full sm:w-auto" :disabled="filteredRows.length === 0" @click="exportExcel" />
                        <Button label="Export CSV" icon="pi pi-download" severity="secondary" outlined class="w-full sm:w-auto" :disabled="filteredRows.length === 0" @click="exportCsv" />
                        <Button :label="isFullscreen ? 'Keluar Fullscreen' : 'Fullscreen'" :icon="isFullscreen ? 'pi pi-window-minimize' : 'pi pi-window-maximize'" severity="secondary" outlined class="w-full sm:w-auto" @click="toggleFullscreen" />
                    </div>
                </div>
            </template>
        </Card>
        <div
            class="relative min-w-0"
            :class="isFullscreen ? 'fixed inset-0 z-50 overflow-hidden bg-white p-2 dark:bg-black sm:p-4' : ''"
        >
            <Button
                v-if="isFullscreen"
                label="Keluar Fullscreen"
                icon="pi pi-window-minimize"
                severity="secondary"
                outlined
                class="absolute right-4 top-4 z-10 shadow-sm"
                @click="toggleFullscreen"
            />
            <DataTable
                ref="dataTable"
                :value="filteredRows"
                v-model:filters="filters"
                filter-display="row"
                :global-filter-fields="allColumns.map(([field]) => field)"
                paginator
                :rows="25"
                :rows-per-page-options="[25, 50, 100]"
                paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                current-page-report-template="{first}–{last} dari {totalRecords}"
                scrollable
                :scroll-height="isFullscreen ? 'calc(100vh - 8rem)' : 'calc(100vh - 20rem)'"
                resizable-columns
                column-resize-mode="expand"
                reorderable-columns
                striped-rows
                row-hover
                show-gridlines
                removable-sort
                size="small"
                table-style="min-width: 108rem"
                class="w-full text-xs"
            >
                <template #empty>Belum ada data rekonsiliasi.</template>
                <Column v-for="[field, header] in visibleColumns" :key="field" :field="field" sortable :show-filter-menu="false">
                    <template #header>
                        <span v-tooltip.top="formulaTooltips[field] || undefined">{{ header }}</span>
                    </template>
                    <template #filter="{ filterModel }">
                        <InputText v-model="filterModel.value" :aria-label="`Filter ${header}`" placeholder="Cari..." class="w-full" />
                    </template>
                    <template #body="{ data }">
                        <Tag v-if="field === 'settlement_status'" :value="String(data[field] || '')" :severity="severity(String(data[field] || ''))" />
                        <span v-else-if="field === 'order_product_name'">{{ exportValue(data, field) }}</span>
                        <span v-else-if="money.includes(field)">{{ formatNominal(data[field]) }}</span>
                        <span v-else-if="field.endsWith('_percent')">{{ Number(data[field] || 0).toFixed(2) }}%</span>
                        <span v-else>{{ data[field] ?? 0 }}</span>
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>
