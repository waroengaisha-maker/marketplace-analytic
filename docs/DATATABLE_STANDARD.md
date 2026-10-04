# DataTable Standard

## Tujuan

Semua halaman yang menggunakan DataTable harus mengikuti pola yang sudah terbukti pada **Orders** sebagai golden reference:

`resources/js/Pages/Orders/Index.vue`

Gunakan komponen bersama:
- `AppDataTable`
- `AppDataTableToolbar`
- `useDataTableContract`
- `TableColumnMeta`

## Golden reference

Orders adalah referensi utama untuk implementasi DataTable baru.

Pola yang harus dipertahankan:
1. Definisi kolom terpusat menggunakan `TableColumnMeta`.
2. `selectedColumns` untuk column visibility.
3. `useDataTableContract()` untuk state DataTable.
4. Lazy/server-side pagination.
5. Server-side sorting.
6. Loading state dari contract.
7. Empty state menggunakan slot `#empty`.
8. Action column terpisah.
9. Frozen column/action bila memang diperlukan.
10. Detail/nested DataTable menggunakan komponen yang sama.
11. Container DataTable tidak mempunyai border atau shadow sebagai frame luar.

## Definisi kolom

Gunakan satu sumber metadata:

```ts
const allColumns = [
    ['name', 'Akun'],
    ['status', 'Status'],
] as const satisfies readonly TableColumnMeta[]

const selectedColumns = ref<TableColumnMeta[]>([...allColumns])
```

## DataTable contract

Gunakan `useDataTableContract()` dan ambil hanya state yang diperlukan halaman.

## Toolbar

Gunakan `AppDataTableToolbar` untuk global search, column selector, dan action buttons.

Filter domain kompleks seperti tanggal, status, atau range sebaiknya berada pada area filter tersendiri seperti pada Orders.

## Pagination dan sorting

Untuk data server-side gunakan `lazy` dan satu fungsi `loadData()` sebagai source of truth.

```vue
<AppDataTable
    :loading="isLoading"
    :value="rows"
    v-model:multi-sort-meta="multiSortMeta"
    sort-mode="multiple"
    lazy
    :total-records="pagination.total"
    :first="(pagination.current_page - 1) * pagination.per_page"
    @page="onPage"
    @sort="onSort"
    paginator
>
```

Pagination menghitung page dari `first / rows`, sedangkan sorting mengirim field dan direction ke backend.

## Multi-sort

Jika menggunakan `multiSortMeta`, DataTable **wajib** menggunakan:

```vue
sort-mode="multiple"
```

Jangan hanya bind `v-model:multi-sort-meta` tanpa mode multiple.

## Visual / container

**Tidak boleh ada border atau shadow sebagai frame di pinggir DataTable.**

Gunakan pola Orders:

```vue
<div
    class="flex min-h-0 flex-col overflow-hidden rounded-lg bg-surface-0 dark:bg-surface-950"
    style="height: min(70vh, 48rem)"
>
    <AppDataTable ... />
</div>
```

Hindari `border`, `border-surface-200`, `border-surface-700`, `shadow-sm`, atau `shadow-md` pada wrapper luar DataTable.

Border internal dari theme PrimeVue tetap diperbolehkan bila merupakan bagian dari tabel, tetapi jangan membuat frame tambahan di sekeliling keseluruhan DataTable.

## Empty state

Setiap DataTable harus memiliki empty state yang jelas:

```vue
<template #empty>Belum ada data.</template>
```

## Action column

Action column terpisah dari column metadata dan tidak ikut column selector. Untuk action yang ringkas, gunakan tombol compact/icon dan pertimbangkan `frozen` di kanan. Tombol icon-only harus memiliki `aria-label`.

## Selection

Row selection hanya digunakan bila dibutuhkan oleh domain; jangan menambahkannya hanya demi mengikuti contoh.

## Formatting

Formatting dilakukan pada layer presentation:
- tanggal → formatter tanggal
- nominal → formatter nominal
- quantity → formatter quantity
- status → `Tag` + severity helper
- nilai kosong → `—`

## Detail / nested DataTable

Tabel detail di Dialog atau secondary view tetap menggunakan `AppDataTable`, `AppDataTableToolbar`, `TableColumnMeta`, empty state, dan column visibility bila relevan.

## Checklist

- [ ] Menggunakan `AppDataTable`
- [ ] Menggunakan `useDataTableContract`
- [ ] Column metadata memakai `TableColumnMeta`
- [ ] Pagination server-side memakai `lazy`
- [ ] Sorting server-side terhubung ke backend
- [ ] `sort-mode="multiple"` jika memakai `multiSortMeta`
- [ ] Loading state menggunakan `isLoading`
- [ ] Memiliki empty state
- [ ] Action column terpisah dari column selector
- [ ] Wrapper DataTable **tanpa border dan tanpa shadow**
- [ ] Filter domain kompleks dipisahkan dari toolbar generik bila diperlukan
- [ ] Selection hanya bila dibutuhkan

## Anti-pattern

Jangan membuat wrapper seperti:

```vue
<div class="border border-surface-200 shadow-sm ...">
    <AppDataTable ... />
</div>
```

Jangan membuat DataTable baru dengan styling/behavior sendiri jika kebutuhan dapat dipenuhi oleh komponen bersama.

**Golden rule:** jika ragu tentang struktur atau visual DataTable, lihat kembali `resources/js/Pages/Orders/Index.vue`.
