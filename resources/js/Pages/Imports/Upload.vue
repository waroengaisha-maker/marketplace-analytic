<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import AppAlert from '../../Components/AppAlert.vue'
import FileUploadCard from '../../Components/FileUploadCard.vue'

type Flash = { success?: string; error?: string; import_operation_id?: number }
type ImportStatus = { status: 'queued' | 'processing' | 'completed' | 'failed'; orders: number; income: number; error?: string | null }

const page = usePage<{ flash?: Flash }>()
const orderForm = useForm<{ order_report: File | null }>({ order_report: null })
const incomeForm = useForm<{ income_report: File | null }>({ income_report: null })
const importStatus = ref<ImportStatus | null>(null)
let pollTimer: ReturnType<typeof setInterval> | undefined

async function pollImportStatus() {
    const operationId = page.props.flash?.import_operation_id
    if (!operationId) return
    const response = await fetch(`/imports/upload/${operationId}/status`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    if (!response.ok) return
    const status = await response.json() as ImportStatus
    importStatus.value = status
    if (status.status === 'completed' || status.status === 'failed') {
        if (pollTimer) clearInterval(pollTimer)
        pollTimer = undefined
    }
}

function startPolling() {
    if (!page.props.flash?.import_operation_id) return
    void pollImportStatus()
    pollTimer = setInterval(() => void pollImportStatus(), 2000)
}

function submitOrder() { orderForm.post('/imports/upload', { forceFormData: true }) }
function submitIncome() { incomeForm.post('/imports/upload', { forceFormData: true }) }

onMounted(startPolling)
onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer) })
</script>

<template>
    <Head title="Upload Files" />
    <div class="flex w-full min-w-0 flex-col gap-6">
        <div>
            <h1 class="mt-2 text-3xl font-bold">Upload Laporan</h1>
            <p class="mt-2 text-color-secondary">Perbarui setiap laporan secara terpisah.</p>
        </div>
        <AppAlert type="success" :message="page.props.flash?.success" />
        <AppAlert type="error" :message="page.props.flash?.error" />
        <div v-if="importStatus" class="rounded-lg border p-4">
            <div class="font-semibold">Status import: {{ importStatus.status }}</div>
            <div v-if="importStatus.status === 'completed'" class="mt-1 text-color-secondary">
                Order: {{ importStatus.orders }} baris · Income: {{ importStatus.income }} baris
            </div>
            <div v-if="importStatus.status === 'failed'" class="mt-1 text-color-secondary">{{ importStatus.error }}</div>
        </div>
        <div class="space-y-4">
            <FileUploadCard title="Laporan Order" field="order_report" submit-label="Upload & Import Order" :form="orderForm" @submit="submitOrder" />
            <FileUploadCard title="Laporan Income" description="Sheet Penghasilan" field="income_report" submit-label="Upload & Import Income" :form="incomeForm" @submit="submitIncome" />
        </div>
    </div>
</template>
