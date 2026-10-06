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
import { computed, ref } from 'vue'

const form = useForm({ name: '', username: '', email: '', phone: '', password: '', password_confirmation: '' })
const clientError = ref('')
const passwordMask = ref(true)
const confirmationMask = ref(true)
const passwordTouched = ref(false)
const confirmationTouched = ref(false)
const submitAttempted = ref(false)

const passwordRequirements = computed(() => [
    { label: 'At least 12 characters', valid: form.password.length >= 12 },
    { label: 'Contains uppercase letter', valid: /[A-Z]/.test(form.password) },
    { label: 'Contains lowercase letter', valid: /[a-z]/.test(form.password) },
    { label: 'Contains number', valid: /\d/.test(form.password) },
    { label: 'Contains special character', valid: /[^A-Za-z0-9]/.test(form.password) },
])

const confirmationRequirements = computed(() => [
    { label: 'Password matches', valid: Boolean(form.password_confirmation) && form.password_confirmation === form.password },
])

const passwordIsInvalid = computed(() =>
    Boolean(form.errors.password) ||
    (passwordTouched.value || submitAttempted.value) && !form.password,
)
const confirmationIsInvalid = computed(() =>
    Boolean(form.errors.password_confirmation) ||
    (confirmationTouched.value || submitAttempted.value) &&
    form.password !== form.password_confirmation,
)

function submit() {
    clientError.value = ''
    submitAttempted.value = true

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
                            <InputText id="name" v-model="form.name" autocomplete="name" class="w-full" :invalid="Boolean(form.errors.name) || (submitAttempted && !form.name)" required />
                        </IconField>
                        <small v-if="form.errors.name" class="p-error">{{ form.errors.name }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="username">Username <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-at" />
                            <InputText id="username" v-model="form.username" autocomplete="username" class="w-full" :invalid="Boolean(form.errors.username) || (submitAttempted && !form.username)" required />
                        </IconField>
                        <small v-if="form.errors.username" class="p-error">{{ form.errors.username }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-envelope" />
                            <InputText id="email" v-model="form.email" type="email" autocomplete="email" class="w-full" :invalid="Boolean(form.errors.email) || (submitAttempted && !form.email)" required />
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
                                :invalid="Boolean(form.errors.phone)"
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
                                :invalid="passwordIsInvalid"
                                v-model:mask="passwordMask"
                                :feedback="false"
                                autocomplete="new-password"
                                required
                                fluid
                                @blur="passwordTouched = true"
                            />
                        </IconField>
                        <ul class="m-0 p-0 list-none flex flex-col gap-1 text-sm text-color-secondary" aria-label="Password requirements">
                            <li v-for="requirement in passwordRequirements" :key="requirement.label" class="flex items-center gap-2">
                                <i :class="requirement.valid ? 'pi pi-check text-primary' : 'pi pi-circle'" aria-hidden="true" />
                                <span>{{ requirement.label }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="password_confirmation">Confirm password <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-lock" />
                            <InputPassword
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                input-class="w-full"
                                :invalid="confirmationIsInvalid"
                                v-model:mask="confirmationMask"
                                :feedback="false"
                                autocomplete="new-password"
                                required
                                fluid
                                @blur="confirmationTouched = true"
                            />
                        </IconField>
                        <ul class="m-0 p-0 list-none flex flex-col gap-1 text-sm text-color-secondary" aria-label="Password confirmation requirements">
                            <li v-for="requirement in confirmationRequirements" :key="requirement.label" class="flex items-center gap-2">
                                <i :class="requirement.valid ? 'pi pi-check text-primary' : 'pi pi-circle'" aria-hidden="true" />
                                <span>{{ requirement.label }}</span>
                            </li>
                        </ul>
                    </div>
                    <Button type="submit" :label="form.processing ? 'Creating account...' : 'Create account'" :loading="form.processing" />
                </form>
                <Divider />
                <p class="text-center text-sm">Already have an account? <Link href="/login" class="text-primary font-medium">Sign in</Link></p>
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
