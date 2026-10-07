<script setup lang="ts">
import { computed } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'

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
    achieved: boolean
    failed: boolean
}

const props = defineProps<{
    operation: ImportOperation
}>()

const emit = defineEmits<{
    dismiss: []
}>()

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
            achieved: true,
            failed: false,
        },
        {
            key: 'processing',
            label: 'Proses',
            achieved: status === 'completed',
            failed: status === 'failed',
        },
        {
            key: 'completed',
            label: 'Selesai',
            achieved: status === 'completed',
            failed: false,
        },
    ]
})

const progressWidth = computed(() => {
    switch (props.operation.status) {
        case 'processing':
            return '50%'
        case 'completed':
            return '100%'
        case 'failed':
            return '50%'
        default:
            return '0%'
    }
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
        header="File Upload"
        aria-label="File Upload"
        @hide="handleDialogHide"
    >
        <div
            role="status"
            aria-live="polite"
            aria-atomic="true"
            class="flex flex-col gap-7"
        >
            <div class="import-timeline">
                <div
                    class="import-timeline__rail"
                    :style="{ '--import-progress': progressWidth }"
                    aria-hidden="true"
                >
                    <span class="import-timeline__track"></span>
                    <span class="import-timeline__progress"></span>
                </div>

                <div class="import-timeline__steps">
                    <div
                        v-for="step in timelineSteps"
                        :key="step.key"
                        class="import-timeline__step"
                    >
                        <span
                            class="import-timeline__marker"
                            :class="{
                                'import-timeline__marker--achieved': step.achieved,
                                'import-timeline__marker--failed': step.failed,
                            }"
                            aria-hidden="true"
                        >
                            <i :class="step.achieved || step.failed ? 'pi pi-check' : 'pi pi-times'"></i>
                        </span>
                        <span
                            class="import-timeline__label"
                            :class="{
                                'import-timeline__label--muted': !step.achieved && !step.failed,
                            }"
                        >
                            {{ step.label }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="props.operation.status === 'completed'"
                class="import-summary"
            >
                <div class="import-summary__item">
                    <span class="import-summary__icon" aria-hidden="true">
                        <i class="pi pi-shopping-bag"></i>
                    </span>
                    <div>
                        <p class="import-summary__label">Order</p>
                        <p class="import-summary__value">{{ props.operation.orders }}</p>
                    </div>
                </div>

                <div class="import-summary__divider" aria-hidden="true"></div>

                <div class="import-summary__item">
                    <span class="import-summary__icon" aria-hidden="true">
                        <i class="pi pi-wallet"></i>
                    </span>
                    <div>
                        <p class="import-summary__label">Income</p>
                        <p class="import-summary__value">{{ props.operation.income }}</p>
                    </div>
                </div>
            </div>

            <div
                v-if="props.operation.status === 'failed' && props.operation.error"
                class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300"
            >
                {{ props.operation.error }}
            </div>
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
        </template>
    </Dialog>
</template>

<style scoped>
.import-timeline {
    position: relative;
    width: 100%;
    padding: 0.25rem 0 0.5rem;
}

.import-timeline__rail {
    position: absolute;
    top: 1.25rem;
    right: calc(16.666667% + 0.5rem);
    left: calc(16.666667% + 0.5rem);
    height: 2px;
}

.import-timeline__track,
.import-timeline__progress {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
}

.import-timeline__track {
    background: var(--p-surface-300);
}

.import-timeline__progress {
    width: var(--import-progress);
    background: var(--p-green-500);
    transition: width 400ms ease;
}

.import-timeline__steps {
    position: relative;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0;
}

.import-timeline__step {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    gap: 0.7rem;
}

.import-timeline__marker {
    position: relative;
    z-index: 1;
    display: flex;
    width: 2.5rem;
    height: 2.5rem;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--p-surface-400);
    border-radius: 9999px;
    background: var(--p-card-background);
    color: var(--p-text-muted-color);
    font-size: 0.9rem;
    transition: border-color 300ms ease, background-color 300ms ease, color 300ms ease, transform 300ms ease;
}

.import-timeline__marker--achieved {
    border-color: var(--p-green-500);
    background: var(--p-green-500);
    color: white;
}

.import-timeline__marker--failed {
    border-color: var(--p-red-500);
    color: var(--p-red-500);
}

.import-timeline__label {
    color: var(--p-text-color);
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.25;
    text-align: center;
}

.import-timeline__label--muted {
    color: var(--p-text-muted-color);
}

.import-summary {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    width: 100%;
    border-radius: 0.875rem;
    background: var(--p-surface-50);
    padding: 1.25rem 1.5rem;
}

.import-summary__item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.875rem;
}

.import-summary__icon {
    display: flex;
    width: 2.75rem;
    height: 2.75rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    background: color-mix(in srgb, var(--p-green-500) 10%, transparent);
    color: var(--p-green-600);
    font-size: 1.1rem;
}

.import-summary__label {
    margin: 0;
    color: var(--p-text-muted-color);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.import-summary__value {
    margin: 0.15rem 0 0;
    color: var(--p-text-color);
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.1;
}

.import-summary__divider {
    width: 1px;
    height: 3rem;
    background: var(--p-surface-300);
}

@media (max-width: 480px) {
    .import-timeline__rail {
        right: calc(16.666667% + 0.25rem);
        left: calc(16.666667% + 0.25rem);
    }

    .import-summary {
        padding: 1rem;
    }

    .import-summary__item {
        gap: 0.5rem;
    }

    .import-summary__icon {
        width: 2.5rem;
        height: 2.5rem;
    }

    .import-summary__value {
        font-size: 1.25rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .import-timeline__progress,
    .import-timeline__marker {
        transition: none;
    }
}
</style>