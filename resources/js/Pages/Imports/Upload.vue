<script setup lang="ts">
import PageHeader from '@/Components/PageHeader.vue'
import { useEcho } from '@laravel/echo-vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppAlert from '../../Components/AppAlert.vue'
import FileUploadCard from '../../Components/FileUploadCard.vue'
import ImportStatusCard from '../../Components/Imports/ImportStatusCard.vue'

type ImportStatus = 'queued' | 'processing' | 'completed' | 'failed'
type ImportOperation = { id: number; status: ImportStatus; orders: number; income: number; error?: string | null }
type Flash = { success?: string; error?: string; import_operation_id?: number }
type PageProps = {
    auth: { user: { id: number } }
    flash?: Flash
    activeOperation?: ImportOperation | null
}

const page = usePage<PageProps>()
const orderForm = useForm<{ order_report: File | null }>({ order_report: null })
const incomeForm = useForm<{ income_report: File | null }>({ income_report: null })
const importStatus = ref<ImportOperation | null>(page.props.activeOperation ?? null)

function operationId() {
    return page.props.flash?.import_operation_id ?? page.props.activeOperation?.id ?? importStatus.value?.id
}

async function loadImportStatus() {
    const id = operationId()
    if (!id) return

    try {
        const response = await fetch(`/imports/upload/${id}/status`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })

        if (!response.ok) return

        importStatus.value = await response.json() as ImportOperation
    } catch {
        // Initial snapshot failures must not be presented as import failures.
    }
}

const userId = page.props.auth.user.id

useEcho(
    `imports.${userId}`,
    '.ImportStatusUpdated',
    (event: ImportOperation) => {
        if (event.id === operationId()) {
            importStatus.value = event
        }
    },
)

function resetImportStatus() {
    importStatus.value = null
}

function submitOrder() {
    orderForm.post('/imports/upload', { forceFormData: true })
}

function submitIncome() {
    incomeForm.post('/imports/upload', { forceFormData: true })
}

void loadImportStatus()
</script>

<template>
    <Head title="Upload Files" />
    <div class="flex w-full min-w-0 flex-col gap-6">
        <PageHeader section="Imports" title="Upload Laporan" description="Perbarui setiap laporan secara terpisah." />
        <AppAlert type="success" :message="page.props.flash?.success" />
        <AppAlert type="error" :message="page.props.flash?.error" />
        <ImportStatusCard v-if="importStatus" :operation="importStatus" @dismiss="resetImportStatus" />
        <div class="space-y-4">
            <FileUploadCard
                title="Laporan Order"
                field="order_report"
                submit-label="Upload & Import Order"
                :form="orderForm"
                @submit="submitOrder"
            />
            <FileUploadCard
                title="Laporan Income"
                description="Sheet Penghasilan"
                field="income_report"
                submit-label="Upload & Import Income"
                :form="incomeForm"
                @submit="submitIncome"
            />
        </div>
    </div>
</template>
