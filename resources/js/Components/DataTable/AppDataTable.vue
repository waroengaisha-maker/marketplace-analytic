<script setup lang="ts">
import DataTable from 'primevue/datatable'
import { ref } from 'vue'
import DataTableState from './DataTableState.vue'

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
        <template #empty>
            <slot v-if="$slots.empty" name="empty" />
            <DataTableState v-else state="empty" title="Tidak ada data" />
        </template>
        <template #loading>
            <slot v-if="$slots.loading" name="loading" />
            <DataTableState v-else state="loading" title="Memuat data..." />
        </template>
    </DataTable>
</template>