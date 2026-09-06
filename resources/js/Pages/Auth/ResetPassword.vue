<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { ref } from 'vue'

const props = defineProps<{ email: string; token: string }>()
const page = usePage()
const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})
const clientError = ref('')

function submit() {
    clientError.value = form.password.length < 8
        ? 'Password minimal 8 karakter.'
        : form.password !== form.password_confirmation
            ? 'Konfirmasi password tidak sama.'
            : ''
    if (clientError.value) return
    form.post('/reset-password')
}
</script>

<template>
    <Head title="Reset password" />
    <main class="flex min-h-screen items-center justify-center bg-surface-50 p-4">
        <Card class="w-full max-w-md">
            <template #title>Set a new password</template>
            <template #content>
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <Message v-if="page.props.errors?.email || page.props.errors?.token" severity="error">
                        {{ page.props.errors?.email || page.props.errors?.token }}
                    </Message>
                    <Message v-if="clientError" severity="error">{{ clientError }}</Message>
                    <div class="flex flex-col gap-2">
                        <label for="email">Email</label>
                        <InputText id="email" v-model="form.email" type="email" autocomplete="email" required />
                        <small v-if="form.errors.email" class="p-error">{{ form.errors.email }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="password">New password</label>
                        <Password id="password" v-model="form.password" input-class="w-full" toggle-mask autocomplete="new-password" minlength="8" required />
                        <small v-if="form.errors.password" class="p-error">{{ form.errors.password }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="password_confirmation">Confirm password</label>
                        <Password id="password_confirmation" v-model="form.password_confirmation" input-class="w-full" toggle-mask :feedback="false" autocomplete="new-password" required />
                    </div>
                    <Button type="submit" :label="form.processing ? 'Updating...' : 'Update password'" :loading="form.processing" />
                    <Link href="/login" class="text-center text-sm text-primary font-medium">Back to login</Link>
                </form>
            </template>
        </Card>
    </main>
</template>
