<script setup lang="ts">
import FeedbackMessage from './FeedbackMessage.vue'

defineProps<{ type: 'success' | 'error'; message?: string }>()
</script>

<template>
    <FeedbackMessage
        v-if="message"
        class="mb-4"
        :severity="type === 'success' ? 'success' : 'error'"
        :message="message"
    />
</template>
