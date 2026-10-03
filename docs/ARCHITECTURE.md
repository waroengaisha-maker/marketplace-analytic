# Marketplace Analytics — Architecture

## Purpose

Dokumen ini mendeskripsikan arsitektur yang berlaku saat ini. Jangan menganggap struktur yang belum diimplementasikan sebagai bagian dari sistem.

Prioritas:
1. data integrity
2. deterministic behavior
3. auditability
4. explicit domain semantics
5. functionality first
6. minimal coherent change

## Technology baseline

Backend: Laravel 13, PHP 8.5 runtime (Composer requirement `^8.4`), Eloquent, migrations, queues bila diperlukan, Redis bila dikonfigurasi.

Frontend: Vue 3, Inertia.js, TypeScript, PrimeVue, Tailwind CSS v4, Vite.

Infrastructure: Docker/Compose, Laravel Sail-compatible application container, Caddy, MySQL 8.4, Redis, Adminer.

Frontend/backend saat ini adalah Laravel + Inertia.js + Vue 3. Jangan memperkenalkan Nuxt, NestJS, atau framework baru tanpa requirement eksplisit dan evaluasi dampak.

## Logical architecture

Browser -> Caddy -> Laravel application -> Controllers/HTTP boundary -> Application/domain services -> Persistence/external integrations.

Application/domain services saat ini mencakup:
- OrderReportImporter
- IncomeReportImporter
- MarketplaceReconciliationService
- OrderCostAllocationService
- RefundEventIdentity
- ShopeePromotionService
- ShopeeApiClient
- CanonicalFinancialProjectionService

Vite digunakan untuk development frontend.

## Presentation boundary

- Controller menangani HTTP concerns dan orchestration ringan.
- Business rules tidak dipindahkan ke controller hanya karena lebih cepat ditulis.
- Inertia/Vue bukan source of truth untuk domain rules.
- PrimeVue adalah default UI component library bila komponen yang sesuai tersedia.
- Frontend tidak mengarang identity atau reconciliation result yang seharusnya berasal dari backend.

## Import boundary

Marketplace source -> validation/normalization -> source identity preservation -> persistence -> reconciliation/projection.

Importer wajib:
- mempertahankan source identifiers;
- tidak membuat identifier palsu;
- tidak menghilangkan informasi audit saat normalisasi;
- hanya deduplicate bila identity rule membenarkannya;
- deterministic;
- atomic bila operasi memang harus atomic.

## Integration boundary

Shopee API/report adalah external source.

Integration code bertanggung jawab atas authentication/authorization, retrieval, validation, source identity preservation, mapping, API promotion/import workflow, dan audit logging bila relevan.

ShopeeApiClient adalah boundary komunikasi Shopee API.

External response tidak otomatis menjadi canonical domain identity.

## Reconciliation boundary

Reconciliation adalah core business function.

Reconciliation harus membedakan:
- Order
- Order Line
- Income/financial representation
- Refund Event

Matching harus:
1. memakai identity terkuat yang tersedia;
2. memakai fallback hanya bila identity lebih kuat tidak tersedia;
3. mempertahankan ambiguity bila evidence tidak cukup;
4. tidak melakukan silent merge;
5. menghasilkan keputusan reproducible.

## Financial projection

Current reconciliation implementation menggunakan CanonicalFinancialProjectionService dari MarketplaceReconciliationService.

Backend menjadi source of truth untuk financial/reconciliation calculation. Jangan membuat formula business yang berbeda di frontend.

## Data access

Gunakan Eloquent/query builder secara eksplisit untuk workload domain kompleks.

- Hindari N+1.
- Gunakan allowlist untuk dynamic sorting/filtering.
- Raw SQL harus memakai parameter binding/allowlist.
- Identity correctness lebih penting daripada query yang sekadar lebih sederhana.

## Transactions and jobs

Gunakan transaction untuk operasi yang memang harus atomik, termasuk import/promotion/multi-record mutation yang relevan.

Queue/Redis adalah infrastructure, bukan tempat untuk menyembunyikan domain semantics.

Job yang dapat retry harus aman terhadap retry dan tetap deterministic.

Jangan gunakan migrate:fresh kecuali diminta eksplisit dan konsekuensi penghapusan data dipahami.

## Dependency direction

Presentation
-> Application/domain services
-> Persistence and external integrations

Business rules tidak bergantung pada Vue, Inertia, Caddy, atau detail HTTP.

## Architecture change rules

1. Inspect implementation existing.
2. Identify domain invariants.
3. Confirm source identifiers.
4. Prefer minimal coherent change.
5. Add regression tests.
6. Run relevant tests.
7. Run full suite for core-domain changes.
8. Run git diff --check.
9. Jangan menyentuh unrelated local changes.

Hindari speculative abstraction, dependency baru, dan framework migration tanpa kebutuhan konkret.
