<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3'
import Card from 'primevue/card'
import FileUpload from 'primevue/fileupload'
import Button from 'primevue/button'

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
    <Card>
        <template #title>{{ title }}</template>
        <template #subtitle>{{ description }}</template>
        <template #content>
            <FileUpload
                mode="basic"
                name="report"
                accept=".xlsx,.xls"
                :auto="false"
                choose-label="Pilih file"
                :disabled="form.processing"
                @select="form[field] = $event.files[0] || null"
            />
            <small v-if="form.errors[field]" class="p-error block mt-2">{{ form.errors[field] }}</small>
            <Button class="mt-4" type="button" :label="form.processing ? 'Mengimpor...' : submitLabel" :loading="form.processing" :disabled="!form[field]" @click="$emit('submit')" />
        </template>
    </Card>
</template>