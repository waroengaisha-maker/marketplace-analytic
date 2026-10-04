<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
import { formatNominal } from '@/utils/formatters'

type Row = {
    dimension_key: string | null
    dimension_label: string | null
    line_count: number
    hpp_available_line_count: number
    hpp_unavailable_line_count: number
    net_quantity: number | null
    subtotal: number | null
    total_fee: number | null
    tax: number | null
    refund_amount: number | null
    penghasilan: number | null
    hpp: number | null
    laba: number | null
    profit_margin: number | null
    financial_status: 'complete' | 'partial' | 'provisional' | 'unavailable'
}

const props = defineProps<{
    rows: Row[]
    dimension: string
    hasAppliedFilter: boolean
    appliedFrom?: string | null
    appliedTo?: string | null
}>()

const from = ref<Date | null>(props.appliedFrom ? new Date(`${props.appliedFrom}T00:00:00`) : null)
const to = ref<Date | null>(props.appliedTo ? new Date(`${props.appliedTo}T00:00:00`) : null)
const dimension = ref(props.dimension)

const dimensions = [
    { label: 'Produk', value: 'product' },
    { label: 'SKU', value: 'sku' },
    { label: 'Variasi', value: 'variation' },
    { label: 'Hari', value: 'day' },
    { label: 'Bulan', value: 'month' },
]

function dateValue(date: Date | null) {
    if (!date) return undefined

    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

function load() {
    router.get('/analytics/profitability', {
        from: dateValue(from.value),
        to: dateValue(to.value),
        dimension: dimension.value,
    }, { preserveState: true, preserveScroll: true })
}

function statusSeverity(status: Row['financial_status']) {
    return status === 'complete' ? 'success' : status === 'partial' ? 'warn' : status === 'provisional' ? 'info' : 'danger'
}

function statusLabel(status: Row['financial_status']) {
    return ({
        complete: 'Lengkap',
        partial: 'HPP belum lengkap',
        provisional: 'Provisional',
        unavailable: 'Tidak tersedia',
    } as Record<Row['financial_status'], string>)[status]
}
</script>

<template>
    <Head title="Profitability Analytics" />
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-3xl font-bold">Profitability Analytics</h1>
            <p class="mt-2 text-color-secondary">Profitabilitas berdasarkan financial projection canonical dan Master HPP.</p>
        </div>

        <Card>
            <template #content>
                <div class="flex flex-col gap-4">
                    <DateRangeFilter v-model:from="from" v-model:to="to" id-prefix="profitability" />
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex min-w-48 flex-col gap-1">
                            <label for="profitability-dimension" class="text-xs font-medium text-color-secondary">Dimensi</label>
                            <Select
                                input-id="profitability-dimension"
                                v-model="dimension"
                                :options="dimensions"
                                option-label="label"
                                option-value="value"
                                class="h-11"
                            />
                        </div>
                        <Button label="Terapkan" icon="pi pi-filter" class="h-11" @click="load" />
                    </div>
                </div>
            </template>
        </Card>

        <Card v-if="hasAppliedFilter">
            <template #content>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead>
                            <tr class="border-b border-surface">
                                <th class="px-3 py-3 text-left">Dimensi</th>
                                <th class="px-3 py-3 text-right">Qty Bersih</th>
                                <th class="px-3 py-3 text-right">Subtotal</th>
                                <th class="px-3 py-3 text-right">Biaya</th>
                                <th class="px-3 py-3 text-right">Pajak</th>
                                <th class="px-3 py-3 text-right">Refund</th>
                                <th class="px-3 py-3 text-right">Penghasilan</th>
                                <th class="px-3 py-3 text-right">HPP</th>
                                <th class="px-3 py-3 text-right">Laba</th>
                                <th class="px-3 py-3 text-right">Margin</th>
                                <th class="px-3 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="`${row.dimension_key ?? 'null'}-${row.dimension_label ?? ''}`" class="border-b border-surface">
                                <td class="px-3 py-3">
                                    <div class="font-medium">{{ row.dimension_label ?? '(tanpa nilai)' }}</div>
                                    <div class="text-xs text-color-secondary">{{ row.line_count }} line · HPP {{ row.hpp_available_line_count }}/{{ row.line_count }}</div>
                                </td>
                                <td class="px-3 py-3 text-right">{{ row.net_quantity ?? '—' }}</td>
                                <td class="px-3 py-3 text-right">{{ row.subtotal === null ? '—' : formatNominal(row.subtotal) }}</td>
                                <td class="px-3 py-3 text-right">{{ row.total_fee === null ? '—' : formatNominal(row.total_fee) }}</td>
                                <td class="px-3 py-3 text-right">{{ row.tax === null ? '—' : formatNominal(row.tax) }}</td>
                                <td class="px-3 py-3 text-right">{{ row.refund_amount === null ? '—' : formatNominal(row.refund_amount) }}</td>
                                <td class="px-3 py-3 text-right font-medium">{{ row.penghasilan === null ? '—' : formatNominal(row.penghasilan) }}</td>
                                <td class="px-3 py-3 text-right">{{ row.hpp === null ? '—' : formatNominal(row.hpp) }}</td>
                                <td class="px-3 py-3 text-right font-semibold">{{ row.laba === null ? '—' : formatNominal(row.laba) }}</td>
                                <td class="px-3 py-3 text-right">{{ row.profit_margin === null ? '—' : `${row.profit_margin.toFixed(2)}%` }}</td>
                                <td class="px-3 py-3"><Tag :value="statusLabel(row.financial_status)" :severity="statusSeverity(row.financial_status)" /></td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="11" class="px-3 py-8 text-center text-color-secondary">Belum ada data profitability untuk periode ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </Card>
        <Card v-else>
            <template #content>
                <div class="py-8 text-center text-color-secondary">Pilih periode tanggal, lalu klik <strong>Terapkan</strong>.</div>
            </template>
        </Card>
    </div>
</template>
