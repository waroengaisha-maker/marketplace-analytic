<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3'
import Card from 'primevue/card'
import FileUpload from 'primevue/fileupload'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { ref } from 'vue'

defineProps<{
    title: string
    description?: string
    form: InertiaForm<Record<string, File | null>>
    field: string
    submitLabel: string
}>()

defineEmits<{ submit: [] }>()
const clientError = ref('')
const validateFile = (file: File | null): boolean => {
    clientError.value = file === null
        ? 'Pilih file laporan terlebih dahulu.'
        : !/\.(xlsx|xls)$/i.test(file.name)
            ? 'File harus berformat XLSX atau XLS.'
            : file.size > 50 * 1024 * 1024
                ? 'Ukuran file maksimal 50 MB.'
                : ''
    return !clientError.value
}
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
                @select="form[field] = $event.files[0] || null; validateFile(form[field])"
            />
            <Message v-if="clientError" class="mt-2" severity="error">{{ clientError }}</Message>
            <small v-if="form.errors[field]" class="p-error block mt-2">{{ form.errors[field] }}</small>
            <Button class="mt-4" type="button" :label="form.processing ? 'Mengimpor...' : submitLabel" :loading="form.processing" :disabled="!form[field] || !!clientError" @click="validateFile(form[field]) && $emit('submit')" />
        </template>
    </Card>
</template>