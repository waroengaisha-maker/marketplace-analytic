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
    <div class="mx-auto max-w-xl p-8">
        <Head title="Account status" />
        <h1 class="mb-4 text-2xl font-semibold">Account unavailable</h1>
        <p class="mb-2">Your account is currently {{ user.account_status }}.</p>
        <p class="mb-6">An administrator must activate your account before you can access the application.</p>
        <Link href="/logout" method="post" as="button" class="rounded bg-slate-800 px-4 py-2 text-white">
            Sign out
        </Link>
    </div>
</template>
