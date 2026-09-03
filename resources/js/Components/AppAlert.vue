<script setup lang="ts">
defineProps<{ type: 'success' | 'error'; message?: string }>()
</script>

<template>
    <div v-if="message" class="mb-4 rounded-lg p-4 text-sm" :class="type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'" role="alert">
        {{ message }}
    </div>
</template>