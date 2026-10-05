<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'

type Service = {
    key: string
    name: string
    description: string
    url: string
    icon: string
    risk: 'medium' | 'high' | 'critical'
}
type ServiceState = { status: string; container?: string }

const props = defineProps<{ services: Service[] }>()
const states = ref<Record<string, ServiceState>>({})
const busy = ref<string | null>(null)
const error = ref<string | null>(null)
const loading = ref(true)

const riskLabel: Record<Service['risk'], string> = {
    medium: 'Medium risk',
    high: 'High risk',
    critical: 'Critical access',
}

const riskDescription: Record<Service['risk'], string> = {
    medium: 'Operational access with a limited blast radius. A mistake can affect monitoring or service availability, but does not directly manage the whole Docker host.',
    high: 'Sensitive access. This service can inspect or change important application data, such as the database or Redis data.',
    critical: 'Highest-impact access. This service can manage Docker containers and therefore can affect the application stack and host-level resources.',
}

const serviceImpact: Record<string, string> = {
    netdata: 'Monitoring only. Restarting or stopping Netdata affects monitoring visibility, not the Marketplace Analytics application itself.',
    portainer: 'Docker management. Changes here can start, stop, restart, recreate, or otherwise affect containers managed by Docker.',
    adminer: 'Database administration. Changes here can modify or delete MySQL data and should be treated as production data operations.',
    redisinsight: 'Redis administration. Changes here can inspect, modify, or delete cache, session, queue, and other Redis data.',
}

const riskClass: Record<Service['risk'], string> = {
    medium: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    high: 'bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300',
    critical: 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
}

function statusDescription(status?: string): string {
    switch (status) {
        case 'running':
            return 'The service container is currently running.'
        case 'exited':
            return 'The service container exists but is currently stopped.'
        case 'restarting':
            return 'Docker is currently restarting the service container.'
        case 'not-found':
            return 'No matching service container was found by the Service Manager.'
        case 'ambiguous':
            return 'More than one matching container was found, so Service Manager will not control it.'
        case 'created':
            return 'The service container exists but has not started yet.'
        default:
            return 'The Service Manager could not determine the current container state.'
    }
}

function riskTooltip(service: Service): string {
    return `${riskLabel[service.risk]} — ${riskDescription[service.risk]} Impact on ${service.name}: ${serviceImpact[service.key] ?? service.description}`
}

async function refresh() {
    loading.value = true
    error.value = null
    try {
        const response = await fetch('/admin/services/status', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        const data = await response.json()
        if (!response.ok) throw new Error(data.message ?? 'Unable to read service status.')
        states.value = data.services ?? {}
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Unable to read service status.'
    } finally {
        loading.value = false
    }
}

async function action(service: Service, operation: 'start' | 'stop' | 'restart') {
    if ((operation === 'stop' || operation === 'restart') && !window.confirm(`${operation === 'stop' ? 'Stop' : 'Restart'} ${service.name}?`)) return
    busy.value = `${service.key}:${operation}`
    error.value = null
    try {
        const response = await fetch(`/admin/services/${service.key}/${operation}`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '' },
            credentials: 'same-origin',
        })
        const data = await response.json()
        if (!response.ok) throw new Error(data.message ?? `Unable to ${operation} ${service.name}.`)
        await refresh()
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Service operation failed.'
    } finally {
        busy.value = null
    }
}

onMounted(refresh)
</script>

<template>
    <Head title="Services" />
    <div class="mx-auto flex max-w-7xl flex-col gap-8">
        <header class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"><i class="pi pi-server" /></div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">Services</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Operational tools for the Marketplace Analytics home server.</p>
                </div>
            </div>
            <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold dark:border-slate-700" :disabled="loading" @click="refresh" v-tooltip.bottom="'Refresh service container status'">
                <i class="pi pi-refresh mr-2" :class="{ 'animate-spin': loading }" />Refresh
            </button>
        </header>

        <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ error }}</div>

        <section class="grid gap-5 md:grid-cols-2">
            <article v-for="service in props.services" :key="service.key" class="flex min-h-64 flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"><i :class="[service.icon, 'text-lg']" /></div>
                    <div class="flex items-center gap-2">
                        <div class="flex items-center">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="riskClass[service.risk]">{{ riskLabel[service.risk] }}</span>
                            <button
                                type="button"
                                class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                :aria-label="`Explain ${riskLabel[service.risk]}`"
                                v-tooltip.bottom="riskTooltip(service)"
                            >
                                <i class="pi pi-question-circle text-xs" />
                            </button>
                        </div>
                        <div class="flex items-center">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="states[service.key]?.status === 'running' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'">
                                {{ states[service.key]?.status ?? (loading ? 'Checking…' : 'unknown') }}
                            </span>
                            <button
                                type="button"
                                class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                aria-label="Explain service status"
                                v-tooltip.bottom="statusDescription(states[service.key]?.status)"
                            >
                                <i class="pi pi-question-circle text-xs" />
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mt-5">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ service.name }}</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ service.description }}</p>
                </div>
                <div class="mt-auto grid grid-cols-3 gap-2 pt-6">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold disabled:opacity-50 dark:border-slate-700" :disabled="busy !== null" @click="action(service, 'start')" v-tooltip.top="'Start the service container'">Start</button>
                    <button type="button" class="rounded-xl border border-amber-200 px-3 py-2 text-sm font-semibold text-amber-700 disabled:opacity-50 dark:border-amber-900 dark:text-amber-300" :disabled="busy !== null" @click="action(service, 'restart')" v-tooltip.top="'Restart the service container'">Restart</button>
                    <button type="button" class="rounded-xl border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 disabled:opacity-50 dark:border-red-900 dark:text-red-300" :disabled="busy !== null" @click="action(service, 'stop')" v-tooltip.top="'Stop the service container'">Stop</button>
                </div>
                <a :href="service.url" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 font-semibold text-white dark:bg-slate-100 dark:text-slate-900" v-tooltip.top="'Open the service in a new tab'">
                    Open {{ service.name }} <i class="pi pi-external-link text-xs" />
                </a>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
            <div class="flex gap-3">
                <i class="pi pi-shield mt-0.5 text-slate-500" />
                <div>
                    <h2 class="text-sm font-semibold">Restricted operations</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Only Super Admin can control these services. Laravel never receives the Docker socket; operations are delegated to the internal service manager.</p>
                </div>
            </div>
        </section>
    </div>
</template>
