<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register');
}
</script>

<template>
    <Head title="Register" />

    <div class="flex min-h-screen items-center justify-center bg-gray-100 px-4">
        <div class="w-full max-w-md rounded-lg bg-white p-8 shadow">
            <h1 class="text-2xl font-bold text-gray-900">Marketplace Analytics</h1>
            <p class="mt-2 text-gray-600">Create your account</p>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input v-model="form.name" type="text" autocomplete="name" required class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input v-model="form.email" type="email" autocomplete="email" required class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input v-model="form.password" type="password" autocomplete="new-password" required class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2" />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirm password</label>
                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2" />
                </div>

                <button type="submit" :disabled="form.processing" class="w-full rounded-md bg-gray-900 px-4 py-2 font-medium text-white disabled:opacity-50">
                    {{ form.processing ? 'Creating account...' : 'Create account' }}
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-600">
                Already have an account? <a href="/login" class="font-medium text-gray-900 underline">Sign in</a>
            </p>
        </div>
    </div>
</template>
