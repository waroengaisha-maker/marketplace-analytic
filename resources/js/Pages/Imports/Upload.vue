<script setup lang="ts">
import PageHeader from '@/Components/PageHeader.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import AppAlert from '../../Components/AppAlert.vue'
import FileUploadCard from '../../Components/FileUploadCard.vue'

type ImportStatus = 'queued' | 'processing' | 'completed' | 'failed'
type ImportOperation = { id: number; status: ImportStatus; orders: number; income: number; error?: string | null }
type Flash = { success?: string; error?: string; import_operation_id?: number }
type PageProps = { flash?: Flash; activeOperation?: ImportOperation | null }

const page = usePage<PageProps>()
const orderForm = useForm<{ order_report: File | null }>({ order_report: null })
const incomeForm = useForm<{ income_report: File | null }>({ income_report: null })
const importStatus = ref<ImportOperation | null>(null)
let pollTimer: ReturnType<typeof setInterval> | undefined
let pollInFlight = false

const operationId = () => page.props.flash?.import_operation_id ?? page.props.activeOperation?.id

async function pollImportStatus() {
    const id = operationId()
    if (!id || pollInFlight) return
    pollInFlight = true
    try {
        const response = await fetch(`/imports/upload/${id}/status`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        if (!response.ok) return
        importStatus.value = await response.json() as ImportOperation
        if (importStatus.value.status === 'completed' || importStatus.value.status === 'failed') stopPolling()
    } catch {
        // A temporary polling failure must not be presented as an import failure.
    } finally {
        pollInFlight = false
    }
}

function stopPolling() {
    if (pollTimer) clearInterval(pollTimer)
    pollTimer = undefined
}

function startPolling() {
    const id = operationId()
    if (!id) return
    importStatus.value = page.props.activeOperation?.id === id ? page.props.activeOperation : null
    void pollImportStatus()
    pollTimer = setInterval(() => void pollImportStatus(), 2000)
}

function resetImportStatus() {
    stopPolling()
    importStatus.value = null
}

function submitOrder() { orderForm.post('/imports/upload', { forceFormData: true }) }
function submitIncome() { incomeForm.post('/imports/upload', { forceFormData: true }) }

onMounted(startPolling)
onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer) })
</script>

<template>
    <Head title="Upload Files" />
    <div class="flex w-full min-w-0 flex-col gap-6">
        <PageHeader
            section="Imports"
            title="Upload Laporan"
            description="Perbarui setiap laporan secara terpisah."
        />
        <AppAlert type="success" :message="page.props.flash?.success" />
        <AppAlert type="error" :message="page.props.flash?.error" />
        <section
            v-if="importStatus"
            class="rounded-xl border border-surface-200 bg-surface-0 p-5 shadow-sm dark:border-surface-700 dark:bg-surface-900"
            role="status"
            aria-live="polite"
        >
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
                    <i :class="[
                        importStatus.status === 'queued' ? 'pi pi-clock'
                            : importStatus.status === 'processing' ? 'pi pi-spin pi-spinner'
                                : importStatus.status === 'completed' ? 'pi pi-check-circle'
                                    : 'pi pi-exclamation-circle',
                        'text-lg',
                    ]"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-color">
                        {{ importStatus.status === 'queued' ? 'Menunggu diproses'
                            : importStatus.status === 'processing' ? 'Sedang memproses laporan'
                                : importStatus.status === 'completed' ? 'Import berhasil' : 'Import gagal' }}
                    </h2>
                    <p class="mt-1 text-sm text-color-secondary">
                        {{ importStatus.status === 'queued' ? 'File berhasil diunggah dan sedang menunggu giliran diproses.'
                            : importStatus.status === 'processing' ? 'Data sedang dibaca dan dimasukkan ke sistem. Mohon tunggu.'
                                : importStatus.status === 'completed' ? 'Data laporan sudah berhasil dimasukkan ke sistem.'
                                    : 'File laporan tidak dapat diproses.' }}
                    </p>
                    <div v-if="importStatus.status === 'completed'" class="mt-4 grid grid-cols-2 gap-3 sm:max-w-md">
                        <div class="rounded-lg border border-surface-200 bg-surface-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                            <p class="text-xs font-medium uppercase tracking-wide text-color-secondary">Order</p>
                            <p class="mt-1 text-xl font-semibold text-color">{{ importStatus.orders }}</p>
                        </div>
                        <div class="rounded-lg border border-surface-200 bg-surface-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                            <p class="text-xs font-medium uppercase tracking-wide text-color-secondary">Income</p>
                            <p class="mt-1 text-xl font-semibold text-color">{{ importStatus.income }}</p>
                        </div>
                    </div>
                    <p v-if="importStatus.status === 'failed' && importStatus.error" class="mt-3 text-sm text-color-secondary">{{ importStatus.error }}</p>
                    <Button v-if="importStatus.status === 'failed'" type="button" label="Tutup" icon="pi pi-times" severity="secondary" class="mt-4" @click="resetImportStatus" />
                </div>
            </div>
        </section>
        <div class="space-y-4">
            <FileUploadCard title="Laporan Order" field="order_report" submit-label="Upload & Import Order" :form="orderForm" @submit="submitOrder" />
            <FileUploadCard title="Laporan Income" description="Sheet Penghasilan" field="income_report" submit-label="Upload & Import Income" :form="incomeForm" @submit="submitIncome" />
        </div>
    </div>
</template>
