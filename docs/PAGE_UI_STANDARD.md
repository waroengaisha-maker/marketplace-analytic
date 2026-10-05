# UI Style Guide

## Purpose

Dokumen ini menetapkan **standar visual dan struktur dasar page** untuk seluruh page Marketplace Analytics.

Page **UI Style Guide** menjadi reference implementation untuk standar ini. Yang dijadikan standar adalah **page shell/header pattern**, bukan fitur bisnis tertentu.

## Reference Pattern

Struktur dasar page:

1. **Section label**
   - Posisi paling atas.
   - Menggunakan label singkat dalam uppercase.
   - Contoh: `Design System`.
   - Visual: kecil, semibold, muted, dengan letter spacing.

2. **Page title**
   - Tepat di bawah section label.
   - Menggunakan judul utama page.
   - Ukuran visual sekitar `text-3xl`.
   - Bold.
   - Warna mengikuti surface/theme aplikasi.

3. **Page description**
   - Tepat di bawah title.
   - Satu kalimat singkat yang menjelaskan tujuan page.
   - Ukuran kecil dan muted.
   - Tidak digunakan untuk instruksi panjang atau detail implementasi.

Contoh canonical:

```text
Design System

UI Style Guide

Referensi struktur dan pola visual dasar untuk seluruh page di Marketplace Analytics.
```

## Layout

Gunakan wrapper page yang:
- memenuhi lebar area konten yang tersedia;
- memiliki `min-w-0` agar aman pada layout responsive;
- menggunakan vertical flow;
- tidak menambahkan spacing atau komponen dekoratif yang tidak diperlukan pada header.

Reference structure:

```vue
<div class="flex w-full min-w-0 flex-col">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
            Section
        </p>

        <h1 class="mt-1 text-3xl font-bold text-slate-900">
            Page Title
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Short description explaining the purpose of this page.
        </p>
    </div>
</div>
```

## Rules for New Pages

Setiap page baru sebaiknya mengikuti pola berikut:
- **Section → Title → Description**.
- Section harus menggambarkan area/domain page, bukan nama teknis.
- Title harus singkat dan mudah dipahami user.
- Description harus menjelaskan fungsi page dalam satu kalimat.
- Hindari breadcrumb jika tidak dibutuhkan.
- Hindari card/container tambahan hanya untuk membungkus header.
- Hindari heading yang terlalu besar.
- Jangan memasukkan filter, tabel, action, statistic card, atau business logic ke dalam header pattern.
- Komponen bisnis diletakkan **setelah header** dan hanya jika memang diperlukan page tersebut.
- Gunakan PrimeVue dan Tailwind mengikuti design language existing application.
- Pertahankan responsive behavior dan dark-mode compatibility.

## Relationship with Global Navigation

Standar page ini **tidak menggantikan global sidebar/topbar navigation**.

Global navigation tetap menjadi tanggung jawab `AppLayout.vue`.

Page hanya bertanggung jawab terhadap:
- section/domain context;
- page title;
- page description;
- content khusus page.

## Reference Implementation

Reference implementation saat ini:

`resources/js/Pages/Admin/UIStyleGuide.vue`

Header tersebut menjadi baseline ketika membuat page baru.

Jika sebuah page membutuhkan variasi visual, variasi harus mempunyai alasan UX yang jelas dan tidak mengubah pola dasar tanpa kebutuhan.

## Design Principle

> **Simple by default.**

Page header harus memberikan konteks yang cukup dalam beberapa detik tanpa mengambil perhatian dari content utama.