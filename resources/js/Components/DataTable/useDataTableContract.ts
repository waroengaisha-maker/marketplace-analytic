import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { FilterMatchMode } from '@primevue/core/api'

export type TableColumnMeta = readonly [field: string, header: string]

export type GlobalFilter = { value: string | null; matchMode: string }

let routerListenersRegistered = false

/**
 * Module-scoped loading state: the "start"/"finish" listeners are registered
 * once and must keep driving the SAME ref for every datatable page. A ref
 * created per `useDataTableContract()` call would only ever be updated for the
 * first page that invoked it, silently breaking the overlay on subsequent pages.
 */
const isLoading = ref(false)

/**
 * Contract: datatable pages should behave as the Finance/Reconciliation
 * reference page — server-side (lazy) with multi-sort and an overlay loading
 * state that follows Inertia visits.
 */
export function useDataTableContract() {
    const globalFilter = ref<string | null>(null)
    const multiSortMeta = ref<{ field: string; order: number }[]>([])
    const selectedRows = ref<unknown[]>([])

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
        selectedRows,
        isLoading,
        clearGlobalFilter,
        pageToWindow,
        buildGlobalFilters,
    }
}