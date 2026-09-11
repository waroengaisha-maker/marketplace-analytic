<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

type ProductRow = {
    code: string
    name: string
    baseUnit: string
    units: Array<{ code: string; conversion: number; hpp: string }>
    hpp: string
    status: 'active' | 'ambiguous' | 'missing'
    match: string
}

const props = defineProps<{
    products: ProductRow[]
}>()

const search = ref('')

const filteredProducts = computed(() => {
    const value = search.value.trim().toLowerCase()

    if (!value) {
        return props.products
    }

    return props.products.filter((product) => {
        return [product.code, product.name, product.baseUnit].some((field) => field.toLowerCase().includes(value))
    })
})

const summary = computed(() => ({
    total: props.products.length,
    active: props.products.filter((product) => product.status === 'active').length,
    ambiguous: props.products.filter((product) => product.status === 'ambiguous').length,
    missing: props.products.filter((product) => product.status === 'missing').length,
}))

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
            <template #title>
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold">Produk & HPP</h2>
                    </div>
                    <div class="w-full max-w-sm">
                        <span class="p-input-icon-left w-full">
                            <i class="pi pi-search" />
                            <InputText v-model="search" placeholder="Cari KodeItem / NamaItem" class="w-full" />
                        </span>
                    </div>
                </div>
            </template>

            <template #content>
                <DataTable :value="filteredProducts" striped-rows table-style="min-width: 100%" class="text-sm">
                    <Column field="code" header="KodeItem" />
                    <Column field="name" header="Nama Produk" />
                    <Column field="baseUnit" header="Base Unit" />
                    <Column header="Multi Satuan">
                        <template #body="slotProps">
                            <div class="flex flex-wrap gap-1">
                                <Tag v-for="unit in slotProps.data.units" :key="`${slotProps.data.code}-${unit.code}`" :value="`${unit.code} (${unit.conversion})`" severity="secondary" />
                            </div>
                        </template>
                    </Column>
                    <Column field="hpp" header="HPP Saat Ini" />
                    <Column header="Status Mapping">
                        <template #body="slotProps">
                            <Tag :value="statusLabel(slotProps.data.status)" :severity="statusSeverity(slotProps.data.status)" />
                        </template>
                    </Column>
                    <Column header="Match">
                        <template #body="slotProps">
                            {{ slotProps.data.match }}
                        </template>
                    </Column>
                    <Column header="Aksi">
                        <template #body>
                            <Button severity="secondary" outlined size="small">
                                Detail
                            </Button>
                        </template>
                    </Column>
                </DataTable>
            </template>
        </Card>
    </div>
</template>
