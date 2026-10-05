<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import Card from 'primevue/card'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import InputPassword from 'primevue/inputpassword'
import MeterGroup from 'primevue/metergroup'
import Popover from 'primevue/popover'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import Message from 'primevue/message'
import { computed, ref } from 'vue'

const form = useForm({ name: '', username: '', email: '', phone: '', password: '', password_confirmation: '' })
const clientError = ref('')
const passwordPopover = ref<InstanceType<typeof Popover> | null>(null)
const confirmationPopover = ref<InstanceType<typeof Popover> | null>(null)
const passwordMask = ref(true)
const confirmationMask = ref(true)

const passwordRequirements = computed(() => [
    { label: 'At least 8 characters', valid: form.password.length >= 8 },
    { label: 'Uppercase letter', valid: /[A-Z]/.test(form.password) },
    { label: 'Lowercase letter', valid: /[a-z]/.test(form.password) },
    { label: 'Number', valid: /\d/.test(form.password) },
    { label: 'Special character', valid: /[^A-Za-z0-9]/.test(form.password) },
])

const passwordStrength = computed(() => passwordRequirements.value.filter((requirement) => requirement.valid).length)
const passwordMeter = computed(() => [{ label: 'Password strength', value: passwordStrength.value * 20 }])
const passwordStrengthLabel = computed(() => {
    if (passwordStrength.value <= 1) return 'Weak'
    if (passwordStrength.value <= 3) return 'Medium'
    if (passwordStrength.value === 4) return 'Strong'
    return 'Very strong'
})
const passwordIsInvalid = computed(() => Boolean(form.errors.password) || form.password.length > 0 && form.password.length < 8)
const confirmationIsInvalid = computed(() => Boolean(form.errors.password_confirmation) || form.password_confirmation.length > 0 && form.password !== form.password_confirmation)

function showPasswordPopover(event: FocusEvent) {
    passwordPopover.value?.show(event, event.currentTarget as HTMLElement)
}

function showConfirmationPopover(event: FocusEvent) {
    confirmationPopover.value?.show(event, event.currentTarget as HTMLElement)
}

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
                            <InputText id="name" v-model="form.name" autocomplete="name" class="w-full" :invalid="Boolean(form.errors.name)" required />
                        </IconField>
                        <small v-if="form.errors.name" class="p-error">{{ form.errors.name }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="username">Username <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-at" />
                            <InputText id="username" v-model="form.username" autocomplete="username" class="w-full" :invalid="Boolean(form.errors.username)" required />
                        </IconField>
                        <small v-if="form.errors.username" class="p-error">{{ form.errors.username }}</small>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                        <IconField iconPosition="left">
                            <InputIcon class="pi pi-envelope" />
                            <InputText id="email" v-model="form.email" type="email" autocomplete="email" class="w-full" :invalid="Boolean(form.errors.email)" required />
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
                                minlength="8"
                                required
                                fluid
                                @focus="showPasswordPopover"
                                aria-describedby="password-help"
                            />
                        </IconField>
                        <Popover ref="passwordPopover" aria-label="Password requirements">
                            <div class="w-72 flex flex-col gap-3">
                                <div class="font-medium">Password requirements</div>
                                <MeterGroup :value="passwordMeter" :max="100" />
                                <div class="flex justify-between text-sm text-color-secondary">
                                    <span>{{ passwordStrengthLabel }}</span>
                                    <span>{{ passwordStrength }}/5</span>
                                </div>
                                <ul class="m-0 p-0 list-none flex flex-col gap-2 text-sm">
                                    <li v-for="requirement in passwordRequirements" :key="requirement.label" class="flex items-center gap-2">
                                        <i :class="requirement.valid ? 'pi pi-check text-primary' : 'pi pi-circle text-color-secondary'" aria-hidden="true" />
                                        <span>{{ requirement.label }}</span>
                                    </li>
                                </ul>
                            </div>
                        </Popover>
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
                                @focus="showConfirmationPopover"
                                aria-describedby="password-confirmation-help"
                            />
                        </IconField>
                        <small id="password-confirmation-help" class="text-color-secondary">Harus sama dengan password.</small>
                        <Popover ref="confirmationPopover" aria-label="Password confirmation requirements">
                            <div class="w-72 flex flex-col gap-3">
                                <div class="font-medium">Password confirmation</div>
                                <div class="flex items-center gap-2 text-sm">
                                    <i :class="form.password_confirmation && form.password === form.password_confirmation ? 'pi pi-check text-primary' : 'pi pi-circle text-color-secondary'" aria-hidden="true" />
                                    <span>{{ form.password_confirmation && form.password === form.password_confirmation ? 'Passwords match' : 'Passwords must match' }}</span>
                                </div>
                            </div>
                        </Popover>
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
