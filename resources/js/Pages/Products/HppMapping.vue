<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'

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
    status: 'approved' | 'review' | 'missing'
}

const props = defineProps<{ rows: MappingRow[]; templateOptions: TemplateOption[] }>()

const selectedTemplate = ref<Record<number, string | null>>({})
const selectedUnit = ref<Record<number, string | null>>({})
const note = ref<Record<number, string>>({})
const searchQuery = ref('')

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

const filteredRows = computed(() => {
    const query = searchQuery.value.trim().toLowerCase()

    if (!query) {
        return props.rows
    }

    return props.rows.filter((row) => {
        const haystack = [
            row.productName,
            row.variationName,
            row.shopeeProductId,
            row.shopeeVariantId,
            row.candidate,
        ].join(' ').toLowerCase()

        return haystack.includes(query)
    })
})

const summary = computed(() => ({
    total: props.rows.length,
    approved: props.rows.filter((row) => row.status === 'approved').length,
    review: props.rows.filter((row) => row.status === 'review').length,
    missing: props.rows.filter((row) => row.status === 'missing').length,
}))

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

const templateChoices = computed(() => props.templateOptions)

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

const selectedTemplateOption = (row: MappingRow) => {
    if (!row.templateItemCode) {
        return null
    }

    return templateChoices.value.find((item) => item.value === row.templateItemCode) ?? null
}

const selectedUnitOption = (row: MappingRow) => {
    if (!row.templateItemCode) {
        return null
    }

    const template = selectedTemplateOption(row)
    if (!template) {
        return null
    }

    return template.units.find((unit) => unit.value === row.templateUnitCode) ?? null
}

const hppDisplayValue = (row: MappingRow) => {
    const selectedTemplateValue = selectedTemplate.value[row.id]
    const selectedUnitValue = selectedUnit.value[row.id]

    if (!selectedTemplateValue) {
        return '—'
    }

    const template = templateChoices.value.find((item) => item.value === selectedTemplateValue) ?? null
    const unit = template?.units.find((item) => item.value === selectedUnitValue) ?? null

    if (unit && typeof unit.hpp_amount !== 'undefined') {
        return formatCurrency(unit.hpp_amount)
    }

    if (template && typeof template.hpp_amount !== 'undefined') {
        return formatCurrency(template.hpp_amount)
    }

    return '—'
}

const saveMappings = (rows: MappingRow[] = props.rows) => {
    const payload = rows
        .filter((row) => selectedTemplate.value[row.id] !== null && selectedTemplate.value[row.id] !== undefined)
        .map((row) => ({
            id: row.id,
            shopee_product_id: row.shopeeProductId === '-' ? null : row.shopeeProductId,
            shopee_variant_id: row.shopeeVariantId === '-' ? null : row.shopeeVariantId,
            shopee_product_name: row.productName === '-' ? null : row.productName,
            shopee_variant_name: row.variationName === '-' ? null : row.variationName,
            template_item_code: selectedTemplate.value[row.id] ?? null,
            template_product_id: null,
            template_unit_code: selectedUnit.value[row.id] ?? null,
            template_unit_id: null,
            manual_override_note: note.value[row.id] ?? '',
        }))

    if (payload.length === 0) {
        return
    }

    router.post('/products/hpp-mapping', { mappings: payload }, { preserveScroll: true })
}

const saveManualOverride = (row: MappingRow) => {
    if (selectedTemplate.value[row.id] === null || selectedTemplate.value[row.id] === undefined) {
        return
    }

    saveMappings([row])
}
</script>

<template>
    <Head title="HPP Mapping" />

    <div class="flex w-full min-w-0 flex-col gap-6">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Master HPP</p>
                <h1 class="mt-1 text-3xl font-bold text-slate-900">Manual Mapping Shopee</h1>
            </div>
            <Button @click="saveMappings(props.rows)">
                Simpan Manual Override
            </Button>
        </div>

        <Card>
            <template #content>
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="w-full max-w-md">
                        <label class="mb-1 block text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Cari produk Shopee</label>
                        <InputText v-model="searchQuery" placeholder="Cari nama produk / variasi / SKU..." class="w-full" />
                    </div>
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium">{{ filteredRows.length }} tampilan</span>
                    </div>
                </div>
            </template>
        </Card>

        <div class="grid gap-4 md:grid-cols-4">
            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Total Item</p>
                            <p class="mt-2 text-2xl font-bold">{{ summary.total }}</p>
                        </div>
                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Queue</span>
                    </div>
                </template>
            </Card>

            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Approved</p>
                            <p class="mt-2 text-2xl font-bold text-green-600">{{ summary.approved }}</p>
                        </div>
                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">OK</span>
                    </div>
                </template>
            </Card>

            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Review</p>
                            <p class="mt-2 text-2xl font-bold text-amber-600">{{ summary.review }}</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Manual</span>
                    </div>
                </template>
            </Card>

            <Card>
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Missing</p>
                            <p class="mt-2 text-2xl font-bold text-red-600">{{ summary.missing }}</p>
                        </div>
                        <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">Action</span>
                    </div>
                </template>
            </Card>
        </div>

        <Card>
            <template #title>
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold">Tabel Mapping Produk Shopee</h2>
                </div>
            </template>

            <template #content>
                <DataTable :value="filteredRows" striped-rows table-style="min-width: 100%" class="text-sm">
                    <Column field="productName" header="Nama Produk Shopee" />
                    <Column field="variationName" header="Nama Variasi" />
                    <Column field="shopeeProductId" header="Product ID" />
                    <Column field="shopeeVariantId" header="Variant ID" />
                    <Column header="Status Match">
                        <template #body="slotProps">
                            <Tag :value="matchLabel(slotProps.data.autoMatch)" :severity="matchSeverity(slotProps.data.autoMatch)" />
                        </template>
                    </Column>
                    <Column header="Pilih Template Item">
                        <template #body="slotProps">
                            <Select
                                :model-value="selectedTemplate[slotProps.data.id] ?? null"
                                @update:model-value="selectedTemplate[slotProps.data.id] = $event ?? null"
                                :options="props.templateOptions"
                                option-label="label"
                                option-value="value"
                                placeholder="Pilih template item"
                                show-clear
                                filter
                                filter-placeholder="Cari template item..."
                                class="w-full"
                            />
                        </template>
                    </Column>
                    <Column header="Pilih Satuan">
                        <template #body="slotProps">
                            <Select
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
                        </template>
                    </Column>
                    <Column header="Harga HPP Produk">
                        <template #body="slotProps">
                            <div class="min-w-[120px] font-semibold text-slate-800">
                                {{ hppDisplayValue(slotProps.data) }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Catatan">
                        <template #body="slotProps">
                            <textarea
                                v-model="note[slotProps.data.id]"
                                rows="2"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-blue-500 focus:outline-none"
                                placeholder="Alasan override manual"
                            />
                        </template>
                    </Column>
                    <Column header="Aksi">
                        <template #body="slotProps">
                            <Button size="small" @click="saveManualOverride(slotProps.data)">
                                Save
                            </Button>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
