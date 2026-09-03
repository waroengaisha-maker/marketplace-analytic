<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'

const page = usePage<{ flash?: { success?: string } }>()
const form = useForm<{ order_report: File | null; income_report: File | null }>({
    order_report: null,
    income_report: null,
})

function submit() {
    form.post('/imports/upload', { forceFormData: true })
}
</script>

<template>
    <Head title="Upload Files" />

    <div class="mx-auto max-w-3xl">
        <div class="mb-8">
            <p class="text-sm font-medium text-slate-400">IMPORTS</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Upload Laporan</h1>
            <p class="mt-2 text-sm text-slate-500">Unggah laporan Order dan Penghasilan untuk diproses ke database.</p>
        </div>

        <div v-if="form.errors.upload" class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            {{ form.errors.upload }}
        </div>

        <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </div>

        <form class="space-y-6 rounded-xl border border-slate-200 bg-white p-6" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700">Laporan Order</label>
                <input class="mt-2 block w-full text-sm" type="file" accept=".xlsx,.xls" required @change="form.order_report = ($event.target as HTMLInputElement).files?.[0] || null" />
                <p v-if="form.errors.order_report" class="mt-1 text-sm text-red-600">{{ form.errors.order_report }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Laporan Income — sheet Penghasilan</label>
                <input class="mt-2 block w-full text-sm" type="file" accept=".xlsx,.xls" required @change="form.income_report = ($event.target as HTMLInputElement).files?.[0] || null" />
                <p v-if="form.errors.income_report" class="mt-1 text-sm text-red-600">{{ form.errors.income_report }}</p>
            </div>
            <button type="submit" :disabled="form.processing" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                {{ form.processing ? 'Mengunggah...' : 'Upload Laporan' }}
            </button>
        </form>
    </div>
</template>