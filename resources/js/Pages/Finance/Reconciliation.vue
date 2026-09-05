<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { formatNominal } from '@/utils/formatters'

type Row = Record<string, any>
const props = defineProps<{ rows: Row[] }>()
const orderFilter = ref('')
const productFilter = ref('')
const filteredRows = computed(() => (props.rows || []).filter((row) =>
    String(row.order_number || '').toLowerCase().includes(orderFilter.value.toLowerCase()) &&
    String(row.order_product_name || row.product_name || '').toLowerCase().includes(productFilter.value.toLowerCase()),
))
const money = ['discounted_price', 'order_subtotal', 'platform_fee', 'free_shipping_xtra_fee', 'promo_xtra_service_fee', 'fee_subtotal', 'fee_per_unit', 'order_processing_fee', 'total_fee', 'tax']
const columns = [
    ['item_index', 'No. Urut'], ['order_number', 'No. Pesanan'], ['order_product_name', 'Nama Produk'],
    ['net_quantity', 'Jumlah Bersih'], ['discounted_price', 'Harga (@)'], ['quantity', 'Jumlah'], ['returned_quantity', 'Retur'],
    ['order_subtotal', 'Subtotal'], ['platform_fee', 'Biaya Administrasi'], ['admin_fee_percent', 'Admin (%)'],
    ['free_shipping_xtra_fee', 'Gratis Ongkir'], ['promo_xtra_service_fee', 'Promo XTRA'], ['fee_subtotal', 'Subtotal Biaya'],
    ['fee_per_unit', 'Biaya (@)'], ['order_processing_fee', 'Biaya Proses'], ['total_fee', 'Total Biaya'], ['tax', 'Pajak'],
] as const
function severity(status: string) { return status === 'Settled' || status === 'Grouped Match' ? 'success' : status === 'Ambiguous' ? 'warn' : 'danger' }
</script>

<template>
    <Head title="Reconciliation" />
    <div class="flex flex-col gap-6">
        <div><h1 class="text-3xl font-bold">Reconciliation</h1><p class="mt-2 text-color-secondary">Detail Order dan Income dengan pencocokan aman.</p></div>
        <Card>
            <template #content><div class="flex flex-wrap gap-3"><InputText v-model="orderFilter" placeholder="Filter No. Pesanan" /><InputText v-model="productFilter" placeholder="Filter Nama Produk" class="min-w-80" /></div></template>
        </Card>
        <DataTable :value="filteredRows" paginator :rows="25" :rows-per-page-options="[25, 50, 100]" scrollable striped-rows removable-sort>
            <template #empty>Belum ada data rekonsiliasi.</template>
            <Column field="settlement_status" header="Status" frozen sortable><template #body="{ data }"><Tag :value="data.settlement_status" :severity="severity(data.settlement_status)" /></template></Column>
            <Column field="order_variation_name" header="Variasi" sortable />
            <Column v-for="[field, header] in columns" :key="field" :field="field" :header="header" sortable>
                <template #body="{ data }">
                    <span v-if="field === 'order_product_name'">{{ data[field] }}<span v-if="data.order_variation_name"> - {{ data.order_variation_name }}</span></span>
                    <span v-else-if="money.includes(field)">{{ formatNominal(data[field]) }}</span>
                    <span v-else-if="field === 'admin_fee_percent'">{{ Number(data[field] || 0).toFixed(2) }}%</span>
                    <span v-else>{{ data[field] ?? 0 }}</span>
                </template>
            </Column>
        </DataTable>
    </div>
</template>
