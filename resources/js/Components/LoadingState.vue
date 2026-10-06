<script setup lang="ts">
withDefaults(defineProps<{
    label?: string
}>(), {
    label: 'Memuat...',
})
</script>

<template>
    <div class="flex items-center justify-center gap-3 p-8 text-color-secondary" role="status" aria-live="polite">
        <i class="pi pi-spin pi-spinner text-xl" aria-hidden="true"></i>
        <span class="text-sm">{{ label }}</span>
    </div>
</template>
