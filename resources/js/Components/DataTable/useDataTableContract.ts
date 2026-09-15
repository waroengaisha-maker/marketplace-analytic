import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { FilterMatchMode } from '@primevue/core/api'

export type TableColumnMeta = readonly [field: string, header: string]

export type GlobalFilter = { value: string | null; matchMode: string }

let routerListenersRegistered = false

/**
 * Contract: datatable pages should behave as the Finance/Reconciliation
 * reference page — server-side (lazy) with a fullscreen toggle, multi-sort,
 * and an overlay loading state that follows Inertia visits.
 */
export function useDataTableContract() {
    const globalFilter = ref<string | null>(null)
    const multiSortMeta = ref<{ field: string; order: number }[]>([])
    const isFullscreen = ref(false)
    const selectedRows = ref<unknown[]>([])
    const isLoading = ref(false)

    if (!routerListenersRegistered) {
        routerListenersRegistered = true
        router.on('start', () => {
            isLoading.value = true
        })
        router.on('finish', () => {
            isLoading.value = false
        })
        router.on('error', () => {
            isLoading.value = false
        })
    }

    const toggleFullscreen = () => {
        isFullscreen.value = !isFullscreen.value
    }

    const clearGlobalFilter = () => {
        globalFilter.value = null
    }

    const pageToWindow = (event: { first: number; rows: number }) => ({
        page: Math.floor(event.first / event.rows) + 1,
        per_page: event.rows,
    })

    const buildGlobalFilters = (columnKeys: readonly string[]): Record<string, GlobalFilter> => ({
        global: { value: globalFilter.value, matchMode: FilterMatchMode.CONTAINS },
        ...Object.fromEntries(columnKeys.map((field) => [field, {
            value: null,
            matchMode: FilterMatchMode.CONTAINS,
        } as GlobalFilter])),
    })

    return {
        globalFilter,
        multiSortMeta,
        isFullscreen,
        selectedRows,
        isLoading,
        toggleFullscreen,
        clearGlobalFilter,
        pageToWindow,
        buildGlobalFilters,
    }
}