<script setup lang="ts">
import DataTable from 'primevue/datatable'
import ProgressSpinner from 'primevue/progressspinner'
import { ref } from 'vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
    tableStyleMinWidth?: string
    loading?: boolean
}>(), {
    tableStyleMinWidth: '108rem',
    loading: false,
})

const dataTable = ref<InstanceType<typeof DataTable> | null>(null)

function exportCSV() {
    dataTable.value?.exportCSV()
}

defineExpose({ exportCSV })
</script>

<template>
    <DataTable
        ref="dataTable"
        :loading="props.loading"
        striped-rows
        row-hover
        resizable-columns
        column-resize-mode="expand"
        reorderable-columns
        removable-sort
        size="large"
        scrollable
        scroll-height="flex"
        :table-style="`min-width: ${props.tableStyleMinWidth}`"
        class="min-h-0 flex-1 text-xs"
        v-bind="$attrs"
    >
        <slot />
        <template v-if="$slots.empty" #empty>
            <slot name="empty" />
        </template>
        <template #loading>
            <slot name="loading">
                <div class="flex h-full min-h-40 w-full items-center justify-center gap-3 bg-surface-0/60 p-8 text-color-secondary backdrop-blur-[1px]">
                    <ProgressSpinner style="width: 2rem; height: 2rem" aria-label="Memuat data" />
                    <span class="text-sm">Memuat data...</span>
                </div>
            </slot>
        </template>
    </DataTable>
</template>