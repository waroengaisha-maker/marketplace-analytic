<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import { formatNominal } from '@/utils/formatters'

type User = {
    name: string
    email: string
}

type PageProps = {
    auth?: {
        user?: User | null
    }
    stats: {
        gross_sales: number
        net_sales: number
        settled_sales: number
        pending_sales: number
        settled_profit: number
        pending_profit: number
        total_profit: number
        valid_without_tracking: number
        cancelled_sales: number
        gross_order_count: number
        net_order_count: number
        settled_order_count: number
        pending_order_count: number
    }
    dateRange: {
        min: string | null
        max: string | null
    }
    filters: {
        from: string | null
        to: string | null
    }
}

const page = usePage<PageProps>()
const logout = useForm({})
const from = ref(page.props.filters.from || '')
const to = ref(page.props.filters.to || '')

function applyDateFilter() {
    router.get('/', { from: from.value || undefined, to: to.value || undefined }, {
        preserveState: true,
        preserveScroll: true,
    })
}

function submitLogout() {
    logout.post('/logout')
}
</script>

<template>
    <Head title="Dashboard" />

    <div>
        <div class="mb-8">
            <p class="text-sm font-medium text-slate-400">
                Overview
            </p>

            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                Dashboard
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Selamat datang<span v-if="page.props.auth?.user?.name">
                   , {{ page.props.auth.user.name }}
                </span>.
            </p>
        </div>

        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-end gap-4">
                <label class="grid gap-1 text-sm text-slate-600">
                    <span>Tanggal Mulai</span>
                    <input v-model="from" type="date" :min="page.props.dateRange.min || undefined" :max="page.props.dateRange.max || undefined" class="rounded-lg border border-slate-300 px-3 py-2" />
                </label>
                <label class="grid gap-1 text-sm text-slate-600">
                    <span>Tanggal Akhir</span>
                    <input v-model="to" type="date" :min="page.props.dateRange.min || undefined" :max="page.props.dateRange.max || undefined" class="rounded-lg border border-slate-300 px-3 py-2" />
                </label>
                <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800" @click="applyDateFilter">Terapkan</button>
            </div>
            <p class="mt-2 text-xs text-slate-500">Periode berdasarkan tanggal order dibuat.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Penjualan / Gross Sales</p>
                <p class="mt-2 text-2xl font-bold text-cyan-500">{{ formatNominal(page.props.stats.gross_sales) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ page.props.stats.gross_order_count }} order</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Net Sales</p>
                <p class="mt-2 text-2xl font-bold text-emerald-500">{{ formatNominal(page.props.stats.net_sales) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ page.props.stats.net_order_count }} order</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pesanan Settled</p>
                <p class="mt-2 text-2xl font-bold text-cyan-500">{{ formatNominal(page.props.stats.settled_sales) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ page.props.stats.settled_order_count }} order</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pesanan Unsettled</p>
                <p class="mt-2 text-2xl font-bold text-orange-500">{{ formatNominal(page.props.stats.pending_sales) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ page.props.stats.pending_order_count }} order</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Laba Bersih Settled</p>
                <p class="mt-2 text-2xl font-bold text-emerald-500">{{ formatNominal(page.props.stats.settled_profit) }}</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Laba Bersih Unsettled</p>
                <p class="mt-2 text-2xl font-bold text-orange-500">{{ formatNominal(page.props.stats.pending_profit) }}</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Laba Bersih</p>
                <p class="mt-2 text-2xl font-bold text-cyan-500">{{ formatNominal(page.props.stats.total_profit) }}</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Valid Tanpa No. Resi</p>
                <p class="mt-2 text-2xl font-bold text-orange-500">{{ page.props.stats.valid_without_tracking }}</p>
                <p class="mt-1 text-xs text-slate-500">order</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Nilai Pembatalan</p>
                <p class="mt-2 text-2xl font-bold text-orange-500">{{ formatNominal(page.props.stats.cancelled_sales) }}</p>
            </div>
        </div>

        <div class="mt-6">
            <button
                type="button"
                :disabled="logout.processing"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-50"
                @click="submitLogout"
            >
                Logout
            </button>
        </div>
    </div>
</template>