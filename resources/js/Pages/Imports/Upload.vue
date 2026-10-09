<script setup lang="ts">
import PageHeader from '@/Components/PageHeader.vue'
import { useEcho } from '@laravel/echo-vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Card from 'primevue/card'
import { computed, ref, watch } from 'vue'
import AppAlert from '../../Components/AppAlert.vue'
import FeedbackMessage from '../../Components/FeedbackMessage.vue'
import ImportStatusCard from '../../Components/Imports/ImportStatusCard.vue'

type ImportStatus = 'queued' | 'processing' | 'completed' | 'failed'
type ReportField = 'order_report' | 'income_report'
type ImportOperation = { id: number; status: ImportStatus; orders: number; income: number; error?: string | null }
type Flash = { success?: string; error?: string; import_operation_id?: number }
type PageProps = { auth: { user: { id: number } }; flash?: Flash; activeOperation?: ImportOperation | null }

const page = usePage<PageProps>()
const form = useForm<{ order_report: File | null; income_report: File | null }>({
    order_report: null,
    income_report: null,
})
const clientErrors = ref<Record<ReportField, string>>({ order_report: '', income_report: '' })
const orderFileInput = ref<HTMLInputElement | null>(null)
const incomeFileInput = ref<HTMLInputElement | null>(null)
const importStatus = ref<ImportOperation | null>(page.props.activeOperation ?? null)
const trackedOperationId = ref<number | null>(page.props.flash?.import_operation_id ?? page.props.activeOperation?.id ?? null)
const optimisticOperationId = 0

const selectedCount = computed(() => Number(!!form.order_report) + Number(!!form.income_report))
const submitLabel = computed(() => {
    if (form.processing) return 'Mengimpor...'
    if (selectedCount.value === 2) return 'Upload Semua'
    if (form.order_report) return 'Upload Order'
    if (form.income_report) return 'Upload Income'
    return 'Pilih laporan untuk diimpor'
})

function validateFile(field: ReportField, file: File | null): boolean {
    clientErrors.value[field] = file === null
        ? ''
        : !/\.(xlsx|xls)$/i.test(file.name)
            ? 'File harus berformat XLSX atau XLS.'
            : file.size > 50 * 1024 * 1024
                ? 'Ukuran file maksimal 50 MB.'
                : ''

    return !clientErrors.value[field]
}

function openFilePicker(field: ReportField) {
    const input = field === 'order_report' ? orderFileInput.value : incomeFileInput.value
    input?.click()
}

function handleFileChange(field: ReportField, event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null

    form[field] = file
    form.clearErrors(field)
    validateFile(field, file)
}

function submit() {
    const orderValid = validateFile('order_report', form.order_report)
    const incomeValid = validateFile('income_report', form.income_report)

    if (!form.order_report && !form.income_report) return
    if (!orderValid || !incomeValid) return

    importStatus.value = {
        id: optimisticOperationId,
        status: 'queued',
        orders: 0,
        income: 0,
    }
    trackedOperationId.value = null

    form.post('/imports/upload', {
        forceFormData: true,
        onSuccess: (page) => {
            const operationId = (page.props.flash as Flash | undefined)?.import_operation_id

            if (operationId) {
                trackedOperationId.value = operationId
                void loadImportStatus(operationId)
            }
        },
        onError: (errors) => {
            // Keep the status dialog for async import failures. Only validation/request
            // errors without an operation should dismiss the optimistic state.
            if (Object.keys(errors).length > 0) {
                importStatus.value = null
                trackedOperationId.value = null
            }
        },
    })
}

async function loadImportStatus(id: number) {
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
        if (event.id === trackedOperationId.value) {
            importStatus.value = event
        }
    },
)

function resetImportStatus() {
    importStatus.value = null
    trackedOperationId.value = null
}

watch(
    () => [page.props.activeOperation, page.props.flash?.import_operation_id] as const,
    ([operation, flashOperationId]) => {
        if (operation) {
            trackedOperationId.value = operation.id
            importStatus.value = operation
            return
        }

        if (flashOperationId) {
            trackedOperationId.value = flashOperationId
            void loadImportStatus(flashOperationId)
            return
        }

        // Do not clear the optimistic dialog just because an intermediate Inertia
        // props update has no operation ID yet. Validation errors are handled by
        // the form's onError callback; successful submissions replace this state
        // when the operation ID or operation snapshot arrives.
    },
    { immediate: true },
)
</script>

<template>
    <Head title="Upload Files" />
    <div class="flex w-full min-w-0 flex-col gap-6">
        <PageHeader
            section="Imports"
            title="Upload Laporan"
        />
        <AppAlert type="error" :message="page.props.flash?.error" />
        <ImportStatusCard v-if="importStatus" :operation="importStatus" @dismiss="resetImportStatus" />

        <Card>
            <template #title>Pilih file laporan</template>

            <template #content>
                <div class="flex flex-col gap-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex min-w-0 flex-col gap-2">
                            <span class="font-medium">Laporan Order <span class="text-sm font-normal text-color-secondary">(opsional)</span></span>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="relative inline-flex">
                                    <input
                                        id="order-report"
                                        ref="orderFileInput"
                                        type="file"
                                        class="sr-only"
                                        accept=".xlsx,.xls"
                                        :disabled="form.processing"
                                        @change="handleFileChange('order_report', $event)"
                                    />
                                    <Button
                                        type="button"
                                        icon="pi pi-file-excel"
                                        label="Pilih file"
                                        :disabled="form.processing"
                                        aria-controls="order-report"
                                        @click="openFilePicker('order_report')"
                                    />
                                </div>
                                <span v-if="form.order_report" class="min-w-0 max-w-full truncate text-sm text-color-secondary">
                                    {{ form.order_report.name }}
                                </span>
                                <span v-else class="text-sm text-color-secondary">XLSX/XLS, maksimal 50 MB</span>
                            </div>
                            <FeedbackMessage
                                v-if="clientErrors.order_report"
                                class="mt-1"
                                severity="error"
                                :message="clientErrors.order_report"
                            />
                            <small v-if="form.errors.order_report" class="p-error block">{{ form.errors.order_report }}</small>
                        </div>

                        <div class="flex min-w-0 flex-col gap-2">
                            <span class="font-medium">Laporan Income <span class="text-sm font-normal text-color-secondary">(opsional)</span></span>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="relative inline-flex">
                                    <input
                                        id="income-report"
                                        ref="incomeFileInput"
                                        type="file"
                                        class="sr-only"
                                        accept=".xlsx,.xls"
                                        :disabled="form.processing"
                                        @change="handleFileChange('income_report', $event)"
                                    />
                                    <Button
                                        type="button"
                                        icon="pi pi-file-excel"
                                        label="Pilih file"
                                        :disabled="form.processing"
                                        aria-controls="income-report"
                                        @click="openFilePicker('income_report')"
                                    />
                                </div>
                                <span v-if="form.income_report" class="min-w-0 max-w-full truncate text-sm text-color-secondary">
                                    {{ form.income_report.name }}
                                </span>
                                <span v-else class="text-sm text-color-secondary">XLSX/XLS, maksimal 50 MB</span>
                            </div>
                            <FeedbackMessage
                                v-if="clientErrors.income_report"
                                class="mt-1"
                                severity="error"
                                :message="clientErrors.income_report"
                            />
                            <small v-if="form.errors.income_report" class="p-error block">{{ form.errors.income_report }}</small>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Button
                            type="button"
                            icon="pi pi-upload"
                            :label="submitLabel"
                            :loading="form.processing"
                            :disabled="selectedCount === 0 || !!clientErrors.order_report || !!clientErrors.income_report"
                            @click="submit"
                        />
                    </div>
                </div>
            </template>
        </Card>
    </div>
</template>
