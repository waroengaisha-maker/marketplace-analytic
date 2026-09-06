<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import Message from 'primevue/message'
import { confirmAction } from '../../../utils/confirmAction'

type User = {
    id: number
    name: string
    email: string
    role: string
    status: string
    trial_ends_at: string | null
    is_admin: boolean
    username: string
    phone: string | null
}

const props = defineProps<{ users: { data: User[] }; trialDays: number; canManageRoles: boolean }>()

const updateRole = (id: number, role: string) => {
    const action = role === 'admin' ? 'Jadikan admin' : 'Hapus akses admin'

    if (confirmAction(`${action} untuk akun ini?`)) {
        router.post(`/admin/users/${id}/role`, { role })
    }
}
const editingId = ref<number | null>(null)
const form = useForm({ name: '', username: '', email: '', phone: '', password: '', password_confirmation: '', role: 'admin' })
const startEdit = (user: User) => {
    editingId.value = user.id
    form.defaults({ name: user.name, username: user.username, email: user.email, phone: user.phone ?? '', password: '', password_confirmation: '', role: user.role })
    form.reset()
}
const cancelEdit = () => { editingId.value = null; form.reset() }
const submit = () => {
    if (editingId.value) {
        if (confirmAction('Simpan perubahan akun admin ini?')) {
            form.put(`/admin/users/${editingId.value}`, { onSuccess: cancelEdit })
        }
        return
    }
    if (confirmAction('Tambah akun admin baru?')) {
        form.post('/admin/users', { onSuccess: () => form.reset('name', 'username', 'email', 'phone', 'password', 'password_confirmation') })
    }
}
const remove = (id: number) => {
    if (confirmAction('Hapus akun ini? Tindakan ini tidak dapat dibatalkan.')) {
        router.delete(`/admin/users/${id}`)
    }
}
const validationError = () => Object.values(form.errors)[0] || ''
</script>

<template>
    <div class="p-6">
        <Head title="Kelola Admin" />
        <h1 class="mb-6 text-2xl font-semibold">Kelola Admin</h1>
        <p class="mb-6 text-sm text-slate-500">Kelola akun admin aplikasi dan ubah aksesnya menjadi user aplikasi.</p>
        <Message v-if="validationError()" class="mb-4" severity="error">{{ validationError() }}</Message>
        <form class="mb-6 grid gap-3 rounded border p-4 md:grid-cols-4" @submit.prevent="submit">
            <input v-model="form.name" class="rounded border p-2" placeholder="Nama" required>
            <input v-model="form.username" class="rounded border p-2" placeholder="Username" required>
            <input v-model="form.email" class="rounded border p-2" type="email" placeholder="Email" required>
            <input v-model="form.phone" class="rounded border p-2" placeholder="Nomor handphone">
            <input v-model="form.password" class="rounded border p-2" type="password" :placeholder="editingId ? 'Password baru (opsional)' : 'Password'" :required="!editingId">
            <input v-model="form.password_confirmation" class="rounded border p-2" type="password" placeholder="Konfirmasi password" :required="!!form.password">
            <div class="flex gap-2">
                <button class="rounded bg-blue-600 px-3 py-2 text-white" type="submit">{{ editingId ? 'Simpan' : 'Tambah Admin' }}</button>
                <button v-if="editingId" class="rounded bg-slate-500 px-3 py-2 text-white" type="button" @click="cancelEdit">Batal</button>
            </div>
        </form>
        <div class="overflow-x-auto rounded border">
            <table class="w-full text-left">
                <thead><tr class="border-b bg-slate-50"><th class="p-3">Akun</th><th class="p-3">Role</th><th class="p-3">Aksi Role</th></tr></thead>
                <tbody>
                    <tr v-for="user in props.users.data" :key="user.id" class="border-b">
                        <td class="p-3"><div>{{ user.name }}</div><div class="text-sm text-slate-500">{{ user.email }}</div></td>
                        <td class="p-3">{{ user.role }}</td>
                        <td class="flex flex-wrap gap-2 p-3">
                            <button class="rounded bg-slate-700 px-3 py-1 text-white" @click="startEdit(user)">Edit</button>
                            <button class="rounded bg-red-600 px-3 py-1 text-white" @click="remove(user.id)">Hapus</button>
                            <button v-if="props.canManageRoles && user.role === 'user'" class="rounded bg-blue-600 px-3 py-1 text-white" @click="updateRole(user.id, 'admin')">Make admin</button>
                            <button v-if="props.canManageRoles && user.role === 'admin'" class="rounded bg-slate-600 px-3 py-1 text-white" @click="updateRole(user.id, 'user')">Remove admin</button>
                            <span v-if="!props.canManageRoles || user.role === 'super_admin'" class="text-sm text-slate-500">Read only</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
