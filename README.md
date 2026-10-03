# Marketplace Analytics

Marketplace Analytics adalah aplikasi internal untuk mengimpor, menganalisis, dan merekonsiliasi data marketplace, dengan fokus pada integrasi Shopee.

Fokus utama project adalah **data integrity, deterministic behavior, auditability, dan financial reconciliation**.

## Technology Stack

- Laravel 13
- PHP 8.5 runtime
- Composer PHP requirement: `^8.4`
- MySQL 8.4
- Redis
- Inertia.js
- Vue 3
- TypeScript
- PrimeVue
- Tailwind CSS v4
- Vite
- Docker / Docker Compose
- Caddy

Frontend menggunakan Laravel + Inertia.js + Vue 3. Project tidak menggunakan Nuxt atau NestJS.

## Core Domain

Aplikasi memproses beberapa level identity yang harus tetap dibedakan:

- **Order** — identity level order dari marketplace.
- **Order Line** — item/line di dalam order.
- **Income Line** — representasi financial event dari report/API.
- **Refund Event** — event refund/pengajuan refund yang memiliki identity tersendiri bila source menyediakannya.
- **Fulfillment Quantity** — quantity yang benar-benar dipenuhi, terpisah dari quantity financial.

Prinsip penting:

> Jangan menggabungkan record hanya karena product, variation, price, dan quantity terlihat sama.

Jika marketplace menyediakan identifier yang reliable, identifier tersebut diprioritaskan. Sistem tidak membuat identity palsu ketika source tidak menyediakannya.

## Reconciliation

Reconciliation adalah core business function.

Aturan utama:

1. Gunakan identity source terkuat yang tersedia.
2. Gunakan fallback matching hanya ketika identity yang lebih kuat tidak tersedia.
3. Pertahankan ambiguity jika evidence tidak cukup.
4. Jangan melakukan silent merge.
5. Pastikan keputusan reconciliation reproducible.
6. Financial projection tetap berasal dari backend.

Financial projection canonical menggunakan `CanonicalFinancialProjectionService`.

## Import Workflow

Import utama saat ini mencakup:

- `OrderReportImporter`
- `IncomeReportImporter`

Alur konseptual:

```
Marketplace report/API
        ↓
Validation / normalization
        ↓
Source identity preservation
        ↓
Persistence
        ↓
Reconciliation / financial projection
```

Importer harus deterministic, mempertahankan source identifiers, tidak memfabricate identifiers, dan melakukan deduplication hanya jika identity rule membenarkannya.

## Shopee Integration

Aplikasi menyediakan integrasi dengan Shopee Open Platform/API.

Area integrasi mencakup:

- authentication / authorization
- Shopee API client
- order synchronization
- income synchronization
- API promotion/import workflow
- audit logging

Boundary komunikasi API berada pada `ShopeeApiClient`.

API promotion harus mempertahankan identity semantics yang sama dengan report importer, termasuk refund event identity bila tersedia.

## Architecture

Arsitektur logical:

```
Browser
   ↓
Caddy
   ↓
Laravel application
   ↓
Controllers / HTTP boundary
   ↓
Application / domain services
   ↓
Persistence / external integrations
```

Service penting:

- `OrderReportImporter`
- `IncomeReportImporter`
- `MarketplaceReconciliationService`
- `OrderCostAllocationService`
- `RefundEventIdentity`
- `ShopeePromotionService`
- `ShopeeApiClient`
- `CanonicalFinancialProjectionService`

Detail architecture dan domain contracts tersedia di:

- `docs/ARCHITECTURE.md`
- `docs/DOMAIN_CONTRACTS.md`
- `docs/DATA_MODEL.md`
- `docs/DECISION_LOG.md`
- `PROJECT_CONTEXT.md`

## Local Development

Project menggunakan Docker Compose dengan file `compose.yaml`.

Start containers:

```bash
docker compose -f compose.yaml up -d
```

Application biasanya tersedia di:

- Laravel/Caddy: `http://localhost:8080`
- Vite: `http://localhost:5173`
- Adminer: `http://localhost:8081`

Jika menggunakan Laravel Sail wrapper:

```bash
./vendor/bin/sail up -d
```

Perintah Artisan:

```bash
./vendor/bin/sail artisan <command>
```

Perintah npm:

```bash
./vendor/bin/sail npm <command>
```

## Environment

Copy environment file:

```bash
cp .env.example .env
```

Kemudian konfigurasi database, Redis, application URL, dan credential integrasi sesuai environment.

Jangan commit:

- `.env`
- API keys
- access tokens
- passwords
- private credentials

Untuk production, gunakan `.env.production.example` sebagai referensi konfigurasi.

## Database

Migration normal:

```bash
./vendor/bin/sail artisan migrate
```

**Jangan menjalankan `migrate:fresh` pada database yang berisi data penting.** Command tersebut menghapus seluruh tabel dan data.

## Testing

Full test suite:

```bash
./vendor/bin/sail artisan test
```

TypeScript:

```bash
./vendor/bin/sail npm run typecheck
```

Frontend build:

```bash
./vendor/bin/sail npm run build
```

Code style check:

```bash
./vendor/bin/sail vendor/bin/pint --test
```

Dependency/security audit:

```bash
./vendor/bin/sail composer audit --no-interaction
./vendor/bin/sail npm audit --audit-level=high
```

Latest confirmed full test baseline:

**297 tests passed, 2,088 assertions, 0 failures.**

Baseline tersebut hanya merupakan checkpoint; selalu jalankan test setelah perubahan.

## Development Rules

Project menggunakan perubahan yang kecil dan coherent.

Sebelum mengubah domain behavior:

1. Inspect implementation existing.
2. Identifikasi domain invariant.
3. Cari source identifier yang tersedia.
4. Pertahankan identity source.
5. Jangan fabricate identifier.
6. Tambahkan regression test.
7. Jalankan relevant tests.
8. Jalankan full suite untuk perubahan core domain.
9. Jalankan `git diff --check`.
10. Jangan menyentuh local changes yang unrelated.

Hindari speculative architecture changes, generic repository abstractions, framework migrations, dan dependency baru tanpa kebutuhan konkret.

## Git Workflow

Development branch:

```bash
git checkout development
git pull --ff-only origin development
```

Sebelum commit:

```bash
git status
git diff --check
```

Gunakan commit yang fokus dan menjelaskan perubahan aktual.

## Production

Production configuration menggunakan `compose.production.yaml` dan `.env.production.example`.

Perhatikan terutama:

- application secrets
- database credentials
- `DB_ROOT_PASSWORD`
- `TRUSTED_HOSTS`
- logging
- queue/Redis configuration
- HTTPS/reverse proxy configuration

Jangan menggunakan credential development/default untuk production.

## Documentation

Dokumentasi project dibagi berdasarkan kebutuhan:

- **README.md** — orientasi dan operasi sehari-hari.
- **PROJECT_CONTEXT.md** — konteks project untuk development dan AI coding assistance.
- **docs/ARCHITECTURE.md** — logical architecture dan dependency boundaries.
- **docs/DOMAIN_CONTRACTS.md** — invariant dan contract domain.
- **docs/DATA_MODEL.md** — model data dan relasi penting.
- **docs/DECISION_LOG.md** — keputusan arsitektur/domain yang sudah dibuat.

Source code dan Git history tetap menjadi source of truth untuk implementation details.

## License

Internal project. Licensing and distribution terms should be determined by the project owner.
