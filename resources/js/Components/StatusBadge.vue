<script setup lang="ts">
import Tag from 'primevue/tag'

type StatusBadgeSeverity = 'success' | 'secondary' | 'info' | 'warn' | 'danger' | 'contrast'

withDefaults(defineProps<{
    value: string | number
    severity?: StatusBadgeSeverity | null
    rounded?: boolean
    icon?: string | null
}>(), {
    severity: 'secondary',
    rounded: false,
    icon: null,
})
</script>

<template>
    <Tag
        :value="value"
        :severity="severity"
        :rounded="rounded"
        :icon="icon ?? undefined"
    />
</template>
