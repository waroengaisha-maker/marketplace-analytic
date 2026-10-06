<script setup lang="ts">
type DataTableState = 'loading' | 'empty' | 'error'

withDefaults(defineProps<{
    state?: DataTableState
    title?: string
    description?: string
    icon?: string
}>(), {
    state: 'empty',
    title: undefined,
    description: undefined,
    icon: undefined,
})
</script>

<template>
    <div
        class="flex min-h-32 flex-col items-center justify-center gap-2 px-4 py-8 text-center text-color-secondary"
        role="status"
        aria-live="polite"
    >
        <i
            :class="[
                icon ?? (state === 'error' ? 'pi pi-exclamation-circle' : state === 'empty' ? 'pi pi-inbox' : 'pi pi-spin pi-spinner'),
                'text-2xl',
            ]"
            aria-hidden="true"
        ></i>
        <p v-if="title" class="text-sm font-semibold text-color">{{ title }}</p>
        <p v-if="description" class="max-w-xl text-sm">{{ description }}</p>
        <slot />
    </div>
</template>
