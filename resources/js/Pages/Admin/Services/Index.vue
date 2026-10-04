<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

type Service = {
    key: string
    name: string
    description: string
    url: string
    icon: string
    risk: 'medium' | 'high' | 'critical'
}

defineProps<{
    services: Service[]
}>()

const riskLabel: Record<Service['risk'], string> = {
    medium: 'Medium risk',
    high: 'High risk',
    critical: 'Critical access',
}

const riskClass: Record<Service['risk'], string> = {
    medium: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    high: 'bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300',
    critical: 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
}
</script>

<template>
    <Head title="Services" />

    <div class="mx-auto flex max-w-7xl flex-col gap-8">
        <header>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white shadow-sm dark:bg-slate-100 dark:text-slate-900">
                    <i class="pi pi-server" aria-hidden="true" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                        Services
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Operational tools for the Marketplace Analytics home server.
                    </p>
                </div>
            </div>
        </header>

        <section class="grid gap-5 md:grid-cols-2">
            <article
                v-for="service in services"
                :key="service.key"
                class="group flex min-h-52 flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-950 dark:hover:border-slate-700"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <i :class="[service.icon, 'text-lg']" aria-hidden="true" />
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="riskClass[service.risk]">
                        {{ riskLabel[service.risk] }}
                    </span>
                </div>

                <div class="mt-5">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ service.name }}</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ service.description }}</p>
                </div>

                <div class="mt-auto pt-6">
                    <a
                        :href="service.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                    >
                        Open {{ service.name }}
                        <i class="pi pi-external-link text-xs" aria-hidden="true" />
                    </a>
                </div>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
            <div class="flex gap-3">
                <i class="pi pi-shield mt-0.5 text-slate-500" aria-hidden="true" />
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Restricted operations</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                        This hub is available only to Super Admin. Service subdomains must also be protected at the reverse-proxy layer; Laravel route authorization alone does not protect external Docker services.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
