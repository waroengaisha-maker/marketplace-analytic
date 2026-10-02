# Marketplace Analytics — Project Context

## Project Overview

Marketplace Analytics adalah aplikasi internal untuk mengintegrasikan, menganalisis, dan merekonsiliasi data marketplace, dengan fokus utama pada Shopee.

Tujuan utama:
- Mengimpor data order marketplace.
- Mengimpor data income/financial marketplace.
- Menghubungkan order dengan income dan refund.
- Menjaga identitas order line dan financial event secara deterministik.
- Menghitung fulfillment quantity, return/refund, revenue, cost, dan reconciliation.
- Menyediakan UI untuk audit dan financial reconciliation.
- Menyediakan integrasi Shopee Open Platform/API.
- Menjaga data dapat diaudit dan tidak melakukan merge terhadap transaksi yang sebenarnya berbeda.

Prinsip utama: **functionality first**, dengan best practice engineering dan integritas data.

## Repository

- Repository: `git@github.com:waroengaisha-maker/marketplace-analytic.git`
- Local path: `~/projects/marketplace-analytics`
- Branch utama development: `development`
- Remote: `origin/development`

Workflow:
- Work only on `development` kecuali diminta lain.
- Jangan membuat perubahan yang tidak terkait.
- Gunakan commit yang fokus dan bermakna.
- Tambahkan regression test untuk perubahan behavior.
- Jalankan test yang relevan; full suite untuk perubahan domain penting.
- Jalankan `git diff --check`.

## Technology Stack

### Backend
- Laravel 13
- PHP >= 8.3
- Laravel Eloquent
- Laravel migrations
- Laravel queues bila diperlukan
- Redis untuk cache/session/queue bila dikonfigurasi

### Frontend
- Vue 3
- Inertia.js
- TypeScript
- PrimeVue
- Tailwind CSS v4
- Vite

### Database
Primary development database: MySQL 8.4.

### Infrastructure
- Docker
- Docker Compose (`compose.yaml`)
- Laravel Sail-compatible application container
- Caddy
- MySQL
- Redis
- Adminer

Typical endpoints:
- Application: `http://localhost:8080`
- Vite: `http://localhost:5173`

## Frontend Architecture

Arsitektur saat ini adalah **Laravel + Inertia.js + Vue 3**.

Gunakan PrimeVue sebagai UI component library default jika tersedia komponen yang sesuai.

Jangan memperkenalkan framework frontend/backend baru atau melakukan migrasi ke Nuxt/NestJS tanpa permintaan eksplisit dan evaluasi dampak terhadap aplikasi yang ada.

## Domain Model

Konsep penting:
- Marketplace Order
- Order Line
- Income
- Income Line
- Refund
- Refund Event
- Fulfillment Quantity
- Product
- Variation
- SKU
- Reconciliation
- Order Cost Allocation

Sistem harus membedakan:
1. Order
2. Order line
3. Income line
4. Refund event

Konsep-konsep tersebut saling berhubungan tetapi **bukan identity yang sama**.

## Order Identity

Order number marketplace adalah identity level-order utama bila source menyediakannya.

Jangan membuat synthetic order identity jika marketplace sudah menyediakan identifier yang reliable.

Order identity tidak boleh disamakan dengan line identity.

## Order-Line Identity

Jangan menganggap kombinasi `product name + variation + price + quantity` cukup untuk mengidentifikasi line jika source menyediakan identifier yang lebih kuat.

Aturan:
- Prioritaskan identifier source yang reliable.
- Jangan memfabricate identifier yang tidak diberikan marketplace.
- Fallback identity hanya bila diperlukan, deterministic, terdokumentasi, dan diuji.
- Variant yang berbeda tidak boleh tergabung secara tidak sengaja.

## Income-Line Identity

Income records merepresentasikan financial events dan tidak otomatis identik dengan order lines.

Bedakan:
- normal order income
- refund income
- financial adjustment/event lainnya

Jangan merge financial events berbeda hanya karena product, variation, price, dan quantity terlihat sama.

## Refund Event Identity

Refund application/event adalah financial event terpisah.

Jika source menyediakan nomor aplikasi seperti `No. Pengajuan`, gunakan identifier tersebut untuk membentuk deterministic internal refund event identity.

Implementasi saat ini:
`App\\Services\\RefundEventIdentity`

Identity internal berupa deterministic SHA-256 fingerprint dari source identity.

Penting: nomor aplikasi adalah source data; SHA-256 hanya internal identity dan bukan marketplace ID yang dibuat-buat.

Jika source tidak menyediakan application number, **jangan membuat refund event ID palsu**.

## NULL Refund Event Identity

`refund_event_identity = NULL` valid ketika source tidak menyediakan application number.

Multiple rows dengan NULL event identity dapat merepresentasikan source rows/events yang berbeda.

Exact duplicate source rows tetap dapat dideduplikasi.

Jangan mengatasi ketiadaan source identifier dengan memberikan arbitrary ID.

## Duplicate Handling

Marketplace dapat menampilkan financial event yang sama melalui beberapa report representation, misalnya summary dan detail.

Sistem harus:
- tidak menghitung event yang sama dua kali;
- tetap mempertahankan event berbeda meskipun product, variation, price, dan quantity sama.

Prinsip:

> Deduplicate identical representations of the same source event, bukan sekadar rows yang terlihat mirip.

## Reconciliation

Reconciliation adalah core business function.

Service harus mempertahankan perbedaan:
- order identity
- order-line identity
- income-line identity
- refund-event identity

Refund matching sebaiknya menggunakan line identity bila tersedia.

Fallback matching hanya boleh digunakan jika identity yang lebih kuat tidak tersedia dan aturan fallback terdokumentasi.

Setiap perubahan matching rule harus memiliki regression test.

## Fulfillment Quantity Semantics

Order quantity dan fulfillment quantity adalah konsep berbeda.

Jangan menggunakan financial quantity sebagai proxy fulfillment quantity.

Bedakan:
- ordered quantity
- fulfilled quantity
- returned quantity
- cancelled quantity
- financial refund quantity

Perubahan pada semantics quantity wajib memiliki domain tests.

## Importers

Importer penting:
- `OrderReportImporter`
- `IncomeReportImporter`

Importer harus:
- memvalidasi source data;
- menormalisasi field;
- mempertahankan source identifiers;
- tidak memfabricate identifiers;
- hanya deduplicate jika identity rule membenarkannya;
- mempertahankan transaction atomicity bila diperlukan;
- deterministic.

Operasi yang seharusnya atomic tidak boleh meninggalkan partial/inconsistent data.

## Shopee Integration

Aplikasi menggunakan Shopee Open Platform/API.

Area penting:
- Shopee API client
- authentication/authorization
- order synchronization
- income synchronization
- API promotion/import workflows
- audit logging

Service penting:
`ShopeeApiClient`

API promotion harus mempertahankan identity semantics yang sama dengan report importer.

Khususnya, `refund_event_identity` tidak boleh hilang saat income dipromosikan melalui API workflow.

## Auditability

Prioritaskan behavior yang deterministic dan traceable.

- Pertahankan source identifiers.
- Hindari destructive normalization.
- Jangan silent merge records.
- Gunakan explicit identity fields.
- Buat reconciliation decisions reproducible.
- Pertahankan informasi audit yang relevan.
- Jangan menyembunyikan ambiguity pada data source.

Jika source ambiguous, pertahankan ambiguity daripada menciptakan certainty palsu.

## Testing

Area test penting:
- Income report importing
- Marketplace reconciliation
- Shopee API promotion
- order/income identity
- refund behavior
- atomicity
- duplicate handling

Regression cases penting:
- distinct order lines tetap distinct
- distinct variations tetap distinct
- stronger source identifier mendapat prioritas
- order identity tetap level-order
- distinct refund applications tetap distinct
- duplicate summary/detail tidak double-count
- missing application number tidak membuat fake event ID
- exact duplicate no-ID rows ditangani dengan benar
- import deterministic
- atomic operation tidak meninggalkan partial corruption
- API promotion mempertahankan identity
- refund event match ke order line yang benar
- unrelated lines tidak tergabung

## Current Test Baseline

Setelah P1 line-identity work, full test suite telah dikonfirmasi:

**280 tests passed, 1,980 assertions, 0 failures.**

Baseline ini tidak boleh diasumsikan tetap valid setelah perubahan; rerun relevant tests.

## Completed Milestone: P1 — Harden Order/Income Line Identity

Goal:

> Harden order-line and income-line identity while distinguishing refund events from order lines.

Important implementation areas:
- `app/Services/RefundEventIdentity.php`
- `app/Services/IncomeReportImporter.php`
- `app/Services/MarketplaceReconciliationService.php`
- `app/Services/ShopeePromotionService.php`
- refund event identity migration
- related feature tests

Conceptual structure:

```
Order
  └── Order Line
        └── Financial / Refund Events
```

## Current Git Checkpoint

Latest known development HEAD:
`5b8be1a`

Commit:
`clean promotion service namespace import`

P1 work sudah committed dan pushed ke `origin/development`.

Latest local status juga menunjukkan dua perubahan lokal yang terpisah dari P1:
- `Caddyfile`
- `compose.yaml`

Jangan memasukkan kedua file tersebut ke commit P1 tanpa memastikan perubahan tersebut memang bagian dari task Docker/Vite yang sedang dikerjakan.

## Git Commit Principles

Gunakan commit message yang menjelaskan perubahan aktual.

Contoh:
- `harden order income line identity`
- `preserve refund event identity in API promotion`
- `fix mysql 1093 in refund identity migration`

Hindari:
- giant mixed commits
- unrelated formatting
- generated files tanpa alasan
- accidental Docker/environment changes dalam domain commit

Sebelum commit:
```bash
git status
git diff --check
```

Setelah perubahan signifikan:
```bash
./vendor/bin/sail artisan test
```

Untuk perubahan core domain, jalankan full suite.

## Database Safety

Jangan menggunakan `migrate:fresh` kecuali diminta secara eksplisit dan user memahami bahwa data database akan dihapus.

Utamakan migration normal dan targeted verification.

Migration harus aman untuk MySQL 8.4 dan memperhatikan:
- existing indexes
- legacy index names
- rollback behavior
- existing data
- duplicate rows
- MySQL-specific constraints

## Coding Style

Prefer:
- small focused services
- explicit domain semantics
- deterministic behavior
- strong typing
- Laravel conventions
- regression protection
- minimal changes

Avoid:
- unnecessary abstraction
- speculative refactoring
- architecture changes tanpa kebutuhan konkret
- dependency baru tanpa justifikasi
- menyembunyikan domain rules dalam generic helpers

Saat memodifikasi code existing, pertahankan behavior yang sudah ada kecuali task memang mengubahnya.

## Decision-Making Rules

Saat menganalisis issue baru:
1. Inspect implementation existing terlebih dahulu.
2. Identifikasi domain invariant.
3. Cari apakah source menyediakan identifier yang lebih kuat.
4. Pertahankan source identity.
5. Jangan fabricate identifiers.
6. Identifikasi duplicate representations.
7. Tambahkan regression test untuk failure mode.
8. Buat perubahan sekecil mungkin yang benar.
9. Jalankan relevant tests.
10. Jalankan full suite jika core domain terpengaruh.
11. Jalankan `git diff --check`.
12. Jangan menyentuh local changes yang unrelated.

Jika marketplace data ambiguous, prioritaskan **data integrity dan auditability daripada aggressive matching**.

## Current Priority

Project berada dalam fase hardening.

Task berikutnya harus berasal dari roadmap project atau requirement eksplisit.

Sebelum perubahan domain baru, verifikasi:
- schema
- importer behavior
- reconciliation behavior
- API promotion behavior
- existing regression tests

Kemudian implementasikan perubahan terkecil yang coherent.

## Communication Preference

Developer lebih menyukai:
- penjelasan dalam Bahasa Indonesia
- rekomendasi langsung dan praktis
- inspeksi repository sebelum menyarankan perubahan
- best practice tanpa kompleksitas yang tidak perlu
- functionality first
- PrimeVue untuk UI bila sesuai
- direct code/repository changes jika diminta eksplisit
- relevant tests dan commit
- penjelasan jelas tentang alasan perubahan
- tidak ada unrelated rewrites

Saat melaporkan pekerjaan selesai, rangkum:
1. Apa yang berubah.
2. Mengapa berubah.
3. Test yang dilakukan.
4. Commit yang dibuat.
5. Local changes atau risk yang masih tersisa.

## Context Maintenance

Dokumen ini adalah high-level project context, bukan pengganti source code atau Git history.

Update dokumen jika ada perubahan arsitektur atau domain yang signifikan.

Jangan menyimpan:
- password
- API keys
- access tokens
- `.env` secrets
- private credentials
- authentication material sensitif

Source code dan Git history tetap menjadi source of truth untuk implementation details.
