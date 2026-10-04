# Marketplace Analytics — Engineering Audit Log

Dokumen ini adalah **engineering audit ledger** proyek Marketplace Analytics.

Tujuannya adalah menjaga jejak audit teknis dari waktu ke waktu: audit yang sudah dilakukan, finding yang ditemukan, tindakan yang diambil, status terkini, dan evidence yang dapat diverifikasi.

## Contract

### 1. Source of truth

Untuk pekerjaan teknis pada repository:

1. Source code dan test suite adalah implementation truth.
2. Git history adalah historical change truth.
3. Dokumen di repository adalah project contract/context.
4. `docs/AUDIT_LOG.md` adalah audit-status truth.
5. Context yang tersimpan di ChatGPT Projects bersifat **advisory/cache** dan tidak boleh dianggap lebih baru daripada repository.

Jika context ChatGPT berbeda dengan repository, model harus membaca repository terlebih dahulu dan menggunakan repository sebagai sumber yang berlaku.

### 2. Audit status

Status yang digunakan:

- **Open** — finding masih membutuhkan tindakan.
- **In Progress** — remediation sedang dikerjakan.
- **Completed** — remediation selesai dan telah diverifikasi sesuai completion criteria.
- **Accepted / No Action** — finding diketahui, tetapi tidak membutuhkan perubahan atau secara eksplisit diterima sebagai kondisi saat ini.
- **Deferred** — valid finding, tetapi sengaja ditunda.
- **Superseded** — audit/finding digantikan oleh keputusan atau implementasi yang lebih baru.

Status harus mencerminkan kondisi **terkini**, bukan status saat audit pertama dibuat.

### 3. Completion criteria

Finding hanya boleh berstatus **Completed** apabila:

1. remediation telah diimplementasikan;
2. behavior yang relevan telah diverifikasi;
3. regression test ditambahkan atau test existing yang tepat telah dijalankan;
4. untuk core-domain changes, full test suite dijalankan;
5. `git diff --check` dijalankan;
6. evidence dicatat di entry audit.

Jika salah satu evidence penting belum tersedia, jangan menyatakan finding Completed hanya berdasarkan asumsi.

### 4. Audit update procedure

Setiap audit/finding baru harus:

1. mendapat ID `AUDIT-NNN` berikutnya;
2. memiliki priority;
3. mencatat finding;
4. mencatat remediation;
5. mencatat verification/evidence;
6. memiliki status terkini;
7. mencatat commit, test, migration, configuration, atau documentation evidence yang relevan.

Jika finding berubah status, **update entry yang sama** dan pertahankan historical note bila perubahan tersebut penting.

Jangan membuat entry baru hanya untuk menyembunyikan status lama.

### 5. Scope

Ledger ini mencatat audit engineering yang mempengaruhi:

- domain/data integrity;
- security dan tenant isolation;
- reconciliation;
- import/promotion;
- API boundaries;
- production safety;
- frontend type/build safety;
- CI/test quality;
- architecture;
- repository/documentation hygiene;
- operational scalability.

Application audit logs (misalnya Shopee integration audit events) adalah mekanisme berbeda dan tidak menggantikan engineering audit ledger ini.

---

## Current Audit Register

| ID | Priority | Area | Finding / Scope | Current Status | Evidence / Notes |
|---|---|---|---|---|---|
| AUDIT-001 | P0/P1 | Domain integrity | Order, order line, income line, dan refund event harus diperlakukan sebagai identity level berbeda. | **Completed** | Ditetapkan dalam `docs/DECISION_LOG.md`, `PROJECT_CONTEXT.md`, importer/reconciliation implementation, dan regression suite. |
| AUDIT-002 | P1 | Order/income identity | Order-line dan income-line identity harus dipertegas dan tidak boleh bergantung pada kombinasi descriptive fields bila source identifier yang lebih kuat tersedia. | **Completed** | Identity rules tercermin pada importer/reconciliation code dan regression tests. Historical implementation checkpoint terdokumentasi di Git history. |
| AUDIT-003 | P1 | Fulfillment semantics | Ordered, fulfilled, returned, cancelled, dan financial refund quantity tidak boleh diperlakukan sebagai quantity yang sama. | **Completed** | Migration/domain implementation dan regression tests untuk fulfillment semantics tercatat di repository. |
| AUDIT-004 | P1 | Refund identity | Refund application/event harus memiliki identity terpisah; source application identifier diprioritaskan dan missing identifier tidak boleh menghasilkan fake ID. | **Completed** | `RefundEventIdentity`, income importer, refund identity migration, reconciliation tests, dan API promotion tests. |
| AUDIT-005 | P1 | API promotion | Shopee API promotion harus mempertahankan identity semantics dari report importer, termasuk refund event identity. | **Completed** | `ShopeePromotionService` dan `ShopeeApiPromotionTest`; historical security regression fix tercatat pada commit `63e8dcf`. |
| AUDIT-006 | P1 | Security / data access | Tenant/data access boundaries harus mencegah cross-account access terhadap marketplace data, exports, sessions, imports, dan integration state. | **Completed** | Security hardening implementation dan regression suite; historical security hardening commit tercatat di Git history. |
| AUDIT-007 | P1 | Request boundaries | Endpoint sensitif membutuhkan rate limiting; production request boundaries dan trusted-host configuration harus di-hardening. | **Completed** | Commit `435a164b17190601376e09903efb692e94edd0e0`; `RateLimitingTest`; production config contract. |
| AUDIT-008 | P2 | Frontend type safety | Repository sebelumnya belum memiliki typecheck baseline yang executable. TypeScript harus pinned dan `vue-tsc` harus menjadi CI gate. | **Completed** | Commit `ad00777`; `tsconfig.json`; `npm run typecheck` PASS; frontend build PASS pada recorded baseline. |
| AUDIT-009 | P2 | Runtime/documentation consistency | Documentation harus mencerminkan Laravel 13, PHP 8.5 runtime, Composer PHP requirement `^8.4`, dan current test baseline. | **Completed** | `PROJECT_CONTEXT.md`, `docs/ARCHITECTURE.md`, dan project README diperbarui melalui Git history. |
| AUDIT-010 | P2 | Migration hygiene | Dua migration menggunakan timestamp yang sama. Keduanya sudah applied dan Laravel membedakan full migration filename. | **Accepted / No Action** | Jangan rename applied migrations hanya untuk memperbaiki timestamp collision. Gunakan unique timestamps untuk migration baru. |
| AUDIT-011 | P2 | Import scalability | Report import masih berpotensi mahal bila dilakukan synchronous untuk dataset besar. | **Completed** | `ImportReportsJob` + persisted `ReportImportOperation` + tenant-scoped status endpoint. Verification recorded 2026-10-04: targeted queue test **4 passed, 18 assertions**; full PHPUnit **306 passed, 2,121 assertions**; `git diff --check` passed before subsequent AUDIT-012 work. |
| AUDIT-012 | P2 | Shopee sync scalability | Sync order/income/escrow perlu queueing dan retry-safe execution untuk workload production. | **Completed** | `ShopeeSyncOperation` + `ShopeeSyncJob`; tenant-scoped status; worker-side per-account lock; `ShouldBeUnique`; 3 retries with backoff; bounded operation summaries; UI polling. Verification 2026-10-04: full PHPUnit **308 passed, 2,174 assertions**; `git diff --check` PASS; TypeScript typecheck PASS; Vite production build PASS. |
| AUDIT-013 | P2 | Sync concurrency | Sync yang sama berpotensi membutuhkan overlap protection/idempotency dan persisted progress/cursor. | **Completed** | Persisted order/income cursors dan promotion fingerprint sudah ada. Per-account cache lock pada order/income/escrow sync dengan configurable TTL mencegah overlapping sync untuk account yang sama. Fix import `Cache` dikomit pada `9d4f2ef40d1ef92c874ee0067f1fcd546f13f5c1`. Regression: `ShopeeApiHardeningTest` **18 passed, 109 assertions**; full PHPUnit **302 passed, 2,103 assertions** pada verification 2026-10-04; `git diff --check` PASS. |
| AUDIT-014 | P2 | Queue operations | Production queue workload membutuhkan monitoring/visibility dan failure handling yang dapat diaudit. | **Completed** | Report-import and Shopee-sync operations persist queued/processing/completed/failed state and expose tenant-scoped status. `compose.production.yaml` provides a dedicated Redis `queue` worker with `restart: unless-stopped`, `--tries=3`, `--timeout=120`, and bounded `--max-time=3600`; `deploy/README.md` documents worker restart and `queue:failed`/retry operations. Queue failures are persisted through Laravel `failed_jobs`, while application failures are sanitized and auditable through operation records and `AccountAuditLog`. Verification 2026-10-04: full PHPUnit **308 passed, 2,174 assertions**; `git diff --check` PASS. |
| AUDIT-015 | P2 | Reconciliation architecture | `MarketplaceReconciliationService` terlalu besar dan memiliki area yang dapat dipecah secara bertahap. | **Deferred** | Refactoring bukan prioritas selama behavior benar. Jangan melakukan decomposition spekulatif tanpa regression protection. |
| AUDIT-016 | P2 | Financial query duplication | Beberapa financial SQL/query logic berpotensi duplikatif; canonical projection tetap menjadi source of truth. | **Deferred** | Tidak boleh direfactor hanya demi cleanliness. Perubahan harus menjaga canonical financial projection semantics. |
| AUDIT-017 | P2 | Repository hygiene | Repository pernah memiliki artifact/note files yang perlu dibedakan antara project-owned documentation dan accidental artifacts. | **Completed** | Root-level artifacts `NUL`, `hello.py`, dan `tmp_master_hpp_test_output.txt` diverifikasi sebagai non-project artifacts dan dihapus pada commit `41a7ea8d1fee195bd128c9963e466180f3618e49`. `devnotes.txt` diverifikasi sebagai project-owned note dan dipertahankan. |
| AUDIT-018 | P2 | Code style | Pint masih menemukan style debt pada sejumlah existing files. | **Completed** | Laravel Pint **143 files PASS** pada 2026-10-04 setelah focused formatting cleanup; perubahan style dipisahkan dari domain/security fixes. Commit `7ef275b`. |

---

## Historical Verification Baseline

Baseline berikut adalah **historical verification record**, bukan klaim bahwa suite masih identik setelah perubahan berikutnya:

- Full PHPUnit suite: **297 tests passed, 2,088 assertions** pada recorded checkpoint.
- TypeScript typecheck: **PASS** pada recorded frontend hardening checkpoint.
- Vite production build: **PASS** pada recorded frontend hardening checkpoint.
- `git diff --check`: **PASS** pada recorded frontend hardening checkpoint.
- Pint test: **NOT CLEAN** pada recorded checkpoint; 32 style issues ditemukan.

Setiap perubahan setelah baseline wajib menjalankan test yang relevan kembali. Baseline lama tidak boleh digunakan sebagai pengganti verification baru.

## Audit Lifecycle

Audit berikutnya harus selalu mengikuti urutan:

1. Inspect repository context and current implementation.
2. Identify invariant/finding.
3. Record or update `AUDIT-NNN`.
4. Implement minimal coherent remediation.
5. Add/update regression tests.
6. Run relevant tests.
7. Run full suite for core-domain changes.
8. Run `git diff --check`.
9. Record evidence and update status.
10. Commit the audit/documentation change with a focused commit.

## Important Rule

**Tidak boleh menyatakan audit "selesai" hanya karena code telah berubah.**

Status Completed berarti evidence remediation dan verification tersedia.

ChatGPT Project context boleh membantu orientasi awal, tetapi sebelum menyimpulkan status, model harus membaca repository context dan audit ledger yang berlaku.

### AUDIT-019 — MVC / Dependency Injection boundary hardening

- **Date:** 2026-10-04
- **Priority:** P2
- **Finding:** Core controllers already used Laravel injection in many endpoints, but ShopeeApiController still used service-locator/manual construction and the dashboard contained non-trivial request orchestration in a route closure.
- **Assessment before remediation:** Partially compliant with MVC/DI best practice.
- **Remediation:** Added ShopeeApiClientFactory; injected controller dependencies; injected the factory into ShopeeSyncService; removed controller app()/manual service construction; moved dashboard handling into DashboardController; added container-resolution regression tests.
- **Verification:** Local verification completed on 2026-10-04: full PHPUnit suite **302 passed, 2,103 assertions**; Laravel Pint **143 files PASS**; `git diff --check` **PASS**. The added `ControllerDependencyInjectionTest` is included in the full suite.
- **Status:** **Completed**

### AUDIT-020 — Shopee token refresh ordering

- **Date:** 2026-10-04
- **Priority:** P1
- **Area:** Shopee API correctness
- **Finding:** Sync workflows could construct a connection-scoped ShopeeApiClient before refreshing an expired access token. Because the client snapshots the connection credentials, a successful refresh could still leave the current sync request using the expired token.
- **Remediation:** All three sync paths — orders, income, and escrow — now refresh the access token before constructing the API client.
- **Additional cleanup:** Removed obsolete static service-locator factories from ShopeeApiClient after introducing the explicit factory; removed unused controller imports/injected parameters.
- **Verification:** Local verification completed on 2026-10-04: full PHPUnit suite **302 passed, 2,103 assertions**; Laravel Pint **143 files PASS**; `git diff --check` **PASS**. The Shopee integration and shadow-validation coverage for the sync paths passed.
- **Status:** **Completed**

## AUDIT-021 — Shopee sync missing-connection precondition

- **Date:** 2026-10-04
- **Priority:** P1
- **Finding:** After the Shopee sync dependency-injection hardening, sync controller actions could pass a missing `ShopeeApiConnection` into `ShopeeSyncService`, whose contract correctly requires a non-null connection. This caused a TypeError/HTTP 500 before the endpoint could return its expected validation response.
- **Impact:** Unconfigured accounts could receive HTTP 500 from sync endpoints; the rate-limit feature test therefore failed before reaching its intended 422 assertions.
- **Remediation:** Added explicit connection guards to orders, income, and escrow sync actions. Missing connections now return HTTP 422 before invoking the sync service. The service contract remains non-null.
- **Regression coverage:** Existing `RateLimitingTest::test_shopee_sync_limit_is_scoped_to_authenticated_user` exercises the missing-connection path while verifying the sixth request reaches HTTP 429.
- **Verification:** Local verification completed on 2026-10-04: full PHPUnit suite **302 passed, 2,103 assertions**; `RateLimitingTest` passed; `git diff --check` PASS.
- **Status:** **Completed**

### AUDIT-022 — Shopee promotion item-index parity

- **Date:** 2026-10-04
- **Priority:** P1
- **Area:** Shopee API promotion / identity compatibility
- **Finding:** `ShopeePromotionService::orderRow()` calculated `item_index` from net quantity after returns, while `OrderReportImporter` calculates it from the ordered quantity. The promotion contract therefore did not actually preserve the Excel import identity algorithm when a line had returned quantity.
- **Impact:** API-promoted order rows could receive a different legacy `item_index` from the equivalent Excel row. This can alter legacy item-index fallback matching and violates the documented requirement that promotion preserve importer identity semantics.
- **Remediation:** Changed promotion `item_index` calculation to use the ordered quantity, matching `OrderReportImporter`. Added a regression assertion in `ShopeeApiPromotionTest::test_promote_writes_validated_lines_income_and_allocations` using a returned quantity of 1 against an ordered quantity of 2.
- **Verification:** Local verification completed on 2026-10-04: `ShopeeApiPromotionTest` passed in the full suite; full PHPUnit suite **302 passed, 2,103 assertions**; `git diff --check` PASS.
- **Status:** **Completed**

### AUDIT-023 — Shopee income promotion duplicate identity without refund event

- **Date:** 2026-10-04
- **Priority:** P1
- **Area:** Income importer / Shopee API promotion / idempotency
- **Finding:** `IncomeReportImporter::persistForPromotion()` skipped duplicate detection whenever `refund_event_identity` was NULL. This allowed identical non-refund income rows with the same `line_identity` to pass validation. The database unique index cannot safely prevent this because `refund_event_identity` is nullable under MySQL uniqueness semantics.
- **Impact:** A duplicate Shopee income snapshot without an application number could be promoted twice, creating duplicate financial evidence and violating the promotion contract's duplicate rejection rule.
- **Remediation:** Duplicate promotion identity detection now uses `user_id + line_identity + refund_event_identity`, with an explicit sentinel for missing refund event identity. Added regression coverage in `IncomeReportImporterTest::test_income_promotion_rejects_duplicate_identity_without_refund_event`.
- **Verification:** Local verification completed on 2026-10-04: `IncomeReportImporterTest` passed in the full suite; full PHPUnit suite **302 passed, 2,103 assertions**; `git diff --check` PASS.
- **Status:** **Completed**


### AUDIT-024 — Queue operational contract

- **Date:** 2026-10-04
- **Priority:** P2
- **Area:** Queue operations / production readiness
- **Finding:** Queue-backed application workflows need an explicit operational contract covering worker lifecycle, retries, failure visibility, and safe recovery.
- **Remediation:** Reused Laravel's existing queue infrastructure without adding a new monitoring dependency. Production Compose defines a dedicated Redis worker with bounded timeout/retry/max-time and automatic restart. Deployment documentation requires restarting the worker after application changes and documents failed-job inspection/recovery. Report-import and Shopee-sync operation records provide application-level status and sanitized failure visibility.
- **Verification:** Full PHPUnit **308 passed, 2,174 assertions**; TypeScript typecheck PASS; Vite production build PASS; `git diff --check` PASS on 2026-10-04. No new runtime dependency introduced.
- **Status:** **Completed**
