<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'

type User = {
    name: string
    email: string
}

type PageProps = {
    auth?: {
        user?: User | null
    }
}

const page = usePage<PageProps>()
const logout = useForm({})

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

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">Net Sales</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Rp 0</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">Gross Profit</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">Rp 0</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">Orders</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">0</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">Profit Margin</p>
                <p class="mt-2 text-2xl font-bold text-slate-900">0%</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="font-semibold text-slate-900">
                    Sales & Profit
                </h2>

                <div class="mt-6 flex h-64 items-center justify-center rounded-lg bg-slate-50">
                    <p class="text-sm text-slate-400">
                        Chart akan tersedia setelah data import aktif.
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="font-semibold text-slate-900">
                    Reconciliation
                </h2>

                <div class="mt-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Matched</span>
                        <span class="font-semibold text-slate-900">0</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Ambiguous</span>
                        <span class="font-semibold text-slate-900">0</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Unmatched</span>
                        <span class="font-semibold text-slate-900">0</span>
                    </div>
                </div>
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