<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

const props = defineProps<{
    orders?: unknown[]
    filters?: Record<string, unknown>
}>()
</script>

<template>
    <Head title="Orders" />

    <div class="flex w-full min-w-0 flex-col">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Operations</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">Orders</h1>
            <p class="mt-2 text-sm text-slate-500">Ringkasan transaksi per nomor order lengkap dengan rincian biaya hingga laba bersih.</p>
        </div>
    </div>
</template>
