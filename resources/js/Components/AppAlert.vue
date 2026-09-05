<script setup lang="ts">
import Message from 'primevue/message'

defineProps<{ type: 'success' | 'error'; message?: string }>()
</script>

<template>
    <Message v-if="message" class="mb-4" :severity="type === 'success' ? 'success' : 'error'" :closable="false">
        {{ message }}
    </Message>
</template>