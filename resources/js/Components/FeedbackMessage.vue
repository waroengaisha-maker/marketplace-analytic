<script setup lang="ts">
import Message from 'primevue/message'

type FeedbackMessageSeverity = 'success' | 'info' | 'warn' | 'error' | 'secondary' | 'contrast'
type FeedbackMessageVariant = 'outlined' | 'simple'

withDefaults(defineProps<{
    severity?: FeedbackMessageSeverity
    variant?: FeedbackMessageVariant
    message?: string
    closable?: boolean
    icon?: string | null
}>(), {
    severity: 'info',
    variant: undefined,
    message: undefined,
    closable: false,
    icon: null,
})
</script>

<template>
    <Message
        v-if="message || $slots.default"
        :severity="severity"
        :variant="variant"
        :closable="closable"
        :icon="icon ?? undefined"
    >
        <slot>{{ message }}</slot>
    </Message>
</template>
