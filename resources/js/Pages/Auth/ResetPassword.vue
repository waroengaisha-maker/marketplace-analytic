<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import InputPassword from 'primevue/inputpassword'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Dialog from 'primevue/dialog'
import { computed, ref } from 'vue'

const props = defineProps<{ email: string; token: string }>()
const page = usePage<{
    flash?: {
        success?: string
    }
}>()
const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})
const clientError = ref('')
const resetSuccess = ref(Boolean(page.props.flash?.success))
const passwordTouched = ref(false)
const confirmationTouched = ref(false)
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

const passwordRequirementsMet = computed(() =>
    passwordRequirements.value.length === 5 &&
    passwordRequirements.value.every((requirement) => requirement.valid),
)

const passwordIsInvalid = computed(() =>
    Boolean(form.errors.password) ||
    (passwordTouched.value || submitAttempted.value) && !passwordRequirementsMet.value,
)

const confirmationIsInvalid = computed(() =>
    Boolean(form.errors.password_confirmation) ||
    (confirmationTouched.value || submitAttempted.value) &&
    (!form.password_confirmation || form.password !== form.password_confirmation),
)

function submit() {
    clientError.value = ''
    submitAttempted.value = true

    if (!passwordRequirementsMet.value) {
        clientError.value = 'Password belum memenuhi semua persyaratan.'
        return
    }

    if (!form.password_confirmation || form.password !== form.password_confirmation) {
        clientError.value = 'Konfirmasi password tidak sama.'
        return
    }

    form.post('/reset-password', {
        preserveScroll: true,
        onSuccess: () => {
            resetSuccess.value = true
        },
    })
}
</script>

<template>
    <Head title="Reset password" />

    <Dialog
        v-model:visible="resetSuccess"
        modal
        header="Password berhasil diperbarui"
        :closable="false"
        :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }"
    >
        <div class="flex flex-col items-center gap-4 py-2 text-center">
            <i class="pi pi-check-circle text-4xl text-primary" aria-hidden="true" />
            <p class="m-0 text-color-secondary">
                Password Anda berhasil diubah. Silakan login menggunakan password baru Anda.
            </p>
            <Button
                label="Go to Login"
                icon="pi pi-sign-in"
                class="w-full"
                @click="router.visit('/login')"
            />
        </div>
    </Dialog>

    <main class="flex min-h-screen items-center justify-center bg-surface-50 p-4">
        <Card class="w-full max-w-md">
            <template #title>Set a new password</template>

            <template #content>
                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <Message
                        v-if="page.props.errors?.email || page.props.errors?.token"
                        severity="error"
                        variant="simple"
                        icon="pi pi-exclamation-circle"
                    >
                        {{ page.props.errors?.email || page.props.errors?.token }}
                    </Message>

                    <Message
                        v-if="clientError"
                        severity="error"
                        variant="simple"
                        icon="pi pi-exclamation-circle"
                    >
                        {{ clientError }}
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
                            :invalid="Boolean(form.errors.email)"
                            required
                            fluid
                        />

                        <small v-if="form.errors.email" class="p-error">{{ form.errors.email }}</small>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password">
                            New password
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>

                        <div class="relative">
                            <IconField iconPosition="left">
                                <InputIcon class="pi pi-lock" />
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
                            </IconField>

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

                        <ul
                            v-if="form.password"
                            class="m-0 flex list-none flex-col gap-1 p-0 text-sm text-color-secondary"
                            aria-label="Password requirements"
                        >
                            <li
                                v-for="requirement in passwordRequirements"
                                :key="requirement.label"
                                class="flex items-center gap-2"
                            >
                                <i
                                    :class="requirement.valid ? 'pi pi-check text-primary' : 'pi pi-circle'"
                                    aria-hidden="true"
                                />
                                <span>{{ requirement.label }}</span>
                            </li>
                        </ul>

                        <small v-if="form.errors.password" class="p-error">{{ form.errors.password }}</small>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label for="password_confirmation">
                            Confirm password
                            <span class="required-mark" aria-hidden="true">*</span>
                        </label>

                        <div class="relative">
                            <IconField iconPosition="left">
                                <InputIcon class="pi pi-lock" />
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
                            </IconField>

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

                        <ul
                            v-if="form.password_confirmation"
                            class="m-0 flex list-none flex-col gap-1 p-0 text-sm text-color-secondary"
                            aria-label="Password confirmation requirements"
                        >
                            <li class="flex items-center gap-2">
                                <i
                                    :class="form.password_confirmation === form.password ? 'pi pi-check text-primary' : 'pi pi-circle'"
                                    aria-hidden="true"
                                />
                                <span>Password matches</span>
                            </li>
                        </ul>

                        <small v-if="form.errors.password_confirmation" class="p-error">
                            {{ form.errors.password_confirmation }}
                        </small>
                    </div>

                    <Button
                        type="submit"
                        :label="form.processing ? 'Updating...' : 'Update password'"
                        :loading="form.processing"
                        :disabled="!passwordRequirementsMet || !form.password_confirmation || form.password !== form.password_confirmation || form.processing"
                    />

                    <Link href="/login" class="text-center text-sm font-medium text-primary">
                        Back to login
                    </Link>
                </form>
            </template>
        </Card>
    </main>
</template>

<style scoped>
.password-toggle {
    position: absolute !important;
    top: 50% !important;
    right: 0.75rem !important;
    z-index: 2;
    width: 1.5rem;
    height: 1.5rem;
    transform: translateY(-50%);
    margin: 0 !important;
    background: transparent !important;
}

.password-toggle::before {
    content: '';
    position: absolute;
    width: 2rem;
    height: 1.15rem;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    z-index: -1;
    border-radius: 0.2rem;
    background: var(--p-form-field-background);
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
</style>
