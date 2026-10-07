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
        <div class="w-full max-w-xl rounded-2xl border border-surface-200 bg-surface-0 p-8 shadow-sm dark:border-surface-700 dark:bg-surface-900">
        <Head title="Account status" />
        <h1 class="mb-4 text-2xl font-semibold text-color">{{ user.account_status === 'pending' ? 'Registration successful' : 'Account unavailable' }}</h1>
        <p class="mb-2 text-color">Your account is currently {{ user.account_status }}.</p>
        <p v-if="user.account_status === 'pending'" class="mb-6 text-color-secondary">Your account has been created successfully. An administrator must activate it before you can access the application.</p>
        <p v-else class="mb-6 text-color-secondary">Your account does not currently have access. Please contact an administrator.</p>
        <Link href="/logout" method="post" as="button" class="rounded-lg bg-primary px-4 py-2 font-medium text-primary-contrast transition-colors hover:bg-primary-emphasis focus:outline-none focus:ring-2 focus:ring-primary/30">
            Sign out
        </Link>
        </div>
    </main>
</template>
