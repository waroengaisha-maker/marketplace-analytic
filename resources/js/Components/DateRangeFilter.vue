<script setup lang="ts">
import DatePicker from 'primevue/datepicker'

withDefaults(defineProps<{
    from: Date | null
    to: Date | null
    minDate?: Date
    maxDate?: Date
    idPrefix: string
}>(), {
    minDate: undefined,
    maxDate: undefined,
})

const emit = defineEmits<{
    'update:from': [value: Date | null]
    'update:to': [value: Date | null]
}>()
</script>

<template>
    <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="flex min-w-0 flex-col gap-1">
            <label :for="`${idPrefix}-from`" class="text-xs font-medium text-color-secondary">Tanggal mulai</label>
            <DatePicker
                :id="`${idPrefix}-from`"
                :model-value="from"
                date-format="yy-mm-dd"
                show-icon
                show-clear
                placeholder="Pilih tanggal"
                aria-label="Tanggal mulai"
                fluid
                class="h-11 w-full"
                :min-date="minDate"
                :max-date="maxDate"
                @update:model-value="emit('update:from', $event)"
            />
        </div>
        <div class="flex min-w-0 flex-col gap-1">
            <label :for="`${idPrefix}-to`" class="text-xs font-medium text-color-secondary">Tanggal akhir</label>
            <DatePicker
                :id="`${idPrefix}-to`"
                :model-value="to"
                date-format="yy-mm-dd"
                show-icon
                show-clear
                placeholder="Pilih tanggal"
                aria-label="Tanggal akhir"
                fluid
                class="h-11 w-full"
                :min-date="minDate"
                :max-date="maxDate"
                @update:model-value="emit('update:to', $event)"
            />
        </div>
    </div>
</template>
