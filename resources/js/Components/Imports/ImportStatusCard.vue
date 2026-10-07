<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
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

const props = defineProps<{
    operation: ImportOperation
}>()

const emit = defineEmits<{
    dismiss: []
}>()

const isFinished = (status: ImportStatus) => status === 'completed' || status === 'failed'
const TRANSFER_DURATION = 900

type TimelinePhase = 'uploading' | 'uploaded' | 'processing' | 'completed' | 'failed'

const timelinePhase = ref<TimelinePhase>('uploading')
const showTransfer = ref(false)
let phaseTimer: ReturnType<typeof setTimeout> | null = null
let transferOperationId: number | null = null

function clearPhaseTimer() {
    if (phaseTimer) {
        clearTimeout(phaseTimer)
        phaseTimer = null
    }
}

function finishTransfer() {
    showTransfer.value = false
    phaseTimer = null

    if (props.operation.status === 'completed') {
        timelinePhase.value = 'completed'
        return
    }

    if (props.operation.status === 'failed') {
        timelinePhase.value = 'failed'
        return
    }

    timelinePhase.value = 'processing'
}

function startTransfer(operationId: number) {
    if (transferOperationId === operationId) {
        return
    }

    transferOperationId = operationId
    showTransfer.value = true
    clearPhaseTimer()

    phaseTimer = setTimeout(() => {
        finishTransfer()
    }, TRANSFER_DURATION)
}

function syncTimelinePhase(operation: ImportOperation) {
    if (operation.id === 0) {
        clearPhaseTimer()
        transferOperationId = null
        showTransfer.value = false
        timelinePhase.value = 'uploading'
        return
    }

    if (transferOperationId !== operation.id) {
        timelinePhase.value = 'uploaded'
        startTransfer(operation.id)
        return
    }

    if (showTransfer.value) {
        return
    }

    if (operation.status === 'completed') {
        timelinePhase.value = 'completed'
        return
    }

    if (operation.status === 'failed') {
        timelinePhase.value = 'failed'
        return
    }

    timelinePhase.value = 'processing'
}

watch(
    () => [props.operation.id, props.operation.status],
    () => syncTimelinePhase(props.operation),
    { immediate: true },
)
onBeforeUnmount(clearPhaseTimer)

const isUploading = computed(() => timelinePhase.value === 'uploading')
const isUploaded = computed(() => timelinePhase.value !== 'uploading')
const isProcessing = computed(() => timelinePhase.value === 'processing')
const isTimelineFinished = computed(() => timelinePhase.value === 'completed' || timelinePhase.value === 'failed')
const isCompleted = computed(() => timelinePhase.value === 'completed')
const isFailed = computed(() => timelinePhase.value === 'failed')

function handleDialogHide() {
    if (isFinished(props.operation.status)) {
        emit('dismiss')
    }
}

const timelineSteps = computed(() => [
    {
        key: 'upload',
        label: isUploading.value ? 'Uploading' : 'Uploaded',
        active: isUploading.value,
        achieved: isUploaded.value,
        failed: false,
    },
    {
        key: 'processing',
        label: isProcessing.value
            ? 'Processing'
            : isCompleted.value
                ? 'Successful'
                : isFailed.value
                    ? 'Failed'
                    : 'Processing',
        active: isProcessing.value,
        achieved: isCompleted.value,
        failed: isFailed.value,
    },
])

const statusMessage = computed(() => {
    if (isUploading.value) {
        return 'Uploading...'
    }

    if (timelinePhase.value === 'uploaded') {
        return 'Uploaded'
    }

    if (isProcessing.value) {
        return 'Processing...'
    }

    if (isCompleted.value) {
        return 'Successful'
    }

    return 'Failed'
})
</script>

<template>
    <Dialog
        :visible="true"
        modal
        :closable="false"
        :dismissable-mask="false"
        :close-on-escape="false"
        :draggable="false"
        :style="{ width: 'min(720px, calc(100vw - 2rem))' }"
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
                <div class="import-timeline__rail" aria-hidden="true">
                    <span
                        class="import-timeline__track"
                        :class="{
                            'import-timeline__track--active': isProcessing || isTimelineFinished,
                        }"
                    ></span>
                </div>

                <div
                    v-if="showTransfer"
                    class="import-timeline__transfer"
                    aria-hidden="true"
                >
                    <span class="import-timeline__transfer-dot"></span>
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
                                'import-timeline__marker--active': step.active,
                                'import-timeline__marker--achieved': step.achieved,
                                'import-timeline__marker--failed': step.failed,
                            }"
                            aria-hidden="true"
                        >
                            <i
                                :class="
                                    step.failed
                                        ? 'pi pi-times'
                                        : step.achieved
                                            ? 'pi pi-check'
                                            : step.active
                                                ? 'pi pi-cog'
                                                : 'pi pi-circle'
                                "
                            ></i>
                        </span>
                        <span
                            class="import-timeline__label"
                            :class="{
                                'import-timeline__label--muted': !step.active && !step.achieved && !step.failed,
                            }"
                        >
                            {{ step.label }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center gap-2 text-center">
                <i
                    :class="
                        isTimelineFinished
                            ? isCompleted
                                ? 'pi pi-check-circle text-green-500'
                                : 'pi pi-times-circle text-red-500'
                            : 'pi pi-spin pi-spinner text-primary'
                    "
                    aria-hidden="true"
                ></i>
                <span class="text-sm font-medium text-color">{{ statusMessage }}</span>
            </div>

            <div
                v-if="isTimelineFinished"
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
                v-if="isTimelineFinished && props.operation.status === 'failed' && props.operation.error"
                class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300"
            >
                {{ props.operation.error }}
            </div>
        </div>

        <template #footer>
            <Button
                v-if="isTimelineFinished"
                type="button"
                label="Close"
                :icon="isCompleted ? 'pi pi-check' : 'pi pi-times'"
                :severity="isCompleted ? 'success' : 'secondary'"
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
    right: calc(25% + 1.25rem);
    left: calc(25% + 1.25rem);
    height: 2px;
}

.import-timeline__track {
    position: absolute;
    inset: 0;
    height: 2px;
    border-radius: 9999px;
    background: var(--p-surface-300);
    transition: background-color 300ms ease;
}

.import-timeline__track--active {
    background: var(--p-green-500);
}

.import-timeline__transfer {
    position: absolute;
    top: 1.25rem;
    right: calc(25% + 1.25rem);
    left: calc(25% + 1.25rem);
    z-index: 2;
    height: 2px;
    pointer-events: none;
}

.import-timeline__transfer-dot {
    position: absolute;
    top: 50%;
    left: 0;
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 9999px;
    background: white;
    box-shadow:
        0 0 0 2px var(--p-green-500),
        0 0 10px color-mix(in srgb, var(--p-green-500) 45%, transparent);
    transform: translate(-50%, -50%);
    animation: import-transfer-travel 900ms cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.import-timeline__steps {
    position: relative;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
    background: var(--p-red-500);
    color: white;
}

.import-timeline__marker--active {
    border-color: var(--p-primary-color);
    color: var(--p-primary-color);
    animation: import-step-pulse 1.6s ease-in-out infinite;
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
    .import-timeline__rail,
    .import-timeline__transfer {
        right: calc(25% + 0.75rem);
        left: calc(25% + 0.75rem);
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

@keyframes import-step-pulse {
    0%, 100% {
        box-shadow: 0 0 0 0 color-mix(in srgb, var(--p-primary-color) 0%, transparent);
    }

    50% {
        box-shadow: 0 0 0 6px color-mix(in srgb, var(--p-primary-color) 12%, transparent);
    }
}

@keyframes import-transfer-travel {
    from {
        left: 0;
    }

    to {
        left: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .import-timeline__marker,
    .import-timeline__track,
    .import-timeline__transfer-dot {
        transition: none;
        animation: none;
    }
}
</style>
