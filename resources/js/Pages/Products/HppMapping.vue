<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Column from 'primevue/column'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import {
    AppDataTable,
    AppDataTableToolbar,
    useDataTableContract,
    type TableColumnMeta,
} from '@/Components/DataTable'
import { buildAnalyticsExportFilename } from '@/utils/exportFilename'

type TemplateUnitOption = {
    value: string
    label: string
    code: string
    conversion: number
    hpp_amount?: number
}

type TemplateOption = {
    value: string
    label: string
    hpp_amount?: number
    units: TemplateUnitOption[]
}

type MappingRow = {
    id: number
    productName: string
    variationName: string
    shopeeProductId: string
    shopeeVariantId: string
    autoMatch: 'manual' | 'exact' | 'normalized' | 'ambiguous' | 'missing'
    candidate: string
    templateProductId: number | null
    templateItemCode: string | null
    templateUnitId: number | null
    templateUnitCode: string | null
    unit: string
    conversion: number
    confidence: number
    status: 'manual' | 'exact' | 'normalized' | 'ambiguous' | 'missing'
}

type Pagination = {
    current_page: number
    per_page: number
    last_page: number
    total: number
}

const props = defineProps<{ rows: MappingRow[]; templateOptions: TemplateOption[]; pagination: Pagination }>()

const allColumns = [
    ['productName', 'Nama Produk Shopee'],
    ['variationName', 'Nama Variasi'],
    ['autoMatch', 'Status Match'],
    ['templateItemCode', 'Pilih Template Item'],
    ['unit', 'Pilih Satuan'],
    ['hpp', 'Harga HPP Produk'],
    ['note', 'Catatan'],
] as const satisfies readonly TableColumnMeta[]

const { globalFilter, multiSortMeta, isLoading, isFullscreen, toggleFullscreen } = useDataTableContract()
const selectedColumns = ref<TableColumnMeta[]>([...allColumns])

const selectedTemplate = ref<Record<number, string | null>>({})
const selectedUnit = ref<Record<number, string | null>>({})
const note = ref<Record<number, string>>({})
const statusFilter = ref<'all' | MappingRow['autoMatch']>('all')

const statusFilterOptions = computed(() => [
    { value: 'all' as const, label: 'Semua Status' },
    { value: 'manual' as const, label: 'Manual' },
    { value: 'exact' as const, label: 'Exact' },
    { value: 'normalized' as const, label: 'Normalized' },
    { value: 'ambiguous' as const, label: 'Ambiguous' },
    { value: 'missing' as const, label: 'Missing' },
])

const syncSelections = () => {
    props.rows.forEach((row) => {
        if (row.templateItemCode && selectedTemplate.value[row.id] === undefined) {
            selectedTemplate.value[row.id] = row.templateItemCode
        }

        if (row.templateUnitCode && selectedUnit.value[row.id] === undefined) {
            selectedUnit.value[row.id] = row.templateUnitCode
        }

        if (selectedTemplate.value[row.id] === undefined) {
            selectedTemplate.value[row.id] = null
        }

        if (selectedUnit.value[row.id] === undefined) {
            selectedUnit.value[row.id] = null
        }
    })
}

watch(() => props.rows, syncSelections, { immediate: true })

function loadData(params: Record<string, unknown> = {}) {
    router.get('/products/hpp-mapping', {
        page: params.page ?? undefined,
        per_page: params.per_page ?? undefined,
        search: globalFilter.value || undefined,
        status: statusFilter.value === 'all' ? undefined : statusFilter.value,
        sort_field: params.sort_field ?? multiSortMeta.value[0]?.field ?? 'productName',
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

const matchSeverity = (match: MappingRow['autoMatch']) => {
    if (match === 'manual') return 'success'
    if (match === 'exact') return 'info'
    if (match === 'normalized') return 'warn'
    if (match === 'ambiguous') return 'danger'
    return 'secondary'
}

const matchLabel = (match: MappingRow['autoMatch']) => {
    if (match === 'manual') return 'Manual'
    if (match === 'exact') return 'Exact'
    if (match === 'normalized') return 'Normalized'
    if (match === 'ambiguous') return 'Ambiguous'
    return 'Missing'
}

const templateOptionsList = ref<TemplateOption[]>(props.templateOptions)
const templateChoices = computed(() => templateOptionsList.value)

const syncingCatalog = ref(false)
const catalogSyncMessage = ref('')
const reallocating = ref(false)
const reallocationMessage = ref('')
const saving = ref(false)
const saveFeedback = ref<string[]>([])

const csrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
    return match ? decodeURIComponent(match[1]) : ''
}

const syncTemplateCatalog = async () => {
    if (syncingCatalog.value) return
    syncingCatalog.value = true

    try {
        const response = await fetch('/products/hpp-mapping/sync-template-catalog', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({}),
        })

        const data = await response.json()

        if (data.templateOptions) {
            templateOptionsList.value = data.templateOptions
        }

        if (data.created > 0) {
            catalogSyncMessage.value = `${data.created} template item baru disinkronkan.`
        } else if (data.ok) {
            catalogSyncMessage.value = 'Katalog template sudah sinkron.'
        } else {
            catalogSyncMessage.value = `Sinkron selesai dengan ${data.errors?.length ?? 0} peringatan.`
        }
    } catch {
        catalogSyncMessage.value = 'Gagal menyinkronkan katalog template.'
    } finally {
        syncingCatalog.value = false
    }
}

const reallocateHpp = async () => {
    if (reallocating.value) return
    reallocating.value = true

    try {
        const response = await fetch('/products/hpp-mapping/reallocate', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({}),
        })

        const data = await response.json()

        if (data.ok) {
            reallocationMessage.value = `HPP diterapkan: ${data.ok_count} ok, ${data.mapping_missing} mapping belum lengkap, ${data.mapping_ambiguous} ambigu, ${data.hpp_missing} HPP kosong, ${data.failed} gagal (dari ${data.total} baris).`
        } else {
            reallocationMessage.value = 'Gagal menerapkan HPP.'
        }
    } catch {
        reallocationMessage.value = 'Gagal menerapkan HPP.'
    } finally {
        reallocating.value = false
    }
}

onMounted(syncTemplateCatalog)

const formatCurrency = (value: number | null | undefined) => {
    const amount = Number(value ?? 0)
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount)
}

const unitOptionsForRow = (row: MappingRow) => {
    const template = templateChoices.value.find((item) => item.value === selectedTemplate.value[row.id])
    return template?.units ?? []
}

const hppDisplayValue = (row: MappingRow) => {
    const selectedTemplateValue = selectedTemplate.value[row.id]
    const selectedUnitValue = selectedUnit.value[row.id]

    if (!selectedTemplateValue || !selectedUnitValue) {
        return '—'
    }

    const template = templateChoices.value.find((item) => item.value === selectedTemplateValue) ?? null
    const unit = template?.units.find((item) => item.value === selectedUnitValue) ?? null

    if (unit && typeof unit.hpp_amount !== 'undefined') {
        return formatCurrency(unit.hpp_amount)
    }

    return '—'
}

const saveMappings = (rows: MappingRow[] = props.rows) => {
    if (saving.value) return
    saving.value = true

    const payload: Record<string, unknown>[] = []
    const skipped: { label: string; reason: string }[] = []

    for (const row of rows) {
        const templateCode = selectedTemplate.value[row.id] ?? null
        const unitCode = selectedUnit.value[row.id] ?? null
        const label = row.productName === '-' ? `Baris #${row.id}` : row.productName

        if (!templateCode) {
            skipped.push({ label, reason: 'template item belum dipilih' })
            continue
        }

        if (!unitCode) {
            skipped.push({ label, reason: 'satuan belum dipilih' })
            continue
        }

        payload.push({
            id: row.id,
            shopee_product_id: row.shopeeProductId === '-' ? null : row.shopeeProductId,
            shopee_variant_id: row.shopeeVariantId === '-' ? null : row.shopeeVariantId,
            shopee_product_name: row.productName === '-' ? null : row.productName,
            shopee_variant_name: row.variationName === '-' ? null : row.variationName,
            template_item_code: templateCode,
            template_product_id: null,
            template_unit_code: unitCode,
            template_unit_id: null,
            manual_override_note: note.value[row.id] ?? '',
        })
    }

    saveFeedback.value = []

    if (skipped.length > 0) {
        const details = skipped
            .slice(0, 5)
            .map((item) => `• ${item.label} — ${item.reason}`)
            .join('\n')
        saveFeedback.value.push(`${skipped.length} baris dilewati karena belum lengkap:\n${details}`)
    }

    if (payload.length === 0) {
        saveFeedback.value.push('Tidak ada baris valid untuk disimpan. Pilih template item dan satuan terlebih dahulu.')
        saving.value = false
        return
    }

    router.post(
        '/products/hpp-mapping',
        { mappings: payload },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                saving.value = false
            },
            onError: (errors) => {
                saveFeedback.value.push(...Object.values(errors))
            },
            onSuccess: () => {
                saveFeedback.value.push(`Manual mapping disimpan untuk ${payload.length} baris.`)
            },
        },
    )
}

const saveManualOverride = (row: MappingRow) => saveMappings([row])

const exportExcel = async () => {
    const XLSX = await import('xlsx')
    const data = props.rows.map((row) => Object.fromEntries(
        selectedColumns.value.map(([field, header]) => {
            let value: unknown

            if (field === 'autoMatch') {
                value = matchLabel(row.autoMatch)
            } else if (field === 'hpp') {
                value = hppDisplayValue(row)
            } else if (field === 'templateItemCode') {
                value = selectedTemplate.value[row.id] ?? ''
            } else if (field === 'unit') {
                value = selectedUnit.value[row.id] ?? ''
            } else if (field === 'note') {
                value = note.value[row.id] ?? ''
            } else {
                value = row[field]
            }

            return [header, value]
        }),
    ))
    const worksheet = XLSX.utils.json_to_sheet(data)
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, 'HPP Mapping')
    XLSX.writeFile(workbook, buildAnalyticsExportFilename('all', 'all'))
}

const visibleRows = computed(() => props.rows || [])
</script>

<template>
    <Head title="HPP Mapping" />

    <div class="flex w-full min-w-0 flex-col gap-6">
        <div v-if="!isFullscreen" class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Master HPP</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Manual Mapping Shopee</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button size="small" severity="secondary" outlined :loading="syncingCatalog" :disabled="syncingCatalog" @click="syncTemplateCatalog">
                    {{ syncingCatalog ? 'Menyinkronkan...' : 'Sync Template Items' }}
                </Button>
                <Button @click="saveMappings(props.rows)" :loading="saving" :disabled="saving">
                    Simpan Manual Override
                </Button>
            </div>
        </div>

        <Card v-if="!isFullscreen">
            <template #content>
                <div v-if="catalogSyncMessage" class="mb-3 text-sm">{{ catalogSyncMessage }}</div>
                <div v-if="reallocationMessage" class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">{{ reallocationMessage }}</div>
                <div v-if="saveFeedback.length > 0" class="mb-3 overflow-x-auto rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p v-for="(message, index) in saveFeedback" :key="index" class="whitespace-pre-line">{{ message }}</p>
                </div>
                <div v-if="templateOptionsList.length === 0" class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Belum ada template item. Periksa hasil import template item (baris pada template_item_rows) untuk akun ini.
                </div>
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium">{{ props.pagination.total }} baris</span>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                        <div class="w-full sm:w-52">
                            <label class="mb-1 block text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Status Match</label>
                            <Select
                                v-model="statusFilter"
                                :options="statusFilterOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full"
                                @change="onFilter"
                            />
                        </div>
                        <div class="flex items-end gap-2">
                            <Button size="small" severity="secondary" :loading="reallocating" :disabled="reallocating" @click="reallocateHpp">
                                {{ reallocating ? 'Menerapkan HPP...' : 'Terapkan HPP ke Order' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </template>
        </Card>

        <div
            class="min-w-0"
            :class="isFullscreen ? 'fixed inset-0 z-50 flex flex-col overflow-hidden bg-surface-0 p-3 shadow-2xl dark:bg-surface-950 sm:p-4' : 'relative'"
        >
            <div v-if="isFullscreen" class="mb-3 flex h-12 shrink-0 items-center justify-between rounded-lg border border-surface-200 bg-surface-0 px-3 dark:border-surface-700 dark:bg-surface-950">
                <div class="flex items-center gap-2">
                    <i class="pi pi-window-maximize text-sm text-color-secondary" aria-hidden="true"></i>
                    <span class="text-sm font-semibold text-color">Fullscreen HPP Mapping</span>
                </div>
                <Button
                    label="Keluar Fullscreen"
                    icon="pi pi-window-minimize"
                    severity="secondary"
                    outlined
                    size="small"
                    @click="toggleFullscreen"
                />
            </div>
            <AppDataTableToolbar
                v-model:global-filter="globalFilter"
                v-model:selected-columns="selectedColumns"
                :all-columns="allColumns"
                search-placeholder="Cari nama produk / variasi / SKU..."
                @filter="onFilter"
            >
                <template #actions>
                    <Button label="Export Excel" icon="pi pi-download" severity="secondary" outlined class="h-11 px-3" :disabled="visibleRows.length === 0" @click="exportExcel" />
                    <Button
                        :label="isFullscreen ? 'Keluar Fullscreen' : 'Fullscreen'"
                        :icon="isFullscreen ? 'pi pi-window-minimize' : 'pi pi-window-maximize'"
                        severity="secondary"
                        outlined
                        class="h-11 px-3"
                        @click="toggleFullscreen"
                    />
                </template>
            </AppDataTableToolbar>
            <div
                class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-950"
                :style="{ height: isFullscreen ? '100%' : 'min(70vh, 48rem)' }"
            >
                <AppDataTable
                    :loading="isLoading"
                    :value="visibleRows"
                    v-model:multi-sort-meta="multiSortMeta"
                    lazy
                    :total-records="props.pagination.total"
                    :first="(props.pagination.current_page - 1) * props.pagination.per_page"
                    data-key="id"
                    @page="onPage"
                    @sort="onSort"
                    @filter="onFilter"
                    paginator
                    :rows="props.pagination.per_page"
                    :rows-per-page-options="[25, 50, 100]"
                    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                    current-page-report-template="{first}–{last} dari {totalRecords}"
                    table-style-min-width="128rem"
                >
                    <template #empty>Tidak ada produk yang cocok.</template>
                    <Column v-for="[field, header] in selectedColumns" :key="field" :field="field" :header="header" :sortable="['productName', 'variationName', 'autoMatch', 'unit'].includes(field)">
                        <template #body="slotProps">
                            <template v-if="field === 'productName'">{{ slotProps.data.productName }}</template>
                            <template v-else-if="field === 'variationName'">{{ slotProps.data.variationName }}</template>
                            <Tag v-else-if="field === 'autoMatch'" :value="matchLabel(slotProps.data.autoMatch)" :severity="matchSeverity(slotProps.data.autoMatch)" />
                            <Select
                                v-else-if="field === 'templateItemCode'"
                                :model-value="selectedTemplate[slotProps.data.id] ?? null"
                                @update:model-value="selectedTemplate[slotProps.data.id] = $event ?? null"
                                :options="templateChoices"
                                option-label="label"
                                option-value="value"
                                placeholder="Pilih template item"
                                show-clear
                                filter
                                filter-placeholder="Cari template item..."
                                class="w-full"
                            />
                            <Select
                                v-else-if="field === 'unit'"
                                :model-value="selectedUnit[slotProps.data.id] ?? null"
                                @update:model-value="selectedUnit[slotProps.data.id] = $event ?? null"
                                :options="unitOptionsForRow(slotProps.data)"
                                option-label="label"
                                option-value="value"
                                placeholder="Pilih satuan"
                                show-clear
                                filter
                                filter-placeholder="Cari satuan..."
                                class="w-full"
                                :disabled="!selectedTemplate[slotProps.data.id]"
                            />
                            <div v-else-if="field === 'hpp'" class="min-w-[120px] font-semibold text-slate-800">
                                {{ hppDisplayValue(slotProps.data) }}
                            </div>
                            <textarea
                                v-else
                                v-model="note[slotProps.data.id]"
                                rows="2"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:outline-none"
                                placeholder="Alasan override manual"
                            />
                        </template>
                    </Column>
                </AppDataTable>
            </div>
        </div>
    </div>
</template>