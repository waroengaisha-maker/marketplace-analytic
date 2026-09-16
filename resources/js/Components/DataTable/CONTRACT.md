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
- **Multiple selection** didukung penuh — `AppDataTable` mewariskan semua attr ke `DataTable` (termasuk `v-model:selection`, `selection-mode`, `data-key`), dan `useDataTableContract()` sudah menyediakan `selectedRows`. Lihat bagian "Multiple selection" di bawah.

## Fitur wajib server-side (lazy)

Konsep utama: **selalu server-side** (DataTable `lazy`). Pola:

1. Controller menerima param query `page`, `per_page`, `search`, `sort_field`, `sort_order` (dan filter domain), lalu mengembalikan `pagination { current_page, per_page, last_page, total }` + data halaman.
2. Halaman memakai `useDataTableContract()` (memberi `globalFilter`, `multiSortMeta`, `isLoading`, `selectedRows`) dan `loadData()` via `router.get(url, params, { preserveScroll: true })`.
3. `@page`, `@sort`, `@filter` serta `@filter` pada `AppDataTableToolbar` memanggil `loadData`.
4. DataTable memakai: `lazy`, `:total-records`, `:first="(currentPage-1)*perPage"`, `:rows="perPage"`, `v-model:multi-sort-meta`, `:loading="isLoading"`.

## Fitur yang wajib ditambahkan di halaman

1. **Pencarian global** — `AppDataTableToolbar` dengan `v-model:global-filter` + `@filter`.
2. **Column picker** — `allColumns` (`[field, header][]`) + `selectedColumns` ref; render kolom lewat `v-for="[field, header] in selectedColumns"`.
3. **Pagination** — `paginator`, `:rows`, `:rows-per-page-options`, `paginator-template` dan `current-page-report-template` seperti referensi.
4. **Export Excel** — konten Wajib persis model halaman referensi Reconciliation (lihat bagian "Export Excel").
5. **Empty state** — slot `#empty` dengan pesan jelas.
6. Kolom aksi memakai `:exportable="false"` bila tidak ikut export.

> **Varian "filter card + toolbar minimal" (pola Orders):** halaman boleh memindahkan seluruh filter (pencarian, filter domain, column picker) ke satu Card bersama filter periode, dan memuat data **hanya setelah** tombol "Terapkan" diklik (bukan otomatis per perubahan input). Dalam pola ini toolbar DataTable cukup berisi tombol Export Excel dan Wajib pasang `:hide-search="true"` pada `AppDataTableToolbar` (komponen secara default selalu merender input pencarian). Aturan tetap: pakai `useDataTableContract()` untuk `globalFilter`/`multiSortMeta`, dan pisahkan state "draft" (input) dari state "applied" (yang benar-benar dikirim ke loadData).

> **Search di modal detail (client-side, pola Orders):** jika tabel di dalam `Dialog` memuat seluruh baris sekaligus (tidak lazy), JANGAN andalkan prop `global-filter` DataTable PrimeVue (tidak konsisten). Filter manual pakai `computed` di halaman: `detailGlobalFilter` (ref) → `detailFilteredRows` (`row => Object.values(row).join(' ').toLowerCase().includes(query)`, plus label untuk kolom ber-kode seperti `hpp_status`) → `:value="detailFilteredRows"` di `AppDataTable` modal. `v-model:global-filter` pada `AppDataTableToolbar` tetap dipakai untuk state input.

## Export Excel (wajib)

Export Wajib mengikuti persis model halaman referensi Finance/Reconciliation:

- Nilai baris adalah **nilai mentah/raw** (`row[field] ?? ''`), bukan string terformat (`formatNominal` hanya untuk tampilan di tabel).
- Sheet pertama memakai header dari kolom yang dipilih user (`selectedColumns`).
- Kolom status HPP diexport memakai label (via `hppStatusLabel`).
- Jika ada data rincian per item, tambahkan **sheet "Detail Per Item"** berisi baris per item dari semua baris terfilter; data berasal dari endpoint server yang memakai filter yang sama (date + search + statuses).
- Jika data item bisa di-group ulang, tambahkan **satu sheet recap tambahan** (mis. "Detail Per Item Rekap") berisi baris yang sama tetapi diurutkan berdasarkan field domain (mis. nama produk → variasi → harga), diurutkan client-side sebelum ditulis ke sheet.
- **Sheet recap untuk input ke sistem accounting WAJIB menyaring data yang eligible** — hanya baris `business_status === 'Settled'` dan `net_quantity > 0` (status lain = Cancelled/Refunded/Returned/Partial/Unmatched/Invalid TIDAK boleh masuk). `business_status` wajib diekspos oleh endpoint export-lines. Grup dengan `SUM(net_quantity)` per (produk, variasi, harga).
- Nama file dipakai `buildAnalyticsExportFilename(fromLabel, toLabel)`.

Contoh pola:

```ts
const exportColumns = [
    ['order_number', 'No. Pesanan'],
    // ... kolom per item
    ['hpp_status', 'Status HPP'],
    ['laba', 'Laba Bersih'],
] as const satisfies readonly TableColumnMeta[]

async function exportExcel() {
    const XLSX = await import('xlsx')
    const data = props.orders.map((row) => Object.fromEntries(
        selectedColumns.value.map(([field, header]) => [header, row[field as keyof OrderRow] ?? '']),
    ))
    const worksheet = XLSX.utils.json_to_sheet(data)
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Orders')
    // ... fetch endpoint export-lines dengan filter yang sama, lalu:
    const detailWorksheet = XLSX.utils.json_to_sheet(detailData)
    XLSX.utils.book_append_sheet(workbook, detailWorksheet, 'Detail Per Item')
    XLSX.writeFile(workbook, buildAnalyticsExportFilename(fromLabel, toLabel))
}
```

## Kartu ringkasan (summary cards) — wajib

Semua kartu ringkasan/statistik di halaman list **wajib** memakai komponen PrimeVue `Card` dengan styling mengikuti halaman referensi **Finance/Reconciliation** — JANGAN membuat kartu custom (`div` dengan `rounded-lg bg-surface-0 shadow-sm`) dengan tone warna manual.

Pola yang wajib dipakai (ikon/breakdown boleh ditambah, struktur label + nilai tetap):

```vue
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <Card class="[&_.p-card-body]:!p-3 [&_.p-card-content]:!p-0">
        <template #content>
            <p class="text-xs font-semibold text-color-secondary">{{ label }}</p>
            <p class="mt-1 text-lg font-bold leading-tight">{{ formatNominal(value) }}</p>
        </template>
    </Card>
</div>
```

Aturan:
- Pakai `text-color-secondary` untuk label, bukan `text-slate-*` manual.
- Pakai padding override kontrak `[&_.p-card-body]:!p-3 [&_.p-card-content]:!p-0`.
- Grid `grid gap-3 sm:grid-cols-2 xl:grid-cols-N` (N menyesuaikan jumlah kartu).
- Sumber nilai dari prop terkendali server (bukan dihitung raw di client).

## Detail per baris — wajib modal (Dialog)

Detail dari baris tabel (mis. rincian per item sebuah order) **wajib** ditampilkan memakai `Dialog` (modal) — JANGAN memakai accordion inline / `#expansion` slot.

Alasan: mekanisme ekspansi baris bawaan DataTable PrimeVue runtime dan interaksi dengan overlay loading (Inertia partial reload) tidak stabil. Pola modal yang sudah terbukti:

```vue
import Dialog from 'primevue/dialog'

<Dialog v-model:visible="detailVisible" modal :header="`Detail ${activeOrderNumber ?? ''}`"
    :style="{ width: 'min(76rem, 96vw)' }" :maximizable="true" :dismissable-mask="true" @hide="closeDetail">
    <!-- kolom aksi memakai tombol :icon="'pi pi-eye'" @click="openDetail(data.order_number)" -->
</Dialog>
```

Aturan:
- Tombol buka detail di kolom aksi memakai ikon `pi pi-eye`.
- **Setiap modal WAJIB ditutup saat klik area luar mask** — selalu pasang `:dismissable-mask="true"` pada `Dialog`.
- State modal dari halaman (ref lokal), sedangkan data detail difetch via `router.get(url, params, { only: ['details'], preserveState: true })` dengan indikator loading.
- Akhiri dengan tombol/`@hide` yang me-reset state & menutup modal.

## Multiple selection (opsional namun distandarkan)

Jika halaman butuh select banyak baris (mis. aksi massal), ikuti pola berikut — jangan buat state selection sendiri:

1. Ambil `selectedRows` dari `useDataTableContract()` (sudah disediakan).
2. Pakai `data-key` yang unik pada `AppDataTable` (id baris).
3. Ikat `v-model:selection="selectedRows"` dan `selection-mode="multiple"` pada `AppDataTable`.
4. Jangan reset selection di dalam loadData; reset manual via `selectedRows.value = []` setelah aksi massal berhasil.
5. Kolom checkbox otomatis ditambahkan PrimeVue; jangan menambah kolom checkbox manual. Multi-sort & selection bisa dipakai bersamaan.

```vue
<AppDataTable :loading="isLoading" :value="rows" lazy data-key="id"
    v-model:selection="selectedRows" selection-mode="multiple" ...>
```

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
- `Orders/Index.vue` (filter card + toolbar minimal, export 2 sheet, modal detail)
- `Products/Hpp.vue`, `Products/HppMapping.vue` (server-side lazy + export Excel)

Catatan: halaman dengan filter domain sendiri boleh menambah kontrol filter di luar toolbar, asalkan pencarian global & pagination tetap lewat komponen kontrak.