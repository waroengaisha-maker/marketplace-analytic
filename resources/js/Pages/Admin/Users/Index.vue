<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Message from 'primevue/message'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
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
    trial_ends_at: string | null
    is_admin: boolean
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

const props = defineProps<{ users: Pagination; trialDays: number; canManageRoles: boolean }>()

const allColumns = [
    ['name', 'Akun'],
    ['role', 'Role'],
] as const satisfies readonly TableColumnMeta[]

const { globalFilter, multiSortMeta, isLoading } = useDataTableContract()
const selectedColumns = ref<TableColumnMeta[]>([...allColumns])

function loadData(params: Record<string, unknown> = {}) {
    router.get('/admin/admins', {
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
            <AppDataTableToolbar
                v-model:global-filter="globalFilter"
                v-model:selected-columns="selectedColumns"
                :all-columns="allColumns"
                search-placeholder="Cari admin..."
                columns-label="Pilih kolom"
                @filter="onFilter"
            >
                <template #actions>
                    <Button label="Tambah Admin" icon="pi pi-plus" @click="startCreate" />
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
                <template #empty>Belum ada data admin.</template>
                <Column v-for="[field, header] in selectedColumns" :key="field" :field="field" :header="header" sortable>
                    <template #body="{ data }">
                        <template v-if="field === 'name'"><div class="font-medium">{{ data.name }}</div><div class="text-xs text-color-secondary">{{ data.email }}</div><div class="text-xs text-color-secondary">@{{ data.username }}</div></template>
                        <Tag v-else :value="data.role" :severity="roleSeverity(data.role)" />
                    </template>
                </Column>
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
            </AppDataTable>
            </div>
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
