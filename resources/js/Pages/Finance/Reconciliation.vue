<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { formatNominal } from '@/utils/formatters'

type Row = Record<string, unknown>
type DataTableInstance = { exportCSV: () => void }
const props = defineProps<{ rows: Row[] }>()
const dataTable = ref<DataTableInstance | null>(null)
const orderFilter = ref('')
const productFilter = ref('')
const filteredRows = computed(() => (props.rows || []).filter((row) =>
    String(row.order_number || '').toLowerCase().includes(orderFilter.value.toLowerCase()) &&
    String(row.order_product_name || row.product_name || '').toLowerCase().includes(productFilter.value.toLowerCase()),
))
const money = ['discounted_price', 'order_subtotal', 'platform_fee', 'free_shipping_xtra_fee', 'promo_xtra_service_fee', 'fee_subtotal', 'order_processing_fee', 'total_fee', 'tax', 'penghasilan', 'hpp', 'laba']
const formulaTooltips: Record<string, string> = {
    net_quantity: 'Jumlah Bersih = Jumlah - Retur',
    order_subtotal: 'Subtotal = Harga setelah diskon x Jumlah',
    admin_fee_percent: 'Admin (%) = Biaya Administrasi / Subtotal x 100',
    free_shipping_xtra_fee_percent: 'Gratis Ongkir (%) = Gratis Ongkir / Subtotal x 100',
    promo_xtra_fee_percent: 'Promo XTRA (%) = Promo XTRA / Subtotal x 100',
    fee_subtotal: 'Subtotal Biaya = Biaya Administrasi + Gratis Ongkir + Promo XTRA',
    fee_subtotal_percent: 'Subtotal Biaya (%) = Subtotal Biaya / Subtotal x 100',
    total_fee: 'Total Biaya = Subtotal Biaya + Biaya Proses',
    penghasilan: 'Penghasilan = Subtotal - (Total Biaya + Pajak)',
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
function severity(status: string) { return status === 'Settled' || status === 'Grouped Match' ? 'success' : status === 'Ambiguous' ? 'warn' : 'danger' }
function exportCsv() { dataTable.value?.exportCSV() }
</script>

<template>
    <Head title="Reconciliation" />
    <div class="flex flex-col gap-6">
        <div><h1 class="text-3xl font-bold">Reconciliation</h1><p class="mt-2 text-color-secondary">Detail Order dan Income dengan pencocokan aman.</p></div>
        <Card>
            <template #content>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row">
                    <InputText v-model="orderFilter" aria-label="Filter nomor pesanan" placeholder="Filter No. Pesanan" class="w-full sm:w-80" />
                    <InputText v-model="productFilter" aria-label="Filter nama produk" placeholder="Filter Nama Produk" class="w-full sm:w-80" />
                    </div>
                    <Button
                        label="Export CSV"
                        icon="pi pi-download"
                        severity="secondary"
                        outlined
                        class="w-full sm:w-auto"
                        :disabled="filteredRows.length === 0"
                        @click="exportCsv"
                    />
                </div>
            </template>
        </Card>
        <DataTable
            ref="dataTable"
            :value="filteredRows"
            paginator
            :rows="25"
            :rows-per-page-options="[25, 50, 100]"
            paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
            current-page-report-template="{first}–{last} dari {totalRecords}"
            scrollable
            scroll-height="calc(100vh - 20rem)"
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
            <Column field="settlement_status" header="Status" frozen sortable><template #body="{ data }"><Tag :value="data.settlement_status" :severity="severity(data.settlement_status)" /></template></Column>
            <Column v-for="[field, header] in columns" :key="field" :field="field" sortable>
                <template #header>
                    <span v-tooltip.top="formulaTooltips[field] || undefined">{{ header }}</span>
                </template>
                <template #body="{ data }">
                    <span v-if="field === 'order_product_name'">{{ data[field] }}<span v-if="data.order_variation_name"> - {{ data.order_variation_name }}</span></span>
                    <span v-else-if="money.includes(field)">{{ formatNominal(data[field]) }}</span>
                    <span v-else-if="field.endsWith('_percent')">{{ Number(data[field] || 0).toFixed(2) }}%</span>
                    <span v-else>{{ data[field] ?? 0 }}</span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
