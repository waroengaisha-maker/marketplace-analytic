<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'

type User = {
    id: number
    name: string
    email: string
    role: string
    status: string
    trial_ends_at: string | null
}

defineProps<{ users: { data: User[] }; trialDays: number }>()

const activate = (id: number, trialDays: number) => router.post(`/admin/users/${id}/activate`, { trial_days: trialDays })
const suspend = (id: number) => router.post(`/admin/users/${id}/suspend`)
const setTrial = (id: number, trialDays: number) => router.post(`/admin/users/${id}/trial`, { trial_days: trialDays })
</script>

<template>
    <div class="p-6">
        <Head title="Manage users" />
        <h1 class="mb-6 text-2xl font-semibold">Manage users</h1>
        <div class="overflow-x-auto rounded border">
            <table class="w-full text-left">
                <thead><tr class="border-b bg-slate-50"><th class="p-3">User</th><th class="p-3">Status</th><th class="p-3">Trial ends</th><th class="p-3">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="border-b">
                        <td class="p-3"><div>{{ user.name }}</div><div class="text-sm text-slate-500">{{ user.email }}</div></td>
                        <td class="p-3">{{ user.status }}</td>
                        <td class="p-3">{{ user.trial_ends_at ?? '—' }}</td>
                        <td class="flex gap-2 p-3">
                            <button class="rounded bg-green-600 px-3 py-1 text-white" @click="activate(user.id, trialDays)">Activate</button>
                            <button class="rounded bg-amber-600 px-3 py-1 text-white" @click="setTrial(user.id, trialDays)">Set trial</button>
                            <button class="rounded bg-red-600 px-3 py-1 text-white" @click="suspend(user.id)">Suspend</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
