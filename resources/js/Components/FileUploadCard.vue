<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3'

defineProps<{
    title: string
    description?: string
    form: InertiaForm<Record<string, File | null>>
    field: string
    submitLabel: string
}>()

defineEmits<{ submit: [] }>()
</script>

<template>
    <form class="rounded-xl border border-slate-200 bg-white p-6" @submit.prevent="$emit('submit')">
        <label class="block text-sm font-medium text-slate-700">{{ title }}</label>
        <p v-if="description" class="mt-1 text-xs text-slate-500">{{ description }}</p>
        <input class="mt-3 block w-full text-sm" type="file" accept=".xlsx,.xls" required @change="form[field] = ($event.target as HTMLInputElement).files?.[0] || null" />
        <p v-if="form.errors[field]" class="mt-2 text-sm text-red-600">{{ form.errors[field] }}</p>
        <button type="submit" class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="form.processing">
            {{ form.processing ? 'Mengimpor...' : submitLabel }}
        </button>
    </form>
</template>