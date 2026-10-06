<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, ref } from 'vue'

const page = usePage<{ status?: string }>()
const form = useForm({ email: '' })
const emailTouched = ref(false)
const submitAttempted = ref(false)

const emailIsValid = computed(() =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()),
)

const emailIsInvalid = computed(() =>
    Boolean(form.errors.email) ||
    (emailTouched.value || submitAttempted.value) && !emailIsValid.value,
)

function submit() {
    submitAttempted.value = true

    if (!emailIsValid.value) {
        return
    }

    form.post('/forgot-password', {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Forgot password" />

    <main class="flex min-h-screen items-center justify-center bg-surface-50 p-4">
        <Card class="w-full max-w-md">
            <template #title>Reset your password</template>
            <template #subtitle>We will email you a password reset link.</template>

            <template #content>
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <Message
                        v-if="page.props.status"
                        severity="success"
                        variant="simple"
                        icon="pi pi-check-circle"
                    >
                        {{ page.props.status }}
                    </Message>

                    <Message
                        v-if="form.errors.email"
                        severity="error"
                        variant="simple"
                        icon="pi pi-exclamation-circle"
                    >
                        {{ form.errors.email }}
                    </Message>

                    <div class="flex flex-col gap-2">
                        <label for="email">
                            Email
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>

                        <InputText
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            :invalid="emailIsInvalid"
                            required
                            fluid
                            @blur="emailTouched = true"
                        />

                        <small
                            v-if="emailIsInvalid && !form.errors.email"
                            class="text-sm text-red-500"
                        >
                            Masukkan alamat email yang valid.
                        </small>
                    </div>

                    <Button
                        type="submit"
                        :label="form.processing ? 'Sending...' : 'Send reset link'"
                        :loading="form.processing"
                        :disabled="!emailIsValid || form.processing"
                    />

                    <Link
                        href="/login"
                        class="text-center text-sm font-medium text-primary"
                    >
                        Back to login
                    </Link>
                </form>
            </template>
        </Card>
    </main>
</template>

<style scoped>
.required-mark {
    color: var(--p-primary-color);
    margin-left: 0.125rem;
}
</style>
