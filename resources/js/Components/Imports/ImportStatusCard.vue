<script setup lang="ts">
import { computed } from 'vue'
import Button from 'primevue/button'
import Timeline from 'primevue/timeline'
import StatusBadge from '@/Components/StatusBadge.vue'

type ImportStatus = 'queued' | 'processing' | 'completed' | 'failed'

type ImportOperation = {
    id: number
    status: ImportStatus
    orders: number
    income: number
    error?: string | null
}

type TimelineStep = {
    key: 'upload' | 'processing' | 'completed'
    label: string
    description: string
    icon: string
    state: 'completed' | 'active' | 'pending' | 'failed'
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

const timelineSteps = computed<TimelineStep[]>(() => {
    const status = props.operation.status

    return [
        {
            key: 'upload',
            label: 'Upload',
            description: 'File berhasil diterima.',
            icon: 'pi pi-check',
            state: 'completed',
        },
        {
            key: 'processing',
            label: status === 'queued' ? 'Menunggu' : 'Proses',
            description: status === 'queued'
                ? 'Menunggu worker untuk diproses.'
                : 'Laporan sedang diproses.',
            icon: status === 'failed' ? 'pi pi-times' : status === 'queued' ? 'pi pi-clock' : 'pi pi-cog',
            state: status === 'failed' ? 'failed' : status === 'queued' ? 'active' : 'completed',
        },
        {
            key: 'completed',
            label: 'Selesai',
            description: status === 'completed'
                ? 'Hasil import sudah tersedia.'
                : 'Menunggu proses import selesai.',
            icon: status === 'completed' ? 'pi pi-check' : 'pi pi-circle',
            state: status === 'completed' ? 'completed' : 'pending',
        },
    ]
})
</script>

<template>
    <section
        class="rounded-xl bg-[var(--p-card-background)] p-5 shadow-sm"
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
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
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
                    </div>

                    <Button
                        v-if="isFinished(props.operation.status)"
                        type="button"
                        label="Tutup"
                        icon="pi pi-times"
                        severity="secondary"
                        size="small"
                        class="shrink-0"
                        @click="emit('dismiss')"
                    />
                </div>

                <Timeline
                    :value="timelineSteps"
                    layout="horizontal"
                    align="top"
                    class="mt-6"
                >
                    <template #marker="{ item }">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-full border-2 bg-[var(--p-card-background)] text-sm"
                            :class="{
                                'border-primary bg-primary text-primary-contrast': item.state === 'completed',
                                'border-primary text-primary': item.state === 'active',
                                'border-red-500 text-red-500': item.state === 'failed',
                                'border-surface-300 text-color-secondary dark:border-surface-600': item.state === 'pending',
                            }"
                            aria-hidden="true"
                        >
                            <i :class="item.icon"></i>
                        </span>
                    </template>

                    <template #content="{ item }">
                        <div class="min-w-0 px-2 text-center">
                            <p
                                class="text-sm font-semibold"
                                :class="item.state === 'pending' ? 'text-color-secondary' : 'text-color'"
                            >
                                {{ item.label }}
                            </p>
                            <p class="mt-1 text-xs text-color-secondary">
                                {{ item.description }}
                            </p>
                        </div>
                    </template>
                </Timeline>

                <div
                    v-if="props.operation.status === 'processing'"
                    class="mt-5 h-1 overflow-hidden rounded-full bg-surface-200 dark:bg-surface-700"
                    role="progressbar"
                    aria-label="Import sedang diproses"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div class="h-full w-1/2 animate-pulse rounded-full bg-primary"></div>
                </div>

                <div
                    v-if="props.operation.status === 'completed'"
                    class="mt-5 grid grid-cols-2 gap-3 sm:max-w-md"
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
                    class="mt-4 text-sm text-color-secondary"
                >
                    {{ props.operation.error }}
                </p>
            </div>
        </div>
    </section>
</template>
