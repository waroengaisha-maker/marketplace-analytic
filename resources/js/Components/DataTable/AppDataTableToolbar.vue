<script setup lang="ts">
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import MultiSelect from 'primevue/multiselect'
import Toolbar from 'primevue/toolbar'

defineProps<{
    allColumns?: readonly (readonly [string, string])[]
    searchLabel?: string
    searchPlaceholder?: string
    columnsLabel?: string
}>()

const globalFilter = defineModel<string | null>('globalFilter', { default: null })
const selectedColumns = defineModel<(readonly [string, string])[]>('selectedColumns', { default: () => [] })

const emit = defineEmits<{ filter: [] }>()

const clearButtonClass = 'absolute right-2 top-1/2 z-10 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full border-0 bg-transparent p-0 text-color-secondary transition-colors hover:bg-surface-200 hover:text-color dark:hover:bg-surface-700'
</script>

<template>
    <Toolbar class="mb-3 shrink-0 flex-wrap gap-3 rounded-xl border border-surface-200 bg-surface-0 px-3 py-2 shadow-sm dark:border-surface-700 dark:bg-surface-950">
        <template #start>
            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                <div class="relative w-full min-w-0 sm:w-[20rem] lg:w-[22rem]">
                    <IconField icon-position="left" class="w-full">
                        <InputIcon class="pi pi-search text-sm text-color-secondary" />
                        <InputText
                            v-model="globalFilter"
                            :aria-label="searchLabel ?? 'Filter semua kolom'"
                            :placeholder="searchPlaceholder ?? 'Cari semua kolom...'"
                            class="h-11 w-full pl-10 pr-10 text-sm"
                            @input="emit('filter')"
                            @keyup.enter="emit('filter')"
                        />
                    </IconField>
                    <button
                        v-if="globalFilter"
                        type="button"
                        aria-label="Hapus pencarian"
                        :class="clearButtonClass"
                        @click="globalFilter = null; emit('filter')"
                    >
                        <i class="pi pi-times text-xs" aria-hidden="true"></i>
                    </button>
                </div>
                <div v-if="allColumns && allColumns.length" class="w-full sm:w-[18rem] lg:w-[20rem]">
                    <MultiSelect
                        v-model="selectedColumns"
                        :options="allColumns"
                        option-label="1"
                        :placeholder="columnsLabel ?? 'Pilih kolom'"
                        display="comma"
                        filter
                        :max-selected-labels="2"
                        selected-items-label="{0} kolom dipilih"
                        class="h-11 w-full text-sm"
                        :pt="{
                            root: { class: 'h-11 rounded-md shadow-none' },
                            trigger: { class: 'rounded-md border-surface-300 bg-surface-0 transition-colors hover:border-primary dark:bg-surface-950' },
                            panel: { class: 'text-sm' },
                            item: { class: 'py-2' },
                            header: { class: 'px-3 py-2' },
                        }"
                    />
                </div>
                <slot name="filters" />
                <slot name="start" />
            </div>
        </template>
        <template #end>
            <div class="ml-auto flex w-full max-w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
                <slot name="actions" />
            </div>
        </template>
    </Toolbar>
</template>