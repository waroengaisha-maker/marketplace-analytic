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
const passwordTouched = ref(false)
const confirmationTouched = ref(false)
const emailTouched = ref(false)
const submitAttempted = ref(false)
const passwordMasked = ref(true)
const confirmationMasked = ref(true)

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

const passwordRequirementsMet = computed(() =>
    passwordRequirements.value.length === 5 &&
    passwordRequirements.value.every((requirement) => requirement.valid),
)

const emailIsValid = computed(() =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim()),
)

const registrationReady = computed(() =>
    Boolean(form.name) &&
    Boolean(form.username) &&
    emailIsValid.value &&
    passwordRequirementsMet.value &&
    Boolean(form.password_confirmation) &&
    form.password === form.password_confirmation,
)

const emailIsInvalid = computed(() =>
    Boolean(form.errors.email) ||
    (emailTouched.value || submitAttempted.value) && !emailIsValid.value,
)

const passwordIsInvalid = computed(() =>
    Boolean(form.errors.password) ||
    (passwordTouched.value || submitAttempted.value) && !passwordRequirementsMet.value,
)
const confirmationIsInvalid = computed(() =>
    Boolean(form.errors.password_confirmation) ||
    (confirmationTouched.value || submitAttempted.value) &&
    form.password !== form.password_confirmation,
)

function submit() {
    clientError.value = ''
    submitAttempted.value = true

    if (!emailIsValid.value) {
        clientError.value = 'Masukkan alamat email yang valid.'
        return
    }

    if (!passwordRequirementsMet.value) {
        clientError.value = 'Password belum memenuhi semua persyaratan.'
        return
    }

    if (!form.password_confirmation || form.password !== form.password_confirmation) {
        clientError.value = 'Konfirmasi password tidak sama.'
        return
    }

    form.post('/register')
}

function sanitizePhone(value: string) {
    form.phone = value.replace(/\D/g, '')
}

function preventNonNumericPhoneInput(event: InputEvent) {
    if (event.data && /\D/.test(event.data)) {
        event.preventDefault()
    }
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
                            <InputText id="email" v-model="form.email" type="email" autocomplete="email" class="w-full" :invalid="emailIsInvalid" required @blur="emailTouched = true" />
                        </IconField>
                        <small v-if="form.errors.email" class="p-error">{{ form.errors.email }}</small>
                        <small v-else-if="emailIsInvalid" class="p-error">Masukkan alamat email yang valid.</small>
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
                                pattern="[0-9]*"
                                autocomplete="tel"
                                class="w-full"
                                :invalid="Boolean(form.errors.phone)"
                                @beforeinput="preventNonNumericPhoneInput"
                                @input="sanitizePhone(($event.target as HTMLInputElement).value)"
                            />
                        </IconField>
                        <small v-if="form.errors.phone" class="p-error">{{ form.errors.phone }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="password">Password <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="relative">
                            <InputPassword
                                id="password"
                                v-model="form.password"
                                :mask="passwordMasked"
                                input-class="w-full pr-12"
                                :invalid="passwordIsInvalid"
                                :feedback="false"
                                autocomplete="new-password"
                                required
                                fluid
                                @blur="passwordTouched = true"
                            />
                            <Button
                                type="button"
                                :icon="passwordMasked ? 'pi pi-eye' : 'pi pi-eye-slash'"
                                variant="text"
                                severity="secondary"
                                rounded
                                class="password-toggle !p-0 text-color-secondary"
                                :aria-label="passwordMasked ? 'Show password' : 'Hide password'"
                                :aria-pressed="!passwordMasked"
                                @mousedown.prevent
                                @click="passwordMasked = !passwordMasked"
                            />
                        </div>
                        <ul v-if="form.password" class="m-0 p-0 list-none flex flex-col gap-1 text-sm text-color-secondary" aria-label="Password requirements">
                            <li v-for="requirement in passwordRequirements" :key="requirement.label" class="flex items-center gap-2">
                                <i :class="requirement.valid ? 'pi pi-check text-primary' : 'pi pi-circle'" aria-hidden="true" />
                                <span>{{ requirement.label }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="password_confirmation">Confirm password <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="relative">
                            <InputPassword
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :mask="confirmationMasked"
                                input-class="w-full pr-12"
                                :invalid="confirmationIsInvalid"
                                :feedback="false"
                                autocomplete="new-password"
                                required
                                fluid
                                @blur="confirmationTouched = true"
                            />
                            <Button
                                type="button"
                                :icon="confirmationMasked ? 'pi pi-eye' : 'pi pi-eye-slash'"
                                variant="text"
                                severity="secondary"
                                rounded
                                class="password-toggle !p-0 text-color-secondary"
                                :aria-label="confirmationMasked ? 'Show password' : 'Hide password'"
                                :aria-pressed="!confirmationMasked"
                                @mousedown.prevent
                                @click="confirmationMasked = !confirmationMasked"
                            />
                        </div>
                        <ul v-if="form.password_confirmation" class="m-0 p-0 list-none flex flex-col gap-1 text-sm text-color-secondary" aria-label="Password confirmation requirements">
                            <li v-for="requirement in confirmationRequirements" :key="requirement.label" class="flex items-center gap-2">
                                <i :class="requirement.valid ? 'pi pi-check text-primary' : 'pi pi-circle'" aria-hidden="true" />
                                <span>{{ requirement.label }}</span>
                            </li>
                        </ul>
                    </div>
                    <Button
                        type="submit"
                        :label="form.processing ? 'Creating account...' : 'Create account'"
                        :loading="form.processing"
                        :disabled="form.processing || !registrationReady"
                    />
                    <small v-if="!registrationReady && !form.processing" class="flex items-start justify-center gap-2 text-center text-color-secondary">
                        <i class="pi pi-info-circle mt-0.5 shrink-0" aria-hidden="true" />
                        <span>Complete all required fields, fulfill all password requirements, and make sure the passwords match to enable account creation.</span>
                    </small>
                </form>
                <Divider />
                <p class="text-center text-sm">Already have an account? <Link href="/login" class="register-link font-medium no-underline">Sign in</Link></p>
            </template>
        </Card>
    </main>
</template>

<style scoped>
.password-toggle {
    position: absolute !important;
    top: 50% !important;
    right: 0.25rem !important;
    z-index: 9999;
    width: 2.25rem;
    height: 2.25rem;
    transform: translateY(-50%);
    margin: 0 !important;
    background: var(--p-form-field-background) !important;
}

:deep([data-pc-section="maskicon"]),
:deep([data-pc-section="unmaskicon"]),
:deep(.p-password-toggle-mask-icon),
:deep(.p-inputpassword-toggle-mask-icon) {
    display: none !important;
}

.required-mark {
    color: var(--p-primary-color);
    margin-left: 0.125rem;
}

.register-link {
    color: var(--p-primary-color);
}

.register-link:hover {
    color: var(--p-primary-color);
}
</style>
