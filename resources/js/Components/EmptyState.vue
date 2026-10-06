<script setup lang="ts">
withDefaults(defineProps<{
    title?: string
    description?: string
    icon?: string
}>(), {
    title: undefined,
    description: undefined,
    icon: 'pi pi-inbox',
})
</script>

<template>
    <div class="flex min-h-32 flex-col items-center justify-center gap-2 px-4 py-8 text-center text-color-secondary">
        <i :class="[icon, 'text-2xl']" aria-hidden="true"></i>
        <p v-if="title" class="text-sm font-semibold text-color">{{ title }}</p>
        <p v-if="description" class="max-w-xl text-sm">{{ description }}</p>
        <slot />
    </div>
</template>
