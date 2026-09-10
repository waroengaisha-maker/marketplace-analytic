<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

type Row = Record<string, unknown>
type Pagination = { current_page: number; per_page: number; total: number; last_page: number }

const props = defineProps<{ rows: Row[]; pagination: Pagination; filters: Record<string, string | null> }>()
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.statuses ?? null)
const statuses = ['Matched', 'Orphan', 'Ambiguous']

function applyFilters(): void {
    router.get('/finance/income-reconciliation', {
        search: search.value || undefined,
        statuses: status.value ? [status.value] : undefined,
        from: props.filters.from ?? undefined,
        to: props.filters.to ?? undefined,
        page: 1,
    }, { preserveState: true, preserveScroll: true })
}

function value(row: Row, field: string): string {
    const raw = row[field]
    return raw === null || raw === undefined || raw === '' ? '-' : String(raw)
}

function money(row: Row, field: string): string {
    const raw = Number(row[field] ?? 0)
    return Number.isFinite(raw) ? raw.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }) : '-'
}

function severity(statusValue: string): string {
    return statusValue === 'Matched' ? 'success' : statusValue === 'Orphan' ? 'warn' : 'danger'
}
</script>

<template>
    <Head title="Income Reconciliation" />
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-3xl font-bold">Income Reconciliation</h1>
            <p class="mt-2 text-color-secondary">Income-side matching, orphan, dan refund evidence.</p>
        </div>

        <Card>
            <template #content>
                <div class="flex flex-col gap-3 md:flex-row md:items-end">
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <label for="income-search" class="text-xs font-medium text-color-secondary">Cari order atau produk</label>
                        <InputText id="income-search" v-model="search" placeholder="Cari..." @keyup.enter="applyFilters" />
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <label for="income-status" class="text-xs font-medium text-color-secondary">Income status</label>
                        <Select id="income-status" v-model="status" :options="statuses" placeholder="Semua status" show-clear />
                    </div>
                    <Button label="Terapkan" icon="pi pi-filter" @click="applyFilters" />
                </div>
            </template>
        </Card>

        <Card>
            <template #content>
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-sm text-color-secondary">{{ pagination.total.toLocaleString('id-ID') }} income rows</span>
                    <span class="text-xs text-color-secondary">Status dan refund dipisahkan dari Order business status</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[72rem] text-left text-sm">
                        <thead>
                            <tr class="border-b border-surface">
                                <th class="p-3">Status</th>
                                <th class="p-3">Order</th>
                                <th class="p-3">Produk</th>
                                <th class="p-3">Match</th>
                                <th class="p-3">Confidence</th>
                                <th class="p-3 text-right">Income</th>
                                <th class="p-3 text-right">Refund</th>
                                <th class="p-3">Refund Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="String(row.id)" class="border-b border-surface">
                                <td class="p-3"><Tag :value="value(row, 'income_match_status')" :severity="severity(value(row, 'income_match_status'))" /></td>
                                <td class="p-3">{{ value(row, 'order_number') }}</td>
                                <td class="p-3">{{ value(row, 'product_name') }}</td>
                                <td class="p-3">{{ value(row, 'match_method') }}</td>
                                <td class="p-3">{{ value(row, 'match_confidence') }}</td>
                                <td class="p-3 text-right">{{ money(row, 'total_income') }}</td>
                                <td class="p-3 text-right">{{ money(row, 'refund_amount') }}</td>
                                <td class="p-3">{{ value(row, 'refund_type') }}</td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="p-6 text-center text-color-secondary">Belum ada data income.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </Card>
    </div>
</template>
