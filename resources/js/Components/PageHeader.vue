<script setup lang="ts">
withDefaults(defineProps<{
    section?: string
    title: string
    description?: string
}>(), {
    section: undefined,
    description: undefined,
})
</script>

<template>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p v-if="section" class="text-xs font-semibold uppercase tracking-[0.2em] text-color-secondary">
                {{ section }}
            </p>
            <h1 class="mt-1 text-3xl font-bold text-color">
                {{ title }}
            </h1>
            <p v-if="description" class="mt-2 text-sm text-color-secondary">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
