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
    subscription_status: string
    payment_status: string
    trial_ends_at: string | null
    username: string
    phone: string | null
}

const props = defineProps<{ users: { data: User[] }; trialDays: number }>()
const filters = ref({
    global: { value: null as string | null, matchMode: FilterMatchMode.CONTAINS },
})
const formVisible = ref(false)

const activate = (id: number) => {
    if (confirmAction('Aktifkan akun user ini?')) {
        router.post(`/admin/users/${id}/activate`, { trial_days: props.trialDays })
    }
}
const suspend = (id: number) => {
    if (confirmAction('Suspend akun user ini?')) {
        router.post(`/admin/users/${id}/suspend`)
    }
}
const setTrial = (id: number) => {
    if (confirmAction(`Atur trial user menjadi ${props.trialDays} hari?`)) {
        router.post(`/admin/users/${id}/trial`, { trial_days: props.trialDays })
    }
}
const editingId = ref<number | null>(null)
const form = useForm({ name: '', username: '', email: '', phone: '', password: '', password_confirmation: '', role: 'user' })
const startEdit = (user: User) => {
    editingId.value = user.id
    form.defaults({ name: user.name, username: user.username, email: user.email, phone: user.phone ?? '', password: '', password_confirmation: '', role: user.role })
    form.reset()
    formVisible.value = true
}
const cancelEdit = () => { editingId.value = null; form.reset(); formVisible.value = false }
const startCreate = () => {
    editingId.value = null
    form.reset()
    formVisible.value = true
}
const submit = () => {
    if (editingId.value) {
        if (confirmAction('Simpan perubahan akun user ini?')) {
            form.put(`/admin/users/${editingId.value}`, { onSuccess: cancelEdit })
        }
        return
    }
    if (confirmAction('Tambah akun user baru?')) {
        form.post('/admin/users', { onSuccess: () => { form.reset('name', 'username', 'email', 'phone', 'password', 'password_confirmation'); formVisible.value = false } })
    }
}
const remove = (id: number) => {
    if (confirmAction('Hapus akun user ini? Tindakan ini tidak dapat dibatalkan.')) {
        router.delete(`/admin/users/${id}`)
    }
}
const validationError = () => Object.values(form.errors)[0] || ''
const statusSeverity = (status: string) => {
    if (status === 'active' || status === 'trialing') {
        return 'success'
    }

    if (status === 'pending' || status === 'past_due') {
        return 'warn'
    }

    return 'danger'
}
const formatDate = (value: string | null) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value)) : '—'
</script>

<template>
    <div class="p-6">
        <Head title="Kelola Akses User" />
        <h1 class="mb-6 text-2xl font-semibold">Kelola Akses User Aplikasi</h1>
        <p class="mb-6 text-sm text-slate-500">Aktifkan, atur trial, atau suspend akun pengguna aplikasi.</p>
        <Message v-if="validationError()" class="mb-4" severity="error">{{ validationError() }}</Message>
        <Card class="[&_.p-card-body]:p-4">
            <template #content>
            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <Button label="Tambah User" icon="pi pi-plus" @click="startCreate" />
                <span class="relative w-full sm:w-80">
                    <i class="pi pi-search absolute left-3 top-1/2 z-10 -translate-y-1/2 text-color-secondary" aria-hidden="true"></i>
                    <InputText v-model="filters.global.value" placeholder="Cari user..." aria-label="Cari user" class="w-full pl-10" />
                </span>
            </div>
            <DataTable
                :value="props.users.data"
                v-model:filters="filters"
                :global-filter-fields="['name', 'email', 'username', 'phone', 'status', 'subscription_status', 'payment_status']"
                paginator
                :rows="10"
                :rows-per-page-options="[10, 25, 50]"
                paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                current-page-report-template="{first}–{last} dari {totalRecords}"
                responsive-layout="scroll"
                striped-rows
                row-hover
                show-gridlines
                removable-sort
                size="small"
                class="w-full text-sm"
            >
                <template #empty>Belum ada user aplikasi.</template>
                <Column field="name" header="Akun" sortable>
                    <template #body="{ data }">
                        <div class="font-medium">{{ data.name }}</div>
                        <div class="text-xs text-color-secondary">{{ data.email }}</div>
                        <div class="text-xs text-color-secondary">@{{ data.username }}</div>
                    </template>
                </Column>
                <Column field="status" header="Status Akses" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.status" :severity="statusSeverity(data.status)" />
                    </template>
                </Column>
                <Column field="subscription_status" header="Subscription" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.subscription_status" :severity="statusSeverity(data.subscription_status)" />
                    </template>
                </Column>
                <Column field="payment_status" header="Pembayaran" sortable />
                <Column field="trial_ends_at" header="Trial Berakhir" sortable>
                    <template #body="{ data }">{{ formatDate(data.trial_ends_at) }}</template>
                </Column>
                <Column header="Aksi" :exportable="false">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <Button label="Edit" severity="secondary" size="small" @click="startEdit(data)" />
                            <Button label="Hapus" severity="danger" size="small" @click="remove(data.id)" />
                            <Button v-if="data.status === 'pending'" label="Aktifkan" severity="success" size="small" @click="activate(data.id)" />
                            <Button v-if="data.status === 'active'" label="Atur Trial" severity="warn" size="small" @click="setTrial(data.id)" />
                            <Button v-if="data.status === 'active'" label="Suspend" severity="danger" size="small" @click="suspend(data.id)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
            </template>
        </Card>
        <Dialog v-model:visible="formVisible" :header="editingId ? 'Edit User' : 'Tambah User'" modal :style="{ width: 'min(42rem, 95vw)' }" @hide="cancelEdit">
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
                    <Button :label="editingId ? 'Simpan' : 'Tambah User'" type="submit" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </div>
</template>
