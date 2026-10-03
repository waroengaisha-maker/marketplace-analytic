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
| AUDIT-011 | P2 | Import scalability | Report import masih berpotensi mahal bila dilakukan synchronous untuk dataset besar. | **Open** | Queue/import job merupakan roadmap hardening; belum boleh dianggap selesai tanpa implementation + regression/operational verification. |
| AUDIT-012 | P2 | Shopee sync scalability | Sync order/income/escrow perlu queueing dan retry-safe execution untuk workload production. | **Open** | Queue/Redis tersedia di architecture, tetapi sync workflow harus diverifikasi sebelum status dapat dinaikkan. |
| AUDIT-013 | P2 | Sync concurrency | Sync yang sama berpotensi membutuhkan overlap protection/idempotency dan persisted progress/cursor. | **In Progress** | Persisted order/income cursors dan promotion fingerprint sudah ada. Commit `9869811fa0b9237bfafb29c4e969eb533942593a` menambahkan per-account cache lock pada order/income/escrow sync dengan configurable TTL; regression tests ditambahkan pada `89f52ac4022955d4457920d3ffee7e1d9825f6e2`. Tests masih harus dijalankan sebelum Completed. |
| AUDIT-014 | P2 | Queue operations | Production queue workload membutuhkan monitoring/visibility dan failure handling yang dapat diaudit. | **Open** | Belum ada evidence yang cukup untuk menyatakan operational monitoring sebagai Completed. |
| AUDIT-015 | P2 | Reconciliation architecture | `MarketplaceReconciliationService` terlalu besar dan memiliki area yang dapat dipecah secara bertahap. | **Deferred** | Refactoring bukan prioritas selama behavior benar. Jangan melakukan decomposition spekulatif tanpa regression protection. |
| AUDIT-016 | P2 | Financial query duplication | Beberapa financial SQL/query logic berpotensi duplikatif; canonical projection tetap menjadi source of truth. | **Deferred** | Tidak boleh direfactor hanya demi cleanliness. Perubahan harus menjaga canonical financial projection semantics. |
| AUDIT-017 | P2 | Repository hygiene | Repository pernah memiliki artifact/note files yang perlu dibedakan antara project-owned documentation dan accidental artifacts. | **Open** | `devnotes.txt` adalah project-owned note dan dipertahankan. Artifact lain harus diverifikasi sebelum dihapus. |
| AUDIT-018 | P2 | Code style | Pint masih menemukan style debt pada sejumlah existing files. | **Open** | Last recorded Pint check menemukan 32 style issues. Jangan mencampur cleanup massal dengan domain/security changes tanpa task terpisah. |

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
