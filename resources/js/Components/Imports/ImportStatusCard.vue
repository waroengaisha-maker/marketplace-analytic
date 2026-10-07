<script setup lang="ts">
import { computed } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
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
    processing: 'Laporan sedang diproses. Status timeline diperbarui secara real-time.',
    completed: 'Seluruh proses import selesai dan hasilnya sudah tersedia di sistem.',
    failed: 'Proses import tidak dapat diselesaikan.',
} satisfies Record<ImportStatus, string>

const isFinished = (status: ImportStatus) => status === 'completed' || status === 'failed'

function handleDialogHide() {
    if (isFinished(props.operation.status)) {
        emit('dismiss')
    }
}

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
    <Dialog
        :visible="true"
        modal
        :closable="isFinished(props.operation.status)"
        :dismissable-mask="false"
        :close-on-escape="false"
        :draggable="false"
        :style="{ width: 'min(720px, calc(100vw - 2rem))' }"
        :header="statusLabel[props.operation.status]"
        :aria-label="`Status import: ${statusLabel[props.operation.status]}`"
        @hide="handleDialogHide"
    >
        <div
            role="status"
            aria-live="polite"
            aria-atomic="true"
            class="flex flex-col gap-5"
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
                        <StatusBadge
                            :value="statusLabel[props.operation.status]"
                            :severity="statusSeverity[props.operation.status]"
                            rounded
                            :icon="statusIcon[props.operation.status]"
                        />
                    </div>

                    <p class="mt-2 text-sm text-color-secondary">
                        {{ statusDescription[props.operation.status] }}
                    </p>
                </div>
            </div>

            <div
                class="import-timeline"
                :class="`import-timeline--${props.operation.status}`"
            >
                <Timeline
                    :value="timelineSteps"
                    layout="horizontal"
                    align="top"
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
            </div>

            <div
                v-if="props.operation.status === 'queued' || props.operation.status === 'processing'"
                class="flex items-center gap-2 text-xs text-color-secondary"
                aria-live="polite"
            >
                <span
                    class="h-1.5 w-1.5 rounded-full bg-primary"
                    :class="props.operation.status === 'processing' ? 'animate-pulse' : ''"
                    aria-hidden="true"
                ></span>
                <span>
                    {{ props.operation.status === 'queued' ? 'Menunggu worker...' : 'Sedang berjalan • pembaruan otomatis aktif' }}
                </span>
            </div>

            <div
                v-if="props.operation.status === 'completed'"
                class="grid grid-cols-2 gap-3 sm:max-w-md"
            >
                <div class="rounded-lg bg-surface-50 px-4 py-3 dark:bg-surface-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-color-secondary">Order</p>
                    <p class="mt-1 text-xl font-semibold text-color">{{ props.operation.orders }}</p>
                </div>
                <div class="rounded-lg bg-surface-50 px-4 py-3 dark:bg-surface-800">
                    <p class="text-xs font-medium uppercase tracking-wide text-color-secondary">Income</p>
                    <p class="mt-1 text-xl font-semibold text-color">{{ props.operation.income }}</p>
                </div>
            </div>

            <p
                v-if="props.operation.status === 'failed' && props.operation.error"
                class="text-sm text-color-secondary"
            >
                {{ props.operation.error }}
            </p>
        </div>

        <template #footer>
            <Button
                v-if="isFinished(props.operation.status)"
                type="button"
                :label="props.operation.status === 'completed' ? 'Selesai' : 'Tutup'"
                :icon="props.operation.status === 'completed' ? 'pi pi-check' : 'pi pi-times'"
                :severity="props.operation.status === 'completed' ? 'success' : 'secondary'"
                @click="emit('dismiss')"
            />
            <span
                v-else
                class="text-xs text-color-secondary"
            >
                Proses akan tetap terbuka sampai selesai.
            </span>
        </template>
    </Dialog>
</template>

<style scoped>
.import-timeline :deep(.p-timeline-event) {
    min-width: 0;
    flex: 1;
}

.import-timeline :deep(.p-timeline-event-content) {
    min-width: 0;
}

.import-timeline :deep(.p-timeline-event-connector) {
    background: var(--p-surface-300);
    transition: background-color 300ms ease;
}

.import-timeline__marker {
    position: relative;
    z-index: 1;
    transition: transform 300ms ease, background-color 300ms ease, color 300ms ease;
}

.import-timeline__marker--completed {
    background: var(--p-primary-color);
    color: var(--p-primary-contrast-color);
}

.import-timeline__marker--active {
    color: var(--p-primary-color);
    box-shadow: 0 0 0 6px color-mix(in srgb, var(--p-primary-color) 10%, transparent);
    animation: import-timeline-pulse 1.8s ease-in-out infinite;
}

.import-timeline__marker--failed {
    color: var(--p-red-500);
    background: color-mix(in srgb, var(--p-red-500) 10%, var(--p-card-background));
}

.import-timeline__marker--pending {
    color: var(--p-text-muted-color);
}

.import-timeline--queued :deep(.p-timeline-event:nth-child(1) .p-timeline-event-connector) {
    background: linear-gradient(90deg, var(--p-primary-color) 0%, var(--p-primary-color) 50%, var(--p-surface-300) 50%, var(--p-surface-300) 100%);
    background-size: 200% 100%;
    animation: import-timeline-flow 1.6s linear infinite;
}

.import-timeline--processing :deep(.p-timeline-event:nth-child(1) .p-timeline-event-connector) {
    background: var(--p-primary-color);
}

.import-timeline--processing :deep(.p-timeline-event:nth-child(2) .p-timeline-event-connector) {
    background: linear-gradient(90deg, var(--p-primary-color) 0%, color-mix(in srgb, var(--p-primary-color) 35%, var(--p-surface-300)) 50%, var(--p-surface-300) 100%);
    background-size: 200% 100%;
    animation: import-timeline-flow 1.6s linear infinite;
}

.import-timeline--completed :deep(.p-timeline-event-connector) {
    background: var(--p-primary-color);
}

.import-timeline--failed :deep(.p-timeline-event:nth-child(1) .p-timeline-event-connector) {
    background: var(--p-primary-color);
}

.import-timeline--failed :deep(.p-timeline-event:nth-child(2) .p-timeline-event-connector) {
    background: var(--p-red-500);
}

@keyframes import-timeline-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}

@keyframes import-timeline-flow {
    from { background-position: 100% 0; }
    to { background-position: -100% 0; }
}

@media (prefers-reduced-motion: reduce) {
    .import-timeline__marker--active,
    .import-timeline :deep(.p-timeline-event-connector) {
        animation: none;
    }
}
</style>
