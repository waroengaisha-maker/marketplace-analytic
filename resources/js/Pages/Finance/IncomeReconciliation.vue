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
type Filters = { search?: string | null; statuses?: string[] | string | null; refund_type?: string | null; from?: string | null; to?: string | null }

const props = defineProps<{ rows: Row[]; pagination: Pagination; filters: Filters }>()
const search = ref(props.filters.search ?? '')
const status = ref(Array.isArray(props.filters.statuses) ? props.filters.statuses[0] ?? null : props.filters.statuses ?? null)
const refundType = ref(props.filters.refund_type ?? null)
const statuses = ['Matched', 'Orphan', 'Ambiguous']
const refundTypes = ['Full', 'Partial', 'None']

function applyFilters(): void {
    router.get('/finance/income-reconciliation', {
        search: search.value || undefined,
        statuses: status.value ? [status.value] : undefined,
        refund_type: refundType.value || undefined,
        from: props.filters.from ?? undefined,
        to: props.filters.to ?? undefined,
        page: 1,
    }, { preserveState: true, preserveScroll: true })
}

function goToPage(page: number): void {
    if (page < 1 || page > props.pagination.last_page) {
        return
    }

    router.get('/finance/income-reconciliation', {
        search: props.filters.search || undefined,
        statuses: Array.isArray(props.filters.statuses) ? props.filters.statuses : props.filters.statuses ? [props.filters.statuses] : undefined,
        refund_type: props.filters.refund_type || undefined,
        from: props.filters.from || undefined,
        to: props.filters.to || undefined,
        page,
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

function refundSeverity(refundTypeValue: string): string {
    return refundTypeValue === 'Full' ? 'danger' : 'warn'
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
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <label for="income-refund" class="text-xs font-medium text-color-secondary">Refund</label>
                        <Select id="income-refund" v-model="refundType" :options="refundTypes" placeholder="Semua refund" show-clear />
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
                                <td class="p-3">
                                    <Tag v-if="value(row, 'refund_type') !== '-'" :value="`${value(row, 'refund_type')} Refund`" :severity="refundSeverity(value(row, 'refund_type'))" />
                                    <span v-else>-</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="p-6 text-center text-color-secondary">Belum ada data income.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between gap-3">
                    <Button label="Sebelumnya" icon="pi pi-chevron-left" severity="secondary" outlined :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)" />
                    <span class="text-sm text-color-secondary">Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
                    <Button label="Berikutnya" icon="pi pi-chevron-right" icon-pos="right" severity="secondary" outlined :disabled="pagination.current_page === pagination.last_page" @click="goToPage(pagination.current_page + 1)" />
                </div>
            </template>
        </Card>
    </div>
</template>
