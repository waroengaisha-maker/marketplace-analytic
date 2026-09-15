<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Message from 'primevue/message'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Dialog from 'primevue/dialog'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { AppDataTable, AppDataTableToolbar, useDataTableContract, type TableColumnMeta } from '@/Components/DataTable'
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

type Pagination = {
    data: User[]
    current_page: number
    last_page: number
    per_page: number
    total: number
}

const props = defineProps<{ users: Pagination; trialDays: number }>()

const allColumns = [
    ['name', 'Akun'],
    ['status', 'Status Akses'],
    ['subscription_status', 'Subscription'],
    ['payment_status', 'Pembayaran'],
    ['trial_ends_at', 'Trial Berakhir'],
] as const satisfies readonly TableColumnMeta[]

const { globalFilter, multiSortMeta, isLoading } = useDataTableContract()
const selectedColumns = ref<TableColumnMeta[]>([...allColumns])

function loadData(params: Record<string, unknown> = {}) {
    router.get('/admin/users/access', {
        page: params.page ?? undefined,
        per_page: params.per_page ?? undefined,
        search: globalFilter.value || undefined,
        sort_field: params.sort_field ?? multiSortMeta.value[0]?.field ?? 'name',
        sort_order: params.sort_order ?? (multiSortMeta.value[0]?.order === -1 ? 'desc' : 'asc'),
    }, { preserveScroll: true })
}

function onPage(event: { first: number; rows: number }) {
    loadData({ page: Math.floor(event.first / event.rows) + 1, per_page: event.rows })
}

function onSort() {
    loadData({ page: 1 })
}

function onFilter() {
    loadData({ page: 1 })
}

const totalRecords = computed(() => props.users.total)
const currentPage = computed(() => props.users.current_page)
const perPage = computed(() => props.users.per_page)

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
            <AppDataTableToolbar
                v-model:global-filter="globalFilter"
                v-model:selected-columns="selectedColumns"
                :all-columns="allColumns"
                search-placeholder="Cari user..."
                columns-label="Pilih kolom"
                @filter="onFilter"
            >
                <template #actions>
                    <Button label="Tambah User" icon="pi pi-plus" @click="startCreate" />
                </template>
            </AppDataTableToolbar>
            <div class="overflow-hidden rounded-xl border border-surface-200 shadow-sm dark:border-surface-700" style="height: min(70vh, 48rem)">
            <AppDataTable
                :loading="isLoading"
                :value="props.users.data"
                v-model:multi-sort-meta="multiSortMeta"
                lazy
                :total-records="totalRecords"
                :first="(currentPage - 1) * perPage"
                data-key="id"
                @page="onPage"
                @sort="onSort"
                @filter="onFilter"
                paginator
                :rows="perPage"
                :rows-per-page-options="[10, 25, 50]"
                paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
                current-page-report-template="{first}–{last} dari {totalRecords}"
                table-style-min-width="72rem"
            >
                <template #empty>Belum ada user aplikasi.</template>
                <Column v-for="[field, header] in selectedColumns" :key="field" :field="field" :header="header" sortable>
                    <template #body="{ data }">
                        <template v-if="field === 'name'"><div class="font-medium">{{ data.name }}</div><div class="text-xs text-color-secondary">{{ data.email }}</div><div class="text-xs text-color-secondary">@{{ data.username }}</div></template>
                        <Tag v-else-if="field === 'status'" :value="data.status" :severity="statusSeverity(data.status)" />
                        <Tag v-else-if="field === 'subscription_status'" :value="data.subscription_status" :severity="statusSeverity(data.subscription_status)" />
                        <template v-else-if="field === 'payment_status'">{{ data.payment_status }}</template>
                        <template v-else>{{ formatDate(data.trial_ends_at) }}</template>
                    </template>
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
            </AppDataTable>
            </div>
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
