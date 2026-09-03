<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppAlert from '../../Components/AppAlert.vue'
import FileUploadCard from '../../Components/FileUploadCard.vue'

const page = usePage<{ flash?: { success?: string; error?: string } }>()
const orderForm = useForm<{ order_report: File | null }>({ order_report: null })
const incomeForm = useForm<{ income_report: File | null }>({ income_report: null })

function submitOrder() { orderForm.post('/imports/upload', { forceFormData: true }) }
function submitIncome() { incomeForm.post('/imports/upload', { forceFormData: true }) }
</script>

<template>
    <Head title="Upload Files" />
    <div class="mx-auto max-w-3xl">
        <div class="mb-8"><p class="text-sm font-medium text-slate-400">IMPORTS</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Upload Laporan</h1><p class="mt-2 text-sm text-slate-500">Perbarui setiap laporan secara terpisah.</p></div>
        <AppAlert type="success" :message="page.props.flash?.success" />
        <AppAlert type="error" :message="page.props.flash?.error" />
        <div class="space-y-4">
            <FileUploadCard title="Laporan Order" field="order_report" submit-label="Upload & Import Order" :form="orderForm" @submit="submitOrder" />
            <FileUploadCard title="Laporan Income" description="Sheet Penghasilan" field="income_report" submit-label="Upload & Import Income" :form="incomeForm" @submit="submitIncome" />
        </div>
    </div>
</template>