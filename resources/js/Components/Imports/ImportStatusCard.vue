<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Skeleton from 'primevue/skeleton'

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
const PROCESSING_MIN_DURATION = 600
const FINISH_REVEAL_DELAY = 1000

type TimelinePhase = 'uploading' | 'uploaded' | 'processing' | 'completed' | 'failed'

const timelinePhase = ref<TimelinePhase>('uploading')
const showTransfer = ref(false)
const summaryVisible = ref(false)
const pointBFillActive = ref(false)
let phaseTimer: ReturnType<typeof setTimeout> | null = null
let processingTimer: ReturnType<typeof setTimeout> | null = null
let summaryTimer: ReturnType<typeof setTimeout> | null = null
let transferOperationId: number | null = null

function clearTimers() {
    if (phaseTimer) {
        clearTimeout(phaseTimer)
        phaseTimer = null
    }

    if (processingTimer) {
        clearTimeout(processingTimer)
        processingTimer = null
    }

    if (summaryTimer) {
        clearTimeout(summaryTimer)
        summaryTimer = null
    }

}

function finishTransfer() {
    showTransfer.value = false
    pointBFillActive.value = false
    phaseTimer = null
    timelinePhase.value = 'processing'

    if (!isFinished(props.operation.status)) {
        return
    }

    processingTimer = setTimeout(() => {
        processingTimer = null

        if (props.operation.status === 'completed') {
            timelinePhase.value = 'completed'
        } else if (props.operation.status === 'failed') {
            timelinePhase.value = 'failed'
        }

        summaryTimer = setTimeout(() => {
            summaryVisible.value = true
            summaryTimer = null
        }, FINISH_REVEAL_DELAY)
    }, PROCESSING_MIN_DURATION)
}

function scheduleSummary() {
    if (summaryVisible.value || summaryTimer) {
        return
    }

    summaryTimer = setTimeout(() => {
        summaryVisible.value = true
        summaryTimer = null
    }, FINISH_REVEAL_DELAY)
}

function startTransfer(operationId: number) {
    if (transferOperationId === operationId) {
        return
    }

    transferOperationId = operationId
    summaryVisible.value = false
    pointBFillActive.value = false
    clearTimers()
    timelinePhase.value = 'uploaded'

    showTransfer.value = true

    phaseTimer = setTimeout(() => {
        finishTransfer()
    }, TRANSFER_DURATION)
}

function syncTimelinePhase(operation: ImportOperation) {
    if (operation.id === 0) {
        clearTimers()
        transferOperationId = null
        showTransfer.value = false
        summaryVisible.value = false
        pointBFillActive.value = false
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

    if (timelinePhase.value === 'processing') {
        if (isFinished(operation.status) && !processingTimer) {
            processingTimer = setTimeout(() => {
                processingTimer = null

                if (props.operation.status === 'completed') {
                    timelinePhase.value = 'completed'
                } else if (props.operation.status === 'failed') {
                    timelinePhase.value = 'failed'
                }

                pointBFillActive.value = true

                summaryTimer = setTimeout(() => {
                    summaryVisible.value = true
                    summaryTimer = null
                }, FINISH_REVEAL_DELAY)
            }, PROCESSING_MIN_DURATION)
        }

        return
    }

    if (operation.status === 'completed') {
        timelinePhase.value = 'completed'
        scheduleSummary()
        return
    }

    if (operation.status === 'failed') {
        timelinePhase.value = 'failed'
        scheduleSummary()
        return
    }

    timelinePhase.value = 'processing'
}

watch(
    () => [props.operation.id, props.operation.status],
    () => syncTimelinePhase(props.operation),
    { immediate: true },
)
onBeforeUnmount(clearTimers)

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

const isSummaryRevealed = computed(() => isTimelineFinished.value && summaryVisible.value)
const statusMessage = computed(() => isSummaryRevealed.value ? 'Finished' : 'Please wait...')
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
                            'import-timeline__track--moving': showTransfer,
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
                                    'import-timeline__marker--fill-success': step.key === 'processing' && pointBFillActive && isCompleted,
                                'import-timeline__marker--fill-failed': step.key === 'processing' && pointBFillActive && isFailed,
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
                            <svg v-if="step.active" class="import-timeline__orbit" viewBox="0 0 40 40" aria-hidden="true"><circle class="import-timeline__orbit-track" cx="20" cy="20" r="18" /><circle class="import-timeline__orbit-progress" cx="20" cy="20" r="18" /></svg><span v-if="step.active" class="import-timeline__orbit-dot" aria-hidden="true"></span>
                        </span>
                        <span
                            class="import-timeline__label"
                            :class="{
                                'import-timeline__label--muted': !step.active && !step.achieved && !step.failed,
                                'import-timeline__label--active': step.active,
                                'import-timeline__label--achieved': step.achieved,
                                'import-timeline__label--failed': step.failed,
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
                        isSummaryRevealed
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
                v-if="!summaryVisible"
                class="import-summary import-summary--skeleton"
                aria-hidden="true"
            >
                <div class="import-summary__item">
                    <Skeleton shape="circle" size="2.75rem" />
                    <div class="import-summary__skeleton-content">
                        <Skeleton width="4rem" height="0.7rem" />
                        <Skeleton width="3rem" height="1.35rem" />
                    </div>
                </div>

                <div class="import-summary__divider" aria-hidden="true"></div>

                <div class="import-summary__item">
                    <Skeleton shape="circle" size="2.75rem" />
                    <div class="import-summary__skeleton-content">
                        <Skeleton width="4rem" height="0.7rem" />
                        <Skeleton width="3rem" height="1.35rem" />
                    </div>
                </div>
            </div>

            <Transition name="import-summary">
                <div
                    v-if="summaryVisible && isTimelineFinished"
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
            </Transition>

            <Transition name="import-summary">
                <div
                    v-if="summaryVisible && isTimelineFinished && props.operation.status === 'failed' && props.operation.error"
                    class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300"
                >
                    {{ props.operation.error }}
                </div>
            </Transition>
        </div>

        <template #footer>
            <Skeleton
                v-if="!isSummaryRevealed"
                width="5.5rem"
                height="2.5rem"
                border-radius="0.5rem"
                aria-hidden="true"
            />
            <Button
                v-else
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

.import-timeline__track::after {
    position: absolute;
    inset: 0 auto 0 0;
    width: 0;
    border-radius: inherit;
    background: var(--p-green-500);
    content: '';
}

.import-timeline__track--active {
    background: var(--p-green-500);
}

.import-timeline__track--moving {
    background: var(--p-surface-300);
}

.import-timeline__track--moving::after {
    animation: import-transfer-line 900ms cubic-bezier(0.4, 0, 0.2, 1) forwards;
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

.import-timeline__orbit {
    position: absolute;
    inset: -2px;
    z-index: 3;
    width: calc(100% + 4px);
    height: calc(100% + 4px);
    pointer-events: none;
    overflow: visible;
}

.import-timeline__orbit-track,
.import-timeline__orbit-progress {
    fill: none;
    stroke-linecap: round;
    stroke-width: 2.5;
}

.import-timeline__orbit-track {
    stroke: var(--p-surface-300);
}

.import-timeline__orbit-progress {
    stroke: var(--p-primary-color);
    stroke-dasharray: 113.1;
    stroke-dashoffset: 113.1;
    transform: rotate(-90deg);
    transform-origin: 20px 20px;
    animation: import-marker-orbit-fill 1.6s linear forwards;
}

.import-timeline__orbit-dot {
    position: absolute;
    top: 50%;
    left: 50%;
    z-index: 4;
    width: 0.45rem;
    height: 0.45rem;
    margin: -0.225rem;
    border: 2px solid var(--p-green-500);
    border-radius: 9999px;
    background: white;
    box-shadow: 0 0 10px color-mix(in srgb, var(--p-green-500) 45%, transparent);
    pointer-events: none;
    transform-origin: 0.225rem 0.225rem;
    animation: import-marker-orbit 1.6s linear forwards;
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

.import-timeline__marker--fill-success,
.import-timeline__marker--fill-failed {
    color: white;
}

.import-timeline__marker--fill-success::before,
.import-timeline__marker--fill-failed::before {
    position: absolute;
    inset: 0;
    z-index: 0;
    border-radius: inherit;
    content: '';
    transform-origin: left center;
    animation: import-point-b-fill 350ms ease-out forwards;
}

.import-timeline__marker--fill-success::before {
    background: var(--p-green-500);
}

.import-timeline__marker--fill-failed::before {
    background: var(--p-red-500);
}

.import-timeline__marker > i {
    position: relative;
    z-index: 1;
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

.import-timeline__label--active {
    color: var(--p-primary-color);
    animation: import-label-pulse 1.6s ease-in-out infinite;
}

.import-timeline__label--achieved {
    color: var(--p-green-500);
}

.import-timeline__label--failed {
    color: var(--p-red-500);
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

.import-summary--skeleton {
    min-height: 5.25rem;
}

.import-summary__skeleton-content {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.45rem;
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

@keyframes import-marker-orbit {
    from { transform: rotate(0deg) translateX(1.02rem); }
    to { transform: rotate(360deg) translateX(1.02rem); }
}

@keyframes import-marker-orbit-fill {
    from { stroke-dashoffset: 113.1; }
    to { stroke-dashoffset: 0; }
}

@keyframes import-step-pulse {
    0%, 100% {
        box-shadow: 0 0 0 0 color-mix(in srgb, var(--p-primary-color) 0%, transparent);
    }

    50% {
        box-shadow: 0 0 0 6px color-mix(in srgb, var(--p-primary-color) 12%, transparent);
    }
}

@keyframes import-label-pulse {
    0%, 100% {
        opacity: 0.72;
    }

    50% {
        opacity: 1;
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

@keyframes import-transfer-line {
    from {
        width: 0;
    }

    to {
        width: 100%;
    }
}

.import-summary-enter-active,
.import-summary-leave-active {
    transition: opacity 300ms ease, transform 300ms ease;
}

.import-summary-enter-from,
.import-summary-leave-to {
    opacity: 0;
    transform: translateY(4px);
}

.import-summary-enter-to,
.import-summary-leave-from {
    opacity: 1;
    transform: translateY(0);
}

@media (prefers-reduced-motion: reduce) {
    .import-timeline__marker,
    .import-timeline__marker::before,
    .import-timeline__marker::after,
    .import-timeline__orbit,
    .import-timeline__orbit-progress,
    .import-timeline__orbit-dot,
    .import-timeline__label,
    .import-timeline__track,
    .import-timeline__transfer-dot {
        transition: none;
        animation: none;
    }
}
</style>
