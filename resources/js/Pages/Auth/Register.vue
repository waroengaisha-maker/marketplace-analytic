<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import Card from 'primevue/card'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import InputPassword from 'primevue/inputpassword'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import Message from 'primevue/message'
import { ref } from 'vue'

const form = useForm({ name: '', username: '', email: '', phone: '', password: '', password_confirmation: '' })
const clientError = ref('')

function submit() {
    clientError.value = ''

    if (form.password.length < 8) {
        clientError.value = 'Password minimal 8 karakter.'
        return
    }

    if (form.password !== form.password_confirmation) {
        clientError.value = 'Konfirmasi password tidak sama.'
        return
    }

    form.post('/register')
}

function sanitizePhone(value: string) {
    form.phone = value.replace(/\D/g, '')
}
</script>

<template>
    <Head title="Register" />
    <main class="min-h-screen flex items-center justify-center bg-surface-50 p-4">
        <Card class="w-full max-w-md">
            <template #title>Marketplace Analytics</template>

<style scoped>
.required-mark {
    color: var(--p-primary-color);
    margin-left: 0.125rem;
}
</style>
            <template #subtitle>Create your account</template>
            <template #content>
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <Message v-if="clientError" severity="error">{{ clientError }}</Message>
                    <Message v-if="form.errors.password" severity="error">{{ form.errors.password }}</Message>
                    <Message v-if="form.errors.password_confirmation" severity="error">{{ form.errors.password_confirmation }}</Message>
                    <div class="flex flex-col gap-2">
                        <label for="name">Name <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-user" />
                            <InputText id="name" v-model="form.name" autocomplete="name" class="w-full" required />
                        </IconField>
                        <small v-if="form.errors.name" class="p-error">{{ form.errors.name }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="username">Username <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-at" />
                            <InputText id="username" v-model="form.username" autocomplete="username" class="w-full" required />
                        </IconField>
                        <small v-if="form.errors.username" class="p-error">{{ form.errors.username }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-envelope" />
                            <InputText id="email" v-model="form.email" type="email" autocomplete="email" class="w-full" required />
                        </IconField>
                        <small v-if="form.errors.email" class="p-error">{{ form.errors.email }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="phone">Nomor handphone (opsional)</label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-phone" />
                            <InputText
                                id="phone"
                                v-model="form.phone"
                                type="tel"
                                inputmode="numeric"
                                autocomplete="tel"
                                class="w-full"
                                @input="sanitizePhone(($event.target as HTMLInputElement).value)"
                            />
                        </IconField>
                        <small v-if="form.errors.phone" class="p-error">{{ form.errors.phone }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="password">Password <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-lock" />
                            <InputPassword
                                id="password"
                                v-model="form.password"
                                input-class="w-full"
                                toggle-mask
                                :feedback="true"
                                autocomplete="new-password"
                                minlength="8"
                                required
                                fluid
                                prompt-label="Choose a password"
                                weak-label="Weak"
                                medium-label="Medium"
                                strong-label="Strong"
                            />
                        </IconField>
                        <small class="text-color-secondary">Minimal 8 karakter.</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="password_confirmation">Confirm password <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-lock" />
                            <InputPassword
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                input-class="w-full"
                                toggle-mask
                                :feedback="true"
                                autocomplete="new-password"
                                required
                                fluid
                                prompt-label="Confirm your password"
                                weak-label="Weak"
                                medium-label="Medium"
                                strong-label="Strong"
                            />
                        </IconField>
                    </div>
                    <Button type="submit" :label="form.processing ? 'Creating account...' : 'Create account'" :loading="form.processing" />
                </form>
                <Divider />
                <p class="text-center text-sm">Already have an account? <Link href="/login" class="text-primary font-medium">Sign in</Link></p>
            </template>
        </Card>
    </main>
</template>
