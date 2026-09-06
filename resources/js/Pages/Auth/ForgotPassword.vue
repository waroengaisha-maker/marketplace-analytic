<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { ref } from 'vue'

const page = usePage<{ status?: string }>()
const form = useForm({ email: '' })
const clientError = ref('')

function submit() {
    clientError.value = !form.email.trim() ? 'Masukkan email.' : ''
    if (clientError.value) return
    form.post('/forgot-password')
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
                    <Message v-if="page.props.status" severity="success">{{ page.props.status }}</Message>
                    <Message v-if="clientError || form.errors.email" severity="error">{{ clientError || form.errors.email }}</Message>
                    <div class="flex flex-col gap-2">
                        <label for="email">Email</label>
                        <InputText id="email" v-model="form.email" type="email" autocomplete="email" required />
                    </div>
                    <Button type="submit" :label="form.processing ? 'Sending...' : 'Send reset link'" :loading="form.processing" />
                    <Link href="/login" class="text-center text-sm text-primary font-medium">Back to login</Link>
                </form>
            </template>
        </Card>
    </main>
</template>
