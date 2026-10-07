<script setup lang="ts">
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'
import StatusBadge from '@/Components/StatusBadge.vue'

type ImportStatus = 'queued' | 'processing' | 'completed' | 'failed'

type ImportOperation = {
    id: number
    status: ImportStatus
    orders: number
    income: number
    error?: string | null
}

const props = defineProps<{
    operation: ImportOperation
}>()

const emit = defineEmits<{
    dismiss: []
}>()

const statusLabel = {
    queued: 'Menunggu diproses',
    processing: 'Sedang memproses',
    completed: 'Import selesai',
    failed: 'Import gagal',
} satisfies Record<ImportStatus, string>

const statusSeverity = {
    queued: 'secondary',
    processing: 'info',
    completed: 'success',
    failed: 'danger',
} satisfies Record<ImportStatus, 'success' | 'secondary' | 'info' | 'danger'>

const statusIcon = {
    queued: 'pi pi-clock',
    processing: 'pi pi-spin pi-spinner',
    completed: 'pi pi-check-circle',
    failed: 'pi pi-exclamation-circle',
} satisfies Record<ImportStatus, string>

const statusDescription = {
    queued: 'File berhasil diterima dan sedang menunggu worker untuk diproses.',
    processing: 'Laporan sedang dibaca dan diproses. Anda dapat tetap berada di halaman ini.',
    completed: 'Seluruh proses import selesai dan hasilnya sudah tersedia di sistem.',
    failed: 'Proses import tidak dapat diselesaikan.',
} satisfies Record<ImportStatus, string>

const isFinished = (status: ImportStatus) => status === 'completed' || status === 'failed'
</script>

<template>
    <section
        class="rounded-xl border border-surface-200 bg-surface-50 p-5 shadow-sm dark:border-surface-700 dark:bg-surface-800"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >
        <div class="flex items-start gap-4">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                aria-hidden="true"
            >
                <i :class="[statusIcon[props.operation.status], 'text-lg']"></i>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-semibold text-color">
                        {{ statusLabel[props.operation.status] }}
                    </h2>
                    <StatusBadge
                        :value="statusLabel[props.operation.status]"
                        :severity="statusSeverity[props.operation.status]"
                        rounded
                        :icon="statusIcon[props.operation.status]"
                    />
                </div>

                <p class="mt-1 text-sm text-color-secondary">
                    {{ statusDescription[props.operation.status] }}
                </p>

                <ProgressBar
                    v-if="props.operation.status === 'processing'"
                    mode="indeterminate"
                    :show-value="false"
                    class="mt-4 h-1.5"
                    aria-label="Import sedang diproses"
                />

                <div
                    v-if="props.operation.status === 'completed'"
                    class="mt-4 grid grid-cols-2 gap-3 sm:max-w-md"
                >
                    <div class="rounded-lg border border-surface-200 bg-surface-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-color-secondary">Order</p>
                        <p class="mt-1 text-xl font-semibold text-color">{{ props.operation.orders }}</p>
                    </div>
                    <div class="rounded-lg border border-surface-200 bg-surface-50 px-4 py-3 dark:border-surface-700 dark:bg-surface-800">
                        <p class="text-xs font-medium uppercase tracking-wide text-color-secondary">Income</p>
                        <p class="mt-1 text-xl font-semibold text-color">{{ props.operation.income }}</p>
                    </div>
                </div>

                <p
                    v-if="props.operation.status === 'failed' && props.operation.error"
                    class="mt-3 text-sm text-color-secondary"
                >
                    {{ props.operation.error }}
                </p>

                <div v-if="isFinished(props.operation.status)" class="mt-4">
                    <Button
                        type="button"
                        label="Tutup"
                        icon="pi pi-times"
                        severity="secondary"
                        size="small"
                        @click="emit('dismiss')"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
