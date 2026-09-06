<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import Message from 'primevue/message'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Dialog from 'primevue/dialog'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { FilterMatchMode } from '@primevue/core/api'
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
const filters = ref({ global: { value: null as string | null, matchMode: FilterMatchMode.CONTAINS } })
const formVisible = ref(false)

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
    formVisible.value = true
}
const cancelEdit = () => { editingId.value = null; form.reset(); formVisible.value = false }
const startCreate = () => { editingId.value = null; form.reset(); formVisible.value = true }
const submit = () => {
    if (editingId.value) {
        if (confirmAction('Simpan perubahan akun admin ini?')) {
            form.put(`/admin/users/${editingId.value}`, { onSuccess: cancelEdit })
        }
        return
    }
    if (confirmAction('Tambah akun admin baru?')) {
        form.post('/admin/users', { onSuccess: () => { form.reset('name', 'username', 'email', 'phone', 'password', 'password_confirmation'); formVisible.value = false } })
    }
}
const remove = (id: number) => {
    if (confirmAction('Hapus akun ini? Tindakan ini tidak dapat dibatalkan.')) {
        router.delete(`/admin/users/${id}`)
    }
}
const validationError = () => Object.values(form.errors)[0] || ''
const roleSeverity = (role: string) => role === 'super_admin' ? 'danger' : role === 'admin' ? 'info' : 'secondary'
</script>

<template>
    <div class="p-6">
        <Head title="Kelola Admin" />
        <h1 class="mb-6 text-2xl font-semibold">Kelola Admin</h1>
        <p class="mb-6 text-sm text-slate-500">Kelola akun admin aplikasi dan ubah aksesnya menjadi user aplikasi.</p>
        <Message v-if="validationError()" class="mb-4" severity="error">{{ validationError() }}</Message>
        <Card class="[&_.p-card-body]:p-4">
            <template #content>
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <Button label="Tambah Admin" icon="pi pi-plus" @click="startCreate" />
                <span class="relative w-full sm:w-80">
                    <i class="pi pi-search absolute left-3 top-1/2 z-10 -translate-y-1/2 text-color-secondary" aria-hidden="true"></i>
                    <InputText v-model="filters.global.value" placeholder="Cari admin..." aria-label="Cari admin" class="w-full pl-10" />
                </span>
            </div>
            <DataTable :value="props.users.data" v-model:filters="filters" :global-filter-fields="['name', 'email', 'username', 'phone', 'role']" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport" current-page-report-template="{first}–{last} dari {totalRecords}" responsive-layout="scroll" striped-rows row-hover show-gridlines removable-sort size="small" class="w-full text-sm">
                <template #empty>Belum ada data admin.</template>
                <Column field="name" header="Akun" sortable>
                    <template #body="{ data }"><div class="font-medium">{{ data.name }}</div><div class="text-xs text-color-secondary">{{ data.email }}</div><div class="text-xs text-color-secondary">@{{ data.username }}</div></template>
                </Column>
                <Column field="role" header="Role" sortable><template #body="{ data }"><Tag :value="data.role" :severity="roleSeverity(data.role)" /></template></Column>
                <Column header="Aksi" :exportable="false">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <Button label="Edit" severity="secondary" size="small" @click="startEdit(data)" />
                            <Button label="Hapus" severity="danger" size="small" @click="remove(data.id)" />
                            <Button v-if="props.canManageRoles && data.role === 'user'" label="Jadikan admin" size="small" @click="updateRole(data.id, 'admin')" />
                            <Button v-if="props.canManageRoles && data.role === 'admin'" label="Hapus akses" severity="secondary" size="small" @click="updateRole(data.id, 'user')" />
                        </div>
                    </template>
                </Column>
            </DataTable>
            </template>
        </Card>
        <Dialog v-model:visible="formVisible" :header="editingId ? 'Edit Admin' : 'Tambah Admin'" modal :style="{ width: 'min(42rem, 95vw)' }" @hide="cancelEdit">
            <form class="grid gap-4 pt-2" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <InputText v-model="form.name" placeholder="Nama" required />
                    <InputText v-model="form.username" placeholder="Username" required />
                    <InputText v-model="form.email" type="email" placeholder="Email" required />
                    <InputText v-model="form.phone" placeholder="Nomor handphone" />
                    <InputText v-model="form.password" type="password" :placeholder="editingId ? 'Password baru (opsional)' : 'Password'" :required="!editingId" />
                    <InputText v-model="form.password_confirmation" type="password" placeholder="Konfirmasi password" :required="!!form.password" />
                </div>
                <Message v-if="validationError()" severity="error">{{ validationError() }}</Message>
                <div class="flex justify-end gap-2">
                    <Button label="Batal" severity="secondary" type="button" @click="cancelEdit" />
                    <Button :label="editingId ? 'Simpan' : 'Tambah Admin'" type="submit" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </div>
</template>
