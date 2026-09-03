<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ auth: { user: { name: string; email: string } } }>();
const logout = useForm({});

function submitLogout() {
    logout.post('/logout');
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-gray-100">
        <header class="flex items-center justify-between bg-white px-8 py-4 shadow-sm">
            <h1 class="text-xl font-bold text-gray-900">Marketplace Analytics</h1>
            <button
                type="button"
                :disabled="logout.processing"
                class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                @click="submitLogout"
            >
                Logout
            </button>
        </header>

        <main class="mx-auto max-w-5xl p-8">
            <div class="rounded-xl bg-white p-8 shadow-sm">
                <p class="text-sm font-medium text-gray-500">Dashboard</p>
                <h2 class="mt-2 text-3xl font-bold text-gray-900">
                    Selamat datang Yaaa, {{ page.props.auth.user.name }}!
                </h2>
                <p class="mt-3 text-gray-600">
                    Anda berhasil login sebagai {{ page.props.auth.user.email }}.
                </p>
            </div>
        </main>
    </div>
</template>
