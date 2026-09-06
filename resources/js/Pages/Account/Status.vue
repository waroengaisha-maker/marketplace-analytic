<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

defineProps<{
    user: {
        name: string
        account_status: string
        trial_ends_at?: string | null
    }
}>()
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-surface-50 p-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-8 shadow-sm">
        <Head title="Account status" />
        <h1 class="mb-4 text-2xl font-semibold">{{ user.account_status === 'pending' ? 'Registration successful' : 'Account unavailable' }}</h1>
        <p class="mb-2">Your account is currently {{ user.account_status }}.</p>
        <p v-if="user.account_status === 'pending'" class="mb-6">Your account has been created successfully. An administrator must activate it before you can access the application.</p>
        <p v-else class="mb-6">Your account does not currently have access. Please contact an administrator.</p>
        <Link href="/logout" method="post" as="button" class="rounded bg-slate-800 px-4 py-2 text-white">
            Sign out
        </Link>
        </div>
    </main>
</template>
