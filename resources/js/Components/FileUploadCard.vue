<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import FeedbackMessage from './FeedbackMessage.vue'
import { ref } from 'vue'

defineProps<{
    title: string
    description?: string
    form: InertiaForm<Record<string, any>>
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

const handleFileChange = (event: Event, form: InertiaForm<Record<string, any>>, field: string) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0] ?? null

    form[field] = file
    validateFile(file)
}
</script>

<template>
    <Card>
        <template #title>{{ title }}</template>
        <template #subtitle>{{ description }}</template>
        <template #content>


            <div class="flex flex-col gap-2">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative inline-flex">
                        <input
                            type="file"
                            class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                            accept=".xlsx,.xls"
                            :disabled="form.processing"
                            @change="handleFileChange($event, form, field)"
                        />
                        <Button
                            type="button"
                            icon="pi pi-file-excel"
                            label="Pilih file"
                            :disabled="form.processing"
                            tabindex="-1"
                            aria-hidden="true"
                        />
                    </div>
                    <span v-if="form[field]" class="min-w-0 truncate text-sm text-color-secondary">
                        {{ form[field]?.name }}
                    </span>
                    <span v-else class="text-sm text-color-secondary">
                        XLSX/XLS, maksimal 50 MB
                    </span>
                </div>

                <FeedbackMessage
                    v-if="clientError"
                    class="mt-2"
                    severity="error"
                    :message="clientError"
                />
                <small v-if="form.errors[field]" class="p-error block mt-2">{{ form.errors[field] }}</small>
            </div>

            <Button
                class="mt-4"
                type="button"
                :label="form.processing ? 'Mengimpor...' : submitLabel"
                :loading="form.processing"
                :disabled="!form[field] || !!clientError"
                @click="validateFile(form[field]) && $emit('submit')"
            />
        </template>
    </Card>
</template>
