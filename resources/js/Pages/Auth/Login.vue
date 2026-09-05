<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Divider from 'primevue/divider'

const form = useForm({ email: '', password: '', remember: false })
function submit() { form.post('/login') }
</script>

<template>
    <Head title="Login" />
    <main class="min-h-screen flex items-center justify-center bg-surface-50 p-4">
        <Card class="w-full max-w-md">
            <template #title>Marketplace Analytics</template>
            <template #subtitle>Sign in to your account</template>
            <template #content>
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <Message v-if="form.errors.email" severity="error">{{ form.errors.email }}</Message>
                    <div class="flex flex-col gap-2">
                        <label for="email">Email</label>
                        <InputText id="email" v-model="form.email" type="email" autocomplete="email" required />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="password">Password</label>
                        <Password id="password" v-model="form.password" input-class="w-full" toggle-mask :feedback="false" autocomplete="current-password" required />
                        <small v-if="form.errors.password" class="p-error">{{ form.errors.password }}</small>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.remember" input-id="remember" binary />
                        <label for="remember">Remember me</label>
                    </div>
                    <Button type="submit" :label="form.processing ? 'Signing in...' : 'Sign in'" :loading="form.processing" />
                </form>
                <Divider />
                <p class="text-center text-sm">Don't have an account? <a href="/register" class="text-primary font-medium">Register</a></p>
            </template>
        </Card>
    </main>
</template>
