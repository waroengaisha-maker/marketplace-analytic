# DataTable Contract

Setiap halaman list/tabel di aplikasi ini **wajib** memakai komponen `AppDataTable` + `AppDataTableToolbar` dari `resources/js/Components/DataTable/`, sehingga styling dan perilaku tabel konsisten dengan halaman referensi **Finance/Reconciliation**.

## Fitur wajib (diberikan otomatis oleh `AppDataTable`)

- **Tanpa border line** — `show-gridlines` TIDAK boleh dipakai di komponen maupun halaman.
- `striped-rows`, `row-hover`
- `resizable-columns` (expand) dan `reorderable-columns`
- `removable-sort` (multi-sort)
- `size="large"`, `scrollable + scroll-height="flex"`
- wrapper scroll `height: min(70vh, 48rem)` + kartu border rounded
- `table-style` default `min-width: 108rem` (override via prop `table-style-min-width`)
- **Overlay loading** — prop `:loading`; komponen otomatis menampilkan spinner overlay saat `loading` true. Gunakan `isLoading` dari `useDataTableContract()` yang mengikuti event Inertia `router.on('start'/'finish')`.
- `exportCSV()` di-expose dari komponen (untuk tombol export).

## Fitur wajib server-side (lazy)

Konsep utama: **selalu server-side** (DataTable `lazy`). Pola:

1. Controller menerima param query `page`, `per_page`, `search`, `sort_field`, `sort_order` (dan filter domain), lalu mengembalikan `pagination { current_page, per_page, last_page, total }` + data halaman.
2. Halaman memakai `useDataTableContract()` (memberi `globalFilter`, `multiSortMeta`, `isLoading`, `isFullscreen`, `selectedRows`) dan `loadData()` via `router.get(url, params, { preserveScroll: true })`.
3. `@page`, `@sort`, `@filter` serta `@filter` pada `AppDataTableToolbar` memanggil `loadData`.
4. DataTable memakai: `lazy`, `:total-records`, `:first="(currentPage-1)*perPage"`, `:rows="perPage"`, `v-model:multi-sort-meta`, `:loading="isLoading"`.

## Fitur yang wajib ditambahkan di halaman

1. **Pencarian global** — `AppDataTableToolbar` dengan `v-model:global-filter` + `@filter`.
2. **Column picker** — `allColumns` (`[field, header][]`) + `selectedColumns` ref; render kolom lewat `v-for="[field, header] in selectedColumns"`.
3. **Pagination** — `paginator`, `:rows`, `:rows-per-page-options`, `paginator-template` dan `current-page-report-template` seperti referensi.
4. **Fullscreen & Export Excel** yang konsisten dengan halaman referensi (tombol di slot `actions` toolbar).
5. **Empty state** — slot `#empty` dengan pesan jelas.
6. Kolom aksi memakai `:exportable="false"` bila tidak ikut export.

## Contoh pola

```ts
const allColumns = [
    ['name', 'Akun'],
    ['role', 'Role'],
] as const satisfies readonly TableColumnMeta[]

const { globalFilter, multiSortMeta, isLoading } = useDataTableContract()
const selectedColumns = ref<TableColumnMeta[]>([...allColumns])

function loadData(params: Record<string, unknown> = {}) {
    router.get('/resource', {
        page: params.page ?? undefined,
        per_page: params.per_page ?? undefined,
        search: globalFilter.value || undefined,
        sort_field: params.sort_field ?? multiSortMeta.value[0]?.field ?? 'name',
        sort_order: params.sort_order ?? (multiSortMeta.value[0]?.order === -1 ? 'desc' : 'asc'),
    }, { preserveScroll: true })
}
```

```vue
<AppDataTableToolbar v-model:global-filter="globalFilter" v-model:selected-columns="selectedColumns" :all-columns="allColumns" @filter="onFilter">
    <template #actions>
        <Button label="Export Excel" icon="pi pi-download" severity="secondary" outlined @click="exportExcel" />
    </template>
</AppDataTableToolbar>

<AppDataTable :loading="isLoading" :value="rows" lazy :total-records="pagination.total"
    :first="(pagination.current_page - 1) * pagination.per_page" data-key="id"
    @page="onPage" @sort="onSort" @filter="onFilter"
    paginator :rows="pagination.per_page" :rows-per-page-options="[25, 50, 100]"
    paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown CurrentPageReport"
    current-page-report-template="{first}–{last} dari {totalRecords}">
    <template #empty>Belum ada data.</template>
    <Column v-for="[field, header] in selectedColumns" :key="field" :field="field" :header="header" sortable />
    <Column header="Aksi" :exportable="false">...</Column>
</AppDataTable>
```

## Best practice & clean code (wajib)

- Laravel: ikuti `laravel-best-practices` — N+1 dihindari (gunakan `with()` / query teragregasi), validasi di controller/Form Request, logika domain di service class, idempotensi untuk operasi yang bisa dijalankan ulang.
- Frontend: komponen list hanya "tipis" — state tabel dari `useDataTableContract()`, logika di layanan/controller; Form vs tabel dipisah.
- Tes wajib untuk service baru (`tests/Feature/...Test.php`) dan menjaga suite tetap hijau (hanya kegagalan pre-existing yang dibiarkan).
- Setiap datatable baru WAJIB mengikuti dokumentasi ini — review disiplin kontrak sebelum merge.

## Sudah diterapkan

- `Finance/Reconciliation.vue` (referensi, server-side + overlay loading, tanpa gridlines)
- `Admin/Users/Index.vue`, `Admin/Users/Access.vue` (server-side lazy)
- `Products/Hpp.vue`, `Products/HppMapping.vue` (server-side lazy + fullscreen + export Excel)

Catatan: halaman dengan filter domain sendiri boleh menambah kontrol filter di luar toolbar, asalkan pencarian global & pagination tetap lewat komponen kontrak.