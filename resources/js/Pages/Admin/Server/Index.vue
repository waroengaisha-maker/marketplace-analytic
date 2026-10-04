<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

type ServiceStatus = {
    status?: string
    health?: string
}

type ServerStatus = {
    available: boolean
    stale?: boolean
    generated_at_human?: string
    host?: string
    overall?: string
    system?: {
        uptime?: string
        load_1m?: number
        memory_used?: string
        memory_total?: string
    }
    disk?: {
        percent?: number
    }
    docker?: Record<string, ServiceStatus>
    application?: {
        status?: string
        url?: string
    }
    backup?: {
        timer_enabled?: boolean
        latest?: string | null
        size?: string | null
        integrity?: string
    }
    storage?: {
        smart?: string
    }
    reason?: string
}

const props = defineProps<{ status: ServerStatus }>()
const refreshing = ref(false)
let refreshTimer: ReturnType<typeof setInterval> | undefined

const refresh = () => {
    refreshing.value = true
    router.reload({
        only: ['status'],
        onFinish: () => { refreshing.value = false },
    })
}

onMounted(() => { refreshTimer = setInterval(refresh, 30000) })
onUnmounted(() => { if (refreshTimer) clearInterval(refreshTimer) })

const overallLabel = computed(() => {
    if (!props.status.available) return 'Unavailable'
    if (props.status.stale) return 'Stale'
    return props.status.overall === 'fail' ? 'Failure' : props.status.overall === 'warn' ? 'Warning' : 'Healthy'
})

const overallClass = computed(() => {
    if (!props.status.available || props.status.overall === 'fail') return 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'
    if (props.status.stale || props.status.overall === 'warn') return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
    return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
})

const serviceHealthy = (service?: ServiceStatus) =>
    service?.status === 'running' && (!service.health || service.health === 'healthy' || service.health === 'no-healthcheck')

const serviceLabel = (service?: ServiceStatus) => serviceHealthy(service) ? 'Running' : service?.health || service?.status || 'Unknown'
const boolLabel = (value?: boolean) => value ? 'Enabled' : 'Not enabled'
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Server Dashboard</h1>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="overallClass">{{ overallLabel }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ status.host || 'Marketplace Analytics Server' }}
                        <span v-if="status.generated_at_human"> · updated {{ status.generated_at_human }}</span>
                    </p>
                </div>
                <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" :disabled="refreshing" @click="refresh">
                    <i class="pi pi-refresh" :class="{ 'animate-spin': refreshing }" aria-hidden="true" />
                    {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                </button>
            </div>

            <div v-if="!status.available" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                {{ status.reason || 'Server status is unavailable. Run the server status snapshot on the host.' }}
            </div>
            <div v-else-if="status.stale" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                The server status snapshot is stale. Verify the server status refresh timer.
            </div>

            <template v-if="status.available">
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="metric in [
                        { label: 'Uptime', value: status.system?.uptime || '—', icon: 'pi pi-clock' },
                        { label: 'Load (1m)', value: status.system?.load_1m?.toFixed(2) ?? '—', icon: 'pi pi-chart-line' },
                        { label: 'Memory', value: (status.system?.memory_used || '—') + ' / ' + (status.system?.memory_total || '—'), icon: 'pi pi-database' },
                        { label: 'Disk /', value: (status.disk?.percent ?? '—') + '%', icon: 'pi pi-server' },
                    ]" :key="metric.label" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <div class="flex items-center gap-3 text-slate-400">
                            <i :class="metric.icon" aria-hidden="true" />
                            <span class="text-xs font-medium uppercase tracking-wide">{{ metric.label }}</span>
                        </div>
                        <div class="mt-3 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ metric.value }}</div>
                    </article>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Docker Services</h2>
                        <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                            <div v-for="(service, name) in status.docker" :key="name" class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                                <span class="text-sm text-slate-700 dark:text-slate-300">{{ name }}</span>
                                <span class="flex items-center gap-2 text-xs font-medium" :class="serviceHealthy(service) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                    <span class="h-2 w-2 rounded-full" :class="serviceHealthy(service) ? 'bg-emerald-500' : 'bg-red-500'" />
                                    {{ serviceLabel(service) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Application</h2>
                        <div class="mt-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500">HTTP health</span>
                                <span class="text-sm font-semibold" :class="status.application?.status === 'ok' ? 'text-emerald-600' : 'text-red-600'">{{ status.application?.status === 'ok' ? 'Healthy' : 'Failure' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500">Local endpoint</span>
                                <code class="text-xs text-slate-700 dark:text-slate-300">{{ status.application?.url || '—' }}</code>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Backup</h2>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between"><span class="text-slate-500">Timer</span><span class="font-medium text-slate-700 dark:text-slate-300">{{ boolLabel(status.backup?.timer_enabled) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500">Latest</span><span class="font-medium text-slate-700 dark:text-slate-300">{{ status.backup?.latest || 'None' }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500">Size</span><span class="font-medium text-slate-700 dark:text-slate-300">{{ status.backup?.size || '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500">Integrity</span><span :class="status.backup?.integrity === 'ok' ? 'font-medium text-emerald-600' : 'font-medium text-red-600'">{{ status.backup?.integrity === 'ok' ? 'Valid' : 'Invalid' }}</span></div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Storage</h2>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-sm text-slate-500">SMART /dev/sda</span>
                            <span class="text-sm font-semibold" :class="status.storage?.smart === 'PASSED' || status.storage?.smart === 'OK' ? 'text-emerald-600' : 'text-red-600'">{{ status.storage?.smart || 'Unknown' }}</span>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
