<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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
const POINT_A_FILL_DURATION = 420
const TRANSFER_DURATION = 900
const FINISH_REVEAL_DELAY = 700

type TimelinePhase = 'uploading' | 'uploaded' | 'processing' | 'completed' | 'failed'

const timelinePhase = ref<TimelinePhase>('uploading')
const summaryVisible = ref(false)
const pointAFillActive = ref(false)
const pointBFillActive = ref(false)
const pointAFillProgress = ref(0)
const pointBFillProgress = ref(0)
const fillTimelineProgress = ref(0)
const pointBFillTimelineProgress = ref(0)
const timelineRef = ref<HTMLElement | null>(null)
const arrowPosition = ref({ x: 0, y: 0 })
const arrowVisible = ref(false)

let transferTimer: ReturnType<typeof setTimeout> | null = null
let finishRevealTimer: ReturnType<typeof setTimeout> | null = null
let animationFrame: number | null = null
let fillAnimationFrame: number | null = null
let transferOperationId: number | null = null
let transferStarted = false
let transferStartedAt = 0
let timelineWidth = 0

function clearTimers() {
    if (transferTimer) {
        clearTimeout(transferTimer)
        transferTimer = null
    }
    if (finishRevealTimer) {
        clearTimeout(finishRevealTimer)
        finishRevealTimer = null
    }
}

function stopAnimationFrame() {
    if (animationFrame !== null) {
        cancelAnimationFrame(animationFrame)
        animationFrame = null
    }
    if (fillAnimationFrame !== null) {
        cancelAnimationFrame(fillAnimationFrame)
        fillAnimationFrame = null
    }
}

function updateTimelineWidth() {
    timelineWidth = timelineRef.value?.clientWidth ?? 0
}

function updateArrowPosition() {
    if (timelinePhase.value !== 'uploaded') return

    const timeline = timelineRef.value
    const markers = timeline?.querySelectorAll<HTMLElement>('.import-timeline__marker')
    if (!timeline || !markers || markers.length < 2) return

    const timelineRect = timeline.getBoundingClientRect()
    const startMarkerRect = markers[0].getBoundingClientRect()
    const endMarkerRect = markers[1].getBoundingClientRect()

    const elapsed = transferStartedAt > 0 ? performance.now() - transferStartedAt : 0
    const progress = Math.min(Math.max(elapsed / TRANSFER_DURATION, 0), 1)
    const markerRadius = startMarkerRect.width / 2
    const startCenterX = startMarkerRect.left + markerRadius - timelineRect.left
    const endCenterX = endMarkerRect.left + endMarkerRect.width / 2 - timelineRect.left
    const startX = startCenterX + markerRadius
    const endX = endCenterX - markerRadius
    const rail = timeline.querySelector<HTMLElement>('.import-timeline__rail')
    const railRect = rail?.getBoundingClientRect()
    const centerY = railRect
        ? railRect.top + railRect.height / 2 - timelineRect.top
        : startMarkerRect.top + startMarkerRect.height / 2 - timelineRect.top

    arrowPosition.value = {
        x: startX + (endX - startX) * progress,
        y: centerY,
    }
}

function animationLoop() {
    updateTimelineWidth()
    updateArrowPosition()
    animationFrame = requestAnimationFrame(animationLoop)
}

function resetTimeline() {
    clearTimers()
    stopAnimationFrame()
    transferOperationId = null
    transferStarted = false
    summaryVisible.value = false
    pointAFillActive.value = false
    pointBFillActive.value = false
    pointAFillProgress.value = 0
    pointBFillProgress.value = 0
    fillTimelineProgress.value = 0
    timelinePhase.value = 'uploading'
    arrowVisible.value = false
    transferStartedAt = 0
    arrowPosition.value = { x: 0, y: 0 }
    updateTimelineWidth()
}

function completeTransfer(operation: ImportOperation) {
    transferTimer = null
    if (isFinished(operation.status)) {
        timelinePhase.value = operation.status === 'completed' ? 'completed' : 'failed'
        pointBFillActive.value = true
        pointBFillProgress.value = 1
        arrowVisible.value = false
        finishRevealTimer = setTimeout(() => {
            finishRevealTimer = null
            summaryVisible.value = true
        }, FINISH_REVEAL_DELAY)
        return
    }
    timelinePhase.value = 'processing'
    arrowVisible.value = false
    pointBFillProgress.value = 0
}

function animateFillAndTransfer() {
    const startedAt = performance.now()

    const tick = (now: number) => {
        const progress = Math.min((now - startedAt) / POINT_A_FILL_DURATION, 1)
        fillTimelineProgress.value = progress

        if (progress >= 1) {
            pointAFillActive.value = false
            arrowVisible.value = true
            transferStartedAt = now
            updateArrowPosition()
            return
        }

        fillAnimationFrame = requestAnimationFrame(tick)
    }

    fillAnimationFrame = requestAnimationFrame(tick)
}

function animatePointBFill() {
    const startedAt = performance.now()

    const tick = (now: number) => {
        const progress = Math.min((now - startedAt) / POINT_A_FILL_DURATION, 1)
        pointBFillTimelineProgress.value = progress

        if (progress >= 1) {
            pointBFillActive.value = true
            return
        }

        fillAnimationFrame = requestAnimationFrame(tick)
    }

    fillAnimationFrame = requestAnimationFrame(tick)
}

function startTransfer(operation: ImportOperation) {
    if (transferStarted && transferOperationId === operation.id) return
    clearTimers()
    transferOperationId = operation.id
    transferStarted = true
    summaryVisible.value = false
    pointAFillActive.value = true
    pointAFillProgress.value = 1
    fillTimelineProgress.value = 0
    arrowVisible.value = false
    pointBFillActive.value = false
    pointBFillProgress.value = 0
    pointBFillTimelineProgress.value = 0
    timelinePhase.value = 'uploaded'
    arrowVisible.value = false
    transferStartedAt = 0
    animateFillAndTransfer()
    transferTimer = setTimeout(() => completeTransfer(props.operation), POINT_A_FILL_DURATION + TRANSFER_DURATION)
}

function syncTimelinePhase(operation: ImportOperation) {
    if (operation.id === 0) {
        resetTimeline()
        return
    }
    if (transferOperationId !== operation.id) {
        transferOperationId = operation.id
        transferStarted = false
    }
    if (operation.status === 'queued') {
        if (timelinePhase.value !== 'uploaded' && timelinePhase.value !== 'processing') {
            timelinePhase.value = 'uploading'
            arrowVisible.value = false
        }
        return
    }
    if (!transferStarted) {
        startTransfer(operation)
        return
    }
    if (operation.status === 'processing') {
        if (timelinePhase.value === 'uploaded') return
        clearTimers()
        timelinePhase.value = 'processing'
        arrowVisible.value = false
        return
    }
    timelinePhase.value = operation.status === 'completed' ? 'completed' : 'failed'
    pointBFillActive.value = true
    pointBFillProgress.value = 1
    pointBFillTimelineProgress.value = 0
    arrowVisible.value = false
    animatePointBFill()
    finishRevealTimer = setTimeout(() => {
        finishRevealTimer = null
        summaryVisible.value = true
    }, FINISH_REVEAL_DELAY)
}

watch(() => [props.operation.id, props.operation.status], () => syncTimelinePhase(props.operation), { immediate: true })

onMounted(() => {
    updateTimelineWidth()
    animationFrame = requestAnimationFrame(animationLoop)
})

onBeforeUnmount(() => {
    clearTimers()
    stopAnimationFrame()
})

const transferArrowStyle = computed(() => ({
    left: `${arrowPosition.value.x}px`,
    top: `${arrowPosition.value.y}px`,
}))
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
        label: isUploading.value ? 'Uploading ...' : 'Uploaded',
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
            <div ref="timelineRef" class="import-timeline">
                <span
                    v-if="arrowVisible"
                    class="import-timeline__transfer-arrow"
                    :style="transferArrowStyle"
                    aria-hidden="true"
                ></span>
                <div class="import-timeline__rail" aria-hidden="true">
                    <span class="import-timeline__track" :class="{ 'import-timeline__track--active': isProcessing || isTimelineFinished }"></span>
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
                                    'import-timeline__marker--fill-upload': step.key === 'upload' && pointAFillActive,
                                'import-timeline__marker--fill-success': step.key === 'processing' && pointBFillActive && isCompleted,
                                'import-timeline__marker--fill-failed': step.key === 'processing' && pointBFillActive && isFailed,
                            }"
                            aria-hidden="true"
                        >
                            <span
                                v-if="(step.key === 'upload' && pointAFillActive) || (step.key === 'processing' && pointBFillActive && isTimelineFinished)"
                                class="import-timeline__marker-fill"
                                :style="{ '--import-fill-progress': step.key === 'upload' ? fillTimelineProgress : pointBFillTimelineProgress }"
                                :class="{
                                    'import-timeline__marker-fill--success': step.key === 'upload' || isCompleted,
                                    'import-timeline__marker-fill--failed': isFailed,
                                }"
                            ></span>
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
    top: calc(0.25rem + 1.25rem);
    right: calc(25% + 1.25rem);
    left: calc(25% + 1.25rem);
    z-index: 0;
    height: 2px;
}
.import-timeline__track {
    position: absolute;
    inset: 0;
    height: 2px;
    border-radius: 9999px;
    background: var(--p-surface-300);
}
.import-timeline__track--active { background: var(--p-green-500); }
.import-timeline__transfer-arrow {
    position: absolute;
    z-index: 5;
    width: 0.45rem;
    height: 0.45rem;
    margin: -0.225rem;
    border-radius: 9999px;
    background: white;
    box-shadow:
        0 0 0 2px var(--p-green-500),
        0 0 10px color-mix(in srgb, var(--p-green-500) 45%, transparent);
    pointer-events: none;
    will-change: left;
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
    background: var(--p-card-background);
    color: white;
    overflow: hidden;
}

.import-timeline__marker--failed {
    border-color: var(--p-red-500);
    color: var(--p-red-500);
    overflow: hidden;
}

.import-timeline__marker-fill {
    position: absolute;
    inset: -3px;
    z-index: 0;
    border-radius: inherit;
    background: var(--p-green-500);
    transform: scale(var(--import-fill-progress, 0));
    transform-origin: center;
}

.import-timeline__marker-fill--failed {
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
    .import-timeline__rail {
        top: calc(0.25rem + 1.125rem);
        right: calc(25% + 1rem);
        left: calc(25% + 1rem);
    }

    .import-timeline__marker {
        width: 2.25rem;
        height: 2.25rem;
    }

    .import-timeline__label {
        font-size: 0.8rem;
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

@keyframes import-label-pulse {
    0%, 100% {
        opacity: 0.72;
    }

    50% {
        opacity: 1;
    }
}

@keyframes import-point-b-fill {
    from {
        transform: scale(0);
    }

    to {
        transform: scale(1);
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
    .import-timeline__transfer-arrow,
    .import-timeline__label,
    .import-timeline__track {
        animation: none !important;
        transition: none !important;
    }

    .import-summary-enter-active,
    .import-summary-leave-active {
        transition: none !important;
    }
}

</style>
