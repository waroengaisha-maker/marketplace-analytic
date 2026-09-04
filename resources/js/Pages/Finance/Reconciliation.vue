<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { formatNominal } from '@/utils/formatters'

type Row = Record<string, any>
const props = defineProps<{ rows: Row[] }>()
const orderFilter = ref('')
const productFilter = ref('')
const sortKey = ref('item_index')
const sortDirection = ref<'asc' | 'desc'>('asc')
const columns = [
    { key: 'item_index', label: 'No. Urut' }, { key: 'settlement_status', label: 'Status Settlement' }, { key: 'order_number', label: 'No. Pesanan' },
    { key: 'order_product_name', label: 'Nama Produk' }, { key: 'net_quantity', label: 'Jumlah Bersih' },
    { key: 'discounted_price', label: 'Harga (@)' }, { key: 'quantity', label: 'Jumlah' }, { key: 'returned_quantity', label: 'Retur' },
    { key: 'order_subtotal', label: 'Subtotal' }, { key: 'platform_fee', label: 'Biaya Administrasi' }, { key: 'admin_fee_percent', label: 'Biaya Administrasi (%)' },
    { key: 'free_shipping_xtra_fee', label: 'Biaya Gratis Ongkir XTRA' }, { key: 'free_shipping_xtra_fee_percent', label: 'Biaya Gratis Ongkir XTRA (%)' },
    { key: 'promo_xtra_service_fee', label: 'Biaya Promo XTRA' }, { key: 'promo_xtra_fee_percent', label: 'Biaya Promo XTRA (%)' },
    { key: 'fee_subtotal', label: 'Subtotal Biaya' }, { key: 'fee_per_unit', label: 'Subtotal Biaya (@)' },
    { key: 'order_processing_fee', label: 'Biaya Proses Pesanan' }, { key: 'total_fee', label: 'Total Biaya' }, { key: 'tax', label: 'Pajak' },
]
const sortableColumns = columns.map((column) => column.key)
const filteredRows = computed(() => [...(props.rows || [])].filter((row) => String(row.order_number || '').toLowerCase().includes(orderFilter.value.toLowerCase()) && String(row.order_product_name || row.product_name || '').toLowerCase().includes(productFilter.value.toLowerCase())).sort((a, b) => { const result = String(a[sortKey.value] ?? '').localeCompare(String(b[sortKey.value] ?? ''), 'id', { numeric: true, sensitivity: 'base' }); return sortDirection.value === 'asc' ? result : -result }))
function sortBy(key: string) { if (sortKey.value === key) sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'; else { sortKey.value = key; sortDirection.value = 'asc' } }
</script>
<template>
    <Head title="Reconciliation" />
    <div><div class="mb-6"><h1 class="text-2xl font-bold">Reconciliation</h1><p class="mt-2 text-sm text-slate-500">Detail Order dan Income berdasarkan user, nomor pesanan, nama produk, dan item index.</p></div>
        <div class="mb-4 flex flex-wrap gap-3"><input v-model="orderFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Filter No. Pesanan" /><input v-model="productFilter" class="min-w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Filter Nama Produk" /></div>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="min-w-[1900px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th v-for="column in columns" :key="column.key" class="cursor-pointer p-3" @click="sortBy(column.key)">{{ column.label }}</th></tr></thead><tbody><tr v-for="(row, index) in filteredRows" :key="row.order_number + '-' + row.item_index + '-' + (row.variation_key || '')" class="border-t"><td class="p-3">{{ index + 1 }}</td><td class="whitespace-nowrap p-3">{{ row.settlement_status }}</td><td class="whitespace-nowrap p-3">{{ row.order_number }}</td><td class="min-w-80 p-3">{{ row.order_product_name }}<span v-if="row.order_variation_name"> - {{ row.order_variation_name }}</span></td><td class="p-3">{{ row.net_quantity }}</td><td class="whitespace-nowrap p-3">{{ formatNominal(row.discounted_price) }}</td><td class="p-3">{{ row.quantity ?? 0 }}</td><td class="p-3">{{ row.returned_quantity ?? 0 }}</td><td class="whitespace-nowrap p-3">{{ formatNominal(row.order_subtotal) }}</td><td class="whitespace-nowrap p-3">{{ formatNominal(row.platform_fee) }}</td><td class="p-3">{{ row.admin_fee_percent.toFixed(2) }}%</td><td class="whitespace-nowrap p-3">{{ formatNominal(row.free_shipping_xtra_fee) }}</td><td class="p-3">{{ row.free_shipping_xtra_fee_percent.toFixed(2) }}%</td><td class="whitespace-nowrap p-3">{{ formatNominal(row.promo_xtra_service_fee) }}</td><td class="p-3">{{ row.promo_xtra_fee_percent.toFixed(2) }}%</td><td class="whitespace-nowrap p-3">{{ formatNominal(row.fee_subtotal) }}</td><td class="whitespace-nowrap p-3">{{ formatNominal(row.fee_per_unit) }}</td><td class="whitespace-nowrap p-3">{{ formatNominal(row.order_processing_fee) }}</td><td class="whitespace-nowrap p-3 font-semibold">{{ formatNominal(row.total_fee) }}</td><td class="whitespace-nowrap p-3">{{ formatNominal(row.tax) }}</td></tr><tr v-if="!filteredRows.length"><td colspan="20" class="p-10 text-center text-slate-500">Belum ada data rekonsiliasi.</td></tr></tbody></table></div></div>
</template>