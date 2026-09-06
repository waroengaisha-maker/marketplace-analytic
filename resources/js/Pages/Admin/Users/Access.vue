<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import Message from 'primevue/message'

type User = {
    id: number
    name: string
    email: string
    role: string
    status: string
    trial_ends_at: string | null
    username: string
    phone: string | null
}

const props = defineProps<{ users: { data: User[] }; trialDays: number }>()

const activate = (id: number) => router.post(`/admin/users/${id}/activate`, { trial_days: props.trialDays })
const suspend = (id: number) => router.post(`/admin/users/${id}/suspend`)
const setTrial = (id: number) => router.post(`/admin/users/${id}/trial`, { trial_days: props.trialDays })
const editingId = ref<number | null>(null)
const form = useForm({ name: '', username: '', email: '', phone: '', password: '', password_confirmation: '', role: 'user' })
const startEdit = (user: User) => {
    editingId.value = user.id
    form.defaults({ name: user.name, username: user.username, email: user.email, phone: user.phone ?? '', password: '', password_confirmation: '', role: user.role })
    form.reset()
}
const cancelEdit = () => { editingId.value = null; form.reset() }
const submit = () => {
    if (editingId.value) {
        form.put(`/admin/users/${editingId.value}`, { onSuccess: cancelEdit })
        return
    }
    form.post('/admin/users', { onSuccess: () => form.reset('name', 'username', 'email', 'phone', 'password', 'password_confirmation') })
}
const remove = (id: number) => router.delete(`/admin/users/${id}`)
const validationError = () => Object.values(form.errors)[0] || ''
</script>

<template>
    <div class="p-6">
        <Head title="Kelola Akses User" />
        <h1 class="mb-6 text-2xl font-semibold">Kelola Akses User Aplikasi</h1>
        <p class="mb-6 text-sm text-slate-500">Aktifkan, atur trial, atau suspend akun pengguna aplikasi.</p>
        <Message v-if="validationError()" class="mb-4" severity="error">{{ validationError() }}</Message>
        <form class="mb-6 grid gap-3 rounded border p-4 md:grid-cols-4" @submit.prevent="submit">
            <input v-model="form.name" class="rounded border p-2" placeholder="Nama" required>
            <input v-model="form.username" class="rounded border p-2" placeholder="Username" required>
            <input v-model="form.email" class="rounded border p-2" type="email" placeholder="Email" required>
            <input v-model="form.phone" class="rounded border p-2" placeholder="Nomor handphone">
            <input v-model="form.password" class="rounded border p-2" type="password" :placeholder="editingId ? 'Password baru (opsional)' : 'Password'" :required="!editingId">
            <input v-model="form.password_confirmation" class="rounded border p-2" type="password" placeholder="Konfirmasi password" :required="!!form.password">
            <div class="flex gap-2">
                <button class="rounded bg-blue-600 px-3 py-2 text-white" type="submit">{{ editingId ? 'Simpan' : 'Tambah User' }}</button>
                <button v-if="editingId" class="rounded bg-slate-500 px-3 py-2 text-white" type="button" @click="cancelEdit">Batal</button>
            </div>
        </form>
        <div class="overflow-x-auto rounded border">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b bg-slate-50">
                        <th class="p-3">Akun</th>
                        <th class="p-3">Status Akses</th>
                        <th class="p-3">Trial Berakhir</th>
                        <th class="p-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in props.users.data" :key="user.id" class="border-b">
                        <td class="p-3">
                            <div>{{ user.name }}</div>
                            <div class="text-sm text-slate-500">{{ user.email }}</div>
                        </td>
                        <td class="p-3">{{ user.status }}</td>
                        <td class="p-3">{{ user.trial_ends_at ?? '—' }}</td>
                        <td class="flex flex-wrap gap-2 p-3">
                            <button class="rounded bg-slate-700 px-3 py-1 text-white" @click="startEdit(user)">Edit</button>
                            <button class="rounded bg-red-600 px-3 py-1 text-white" @click="remove(user.id)">Hapus</button>
                            <button class="rounded bg-green-600 px-3 py-1 text-white" @click="activate(user.id)">Aktifkan</button>
                            <button class="rounded bg-amber-600 px-3 py-1 text-white" @click="setTrial(user.id)">Atur Trial</button>
                            <button class="rounded bg-red-600 px-3 py-1 text-white" @click="suspend(user.id)">Suspend</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
