# Marketplace Analytics — Product Requirements Document

> **Status:** Living document  
> **Product phase:** Hardening / MVP evolution  
> **Primary marketplace:** Shopee  
> **Audience:** Product owner, developer, QA, AI coding assistants  
> **Source of truth:** Product intent and requirements live here; implementation details live in source code and engineering documentation.

---

## 1. Product Summary

Marketplace Analytics adalah aplikasi internal untuk mengubah data marketplace menjadi informasi operasional dan finansial yang dapat dipercaya, dapat direkonsiliasi, dan dapat diaudit.

Produk tidak berhenti pada aktivitas mengimpor order. Nilai utamanya adalah membantu pengguna menjawab:

- Apa yang sebenarnya terjual?
- Berapa quantity yang dipesan, dipenuhi, dibatalkan, atau dikembalikan?
- Berapa pendapatan dan biaya yang benar-benar tercermin pada data marketplace?
- Transaksi finansial mana yang terkait dengan order line tertentu?
- Refund atau adjustment apa yang terjadi?
- Berapa HPP dan profitabilitas transaksi?
- Jika angka berbeda, apa penyebab dan evidence-nya?

Marketplace Analytics harus memprioritaskan **data integrity, deterministic behavior, auditability, dan financial reconciliation**.

---

## 2. Problem Statement

Data marketplace mengandung beberapa representasi yang berbeda atas transaksi yang sama:

- order;
- order line;
- fulfillment state;
- income/financial records;
- refund events;
- fee dan adjustment;
- product/SKU/variation;
- cost/HPP.

Representasi tersebut tidak selalu memiliki identity yang sama.

Masalah yang ingin diselesaikan:

1. Data dari marketplace perlu dinormalisasi tanpa kehilangan source identity.
2. Order line harus dapat dibedakan secara reliable.
3. Financial event tidak boleh salah dianggap sebagai order line.
4. Refund harus dapat dilacak sebagai event tersendiri bila source menyediakan identity.
5. Quantity finansial tidak boleh digunakan sebagai pengganti physical fulfillment quantity.
6. Duplicate representation dari source event harus dicegah tanpa menggabungkan event yang berbeda.
7. Reconciliation harus reproducible dan menjelaskan evidence yang digunakan.
8. Derived financial values harus dapat dibedakan dari raw marketplace facts.
9. Ambiguity pada source data harus dipertahankan, bukan disembunyikan dengan synthetic certainty.

---

## 3. Product Vision

> **Membangun satu sumber informasi operasional dan finansial marketplace yang dapat dipercaya karena setiap angka dapat ditelusuri kembali ke source event, identity, dan aturan bisnis yang menghasilkan angka tersebut.**

Produk harus membantu pengguna bergerak dari:

`Marketplace data`

menjadi:

`Trusted business information`

melalui alur:

`Import → Normalize → Preserve Identity → Reconcile → Project → Audit`

---

## 4. Product Principles

### 4.1 Data integrity over aggressive matching

Jika evidence tidak cukup untuk menyatakan dua records sama, sistem harus mempertahankan ambiguity/unmatched state.

### 4.2 Source identity is authoritative evidence

Jika marketplace menyediakan identifier reliable, identifier tersebut harus diprioritaskan.

### 4.3 Do not fabricate certainty

Sistem tidak boleh membuat identifier palsu hanya untuk menghilangkan NULL atau ambiguity.

### 4.4 Separate source facts from derived values

Raw marketplace facts dan hasil perhitungan internal harus dapat dibedakan.

### 4.5 Reconciliation must be reproducible

Input yang sama dan rule yang sama harus menghasilkan keputusan yang sama.

### 4.6 Backend is the domain source of truth

Business calculation, financial projection, dan reconciliation decision berasal dari backend. UI hanya merepresentasikan hasil tersebut.

### 4.7 Functionality first

Implementasi harus menyelesaikan kebutuhan bisnis terlebih dahulu dengan perubahan yang minimal dan coherent.

### 4.8 Auditability is a product feature

Kemampuan menjelaskan "mengapa angka ini seperti ini" adalah bagian dari product value, bukan sekadar kebutuhan engineering.

---

## 5. Target Users

### 5.1 Business Owner

Membutuhkan:

- ringkasan penjualan;
- pendapatan;
- biaya;
- profitabilitas;
- refund/return;
- exception yang perlu diperiksa.

### 5.2 Operations / Marketplace Admin

Membutuhkan:

- data order;
- status fulfillment;
- quantity;
- product/variation;
- import/sync status;
- data yang belum tereconcile.

### 5.3 Finance / Accounting

Membutuhkan:

- income/financial events;
- fees;
- refunds;
- reconciliation;
- HPP;
- audit trail;
- evidence untuk perbedaan angka.

### 5.4 Developer / Maintainer

Membutuhkan:

- domain contracts yang eksplisit;
- deterministic behavior;
- regression protection;
- source identity preservation;
- dokumentasi product yang stabil terhadap perubahan teknologi.

---

## 6. Product Goals

### G1 — Reliable marketplace ingestion

Sistem dapat menerima data marketplace melalui report/API tanpa kehilangan identity dan source facts yang penting.

### G2 — Correct transaction identity

Sistem membedakan order, order line, income line, dan refund event.

### G3 — Trustworthy reconciliation

Sistem dapat menghubungkan order dan financial records berdasarkan evidence terkuat yang tersedia.

### G4 — Correct quantity semantics

Sistem membedakan ordered, fulfilled, returned, cancelled, dan financial refund quantity.

### G5 — Explainable financial information

Derived financial values dapat ditelusuri ke source data dan aturan perhitungan.

### G6 — Operational visibility

Pengguna dapat menemukan records yang unmatched, ambiguous, incomplete, atau memerlukan tindakan.

### G7 — Extensible marketplace integration

Model product harus memungkinkan marketplace tambahan di masa depan tanpa mengubah fundamental domain semantics.

---

## 7. Non-Goals

Hal berikut bukan tujuan MVP saat ini:

- menjadi accounting/ERP penuh;
- menggantikan marketplace seller center;
- menjadi warehouse management system penuh;
- membuat payment gateway sendiri;
- membuat tax filing system;
- melakukan automatic matching agresif untuk menghilangkan semua unmatched records;
- memperkenalkan framework baru hanya untuk kebutuhan fitur;
- memaksa semua marketplace memiliki model identity yang sama;
- menghapus raw source data demi model yang lebih sederhana.

Future scope dapat menambahkan integrasi marketplace lain, tetapi tidak boleh mengorbankan semantics yang sudah ditetapkan.

---

## 8. Core Product Model

Model konseptual utama:

```
Marketplace
    │
    └── Order
          │
          └── Order Line
                ├── Product / SKU / Variation
                ├── Fulfillment Quantities
                ├── Cost Allocation
                └── Financial / Refund Evidence

Income / Financial Records
    └── Income Line
          └── Refund / Adjustment Event

Order Line + Financial Records
    └── Reconciliation
          └── Canonical Financial Projection
```

Identity levels **tidak boleh disamakan**:

1. Order identity
2. Order-line identity
3. Income-line identity
4. Refund-event identity

---

## 9. Core Domain Semantics

### 9.1 Order

Order merepresentasikan satu order marketplace.

Jika source menyediakan reliable order identifier, identifier tersebut adalah business identity utama.

Order bukan:

- order line;
- income event;
- refund event;
- product.

### 9.2 Order Line

Order line merepresentasikan item/variation line di dalam order.

Order dapat memiliki lebih dari satu line.

Product name + variation + price + quantity bukan identity yang cukup apabila source menyediakan identifier yang lebih kuat.

### 9.3 Income Line

Income line merepresentasikan financial record/event.

Income line tidak otomatis identik dengan order line.

Minimal bedakan:

- normal order income;
- refund income;
- financial adjustment/event lainnya.

### 9.4 Refund Event

Refund event adalah financial/application event yang memiliki semantics tersendiri.

Jika source menyediakan identifier seperti `No. Pengajuan`, identifier tersebut digunakan sebagai source identity evidence untuk deterministic internal identity.

Jika source tidak menyediakan identifier tersebut:

`refund_event_identity = NULL`

NULL adalah valid state dan tidak boleh diatasi dengan arbitrary ID.

### 9.5 Product / SKU / Variation

Product, SKU, dan variation adalah merchandise identity/classification concepts.

Variation berbeda tidak boleh digabung hanya karena nama product sama atau mirip.

### 9.6 Quantity

Sistem wajib mempertahankan perbedaan:

- `ordered_quantity`
- `fulfilled_quantity`
- `returned_quantity`
- `cancelled_quantity`
- `financial_refund_quantity`

Financial refund quantity tidak otomatis berarti physical returned quantity.

### 9.7 Cost / HPP

Cost allocation menghubungkan biaya/HPP dengan order line tanpa mengubah source order/income identity.

### 9.8 Reconciliation

Reconciliation adalah derived business view yang menjelaskan hubungan antara order line dan financial records.

Reconciliation dapat mencakup:

- matching status;
- match method;
- evidence;
- refund evidence;
- financial projection;
- cost/HPP status;
- exception state.

Reconciliation bukan source identity.

---

## 10. Primary User Journeys

### Journey A — Import marketplace data

```
Select/import source
    ↓
Validate
    ↓
Normalize
    ↓
Preserve source identity
    ↓
Persist
    ↓
Deduplicate only where justified
    ↓
Reconcile
    ↓
Report result / exceptions
```

Acceptance:

- source identifiers tetap tersedia;
- invalid rows ditangani secara eksplisit;
- duplicate source representation tidak double-count;
- distinct events tidak silent-merge;
- operation yang harus atomic tidak meninggalkan partial state.

### Journey B — Inspect an order

User membuka order dan dapat melihat:

- order identity;
- order lines;
- product/SKU/variation;
- ordered quantity;
- fulfillment quantity;
- returned/cancelled quantity;
- associated financial evidence;
- refund events;
- cost/HPP;
- reconciliation status;
- relevant source evidence.

### Journey C — Investigate financial discrepancy

User menemukan order yang financial result-nya tidak sesuai ekspektasi.

Sistem harus membantu user melihat:

1. source order;
2. source order line;
3. income record;
4. refund/adjustment event;
5. matching method;
6. derived financial projection;
7. cost allocation;
8. unresolved ambiguity, bila ada.

### Journey D — Sync from Shopee API

```
Authorize / configure Shopee
    ↓
Retrieve marketplace data
    ↓
Validate source response
    ↓
Preserve identity
    ↓
Persist / promote
    ↓
Reconcile
    ↓
Audit synchronization
```

API ingestion harus menghasilkan domain semantics yang sama dengan report ingestion.

---

## 11. Functional Requirements

Priority:

- **P0** — required for product correctness
- **P1** — important for usable MVP
- **P2** — future enhancement

### FR-001 — Marketplace data ingestion [P0]

System MUST support ingestion of marketplace order and financial data through supported ingestion paths.

### FR-002 — Source identity preservation [P0]

System MUST preserve reliable source identifiers.

System MUST NOT replace a reliable source identifier with a weaker synthetic identity.

### FR-003 — Order identity [P0]

System MUST identify an order using the strongest reliable marketplace order identifier available.

### FR-004 — Order-line identity [P0]

System MUST distinguish multiple lines belonging to the same order.

System MUST prefer a reliable source line identifier when available.

### FR-005 — Income-line identity [P0]

System MUST keep financial record identity separate from order-line identity.

### FR-006 — Refund-event identity [P0]

System MUST preserve refund/application identity when provided by the source.

System MUST allow NULL identity when the source does not provide sufficient identity evidence.

### FR-007 — Safe deduplication [P0]

System MUST deduplicate identical representations of the same source event.

System MUST NOT deduplicate records merely because descriptive attributes are equal.

### FR-008 — Quantity semantics [P0]

System MUST maintain separate ordered, fulfilled, returned, cancelled, and financial refund quantities.

### FR-009 — Conservative reconciliation [P0]

System MUST prefer:

1. strongest reliable identity;
2. documented deterministic fallback;
3. unmatched/ambiguous state when evidence is insufficient.

### FR-010 — Reproducible reconciliation [P0]

Given the same source data and rules, reconciliation MUST produce the same result.

### FR-011 — Financial projection [P0]

Financial projection MUST be calculated by backend domain logic.

Frontend MUST NOT implement an independent business formula.

### FR-012 — Source versus derived values [P0]

System MUST preserve the distinction between source facts and derived financial values.

### FR-013 — Auditability [P0]

Relevant source identifiers, matching decisions, and provenance MUST remain inspectable.

### FR-014 — Import atomicity [P0]

Operations that are logically atomic MUST NOT leave inconsistent partial state after failure.

### FR-015 — Retry safety [P1]

Retryable imports/API promotions SHOULD be idempotent or otherwise safe against duplicate persistence.

### FR-016 — Exception visibility [P1]

Users SHOULD be able to identify:

- unmatched records;
- ambiguous matches;
- missing identity;
- missing financial evidence;
- inconsistent quantity states;
- import failures.

### FR-017 — Order detail [P1]

User SHOULD be able to inspect an order and its related domain evidence from a single workflow.

### FR-018 — Reconciliation detail [P1]

User SHOULD be able to inspect how a reconciliation decision was reached.

### FR-019 — Shopee API integration [P1]

System SHOULD support authenticated Shopee ingestion/promotion while preserving the same identity semantics as report imports.

### FR-020 — Multi-marketplace readiness [P2]

Domain model SHOULD allow additional marketplaces without replacing marketplace-specific source identities with a universal synthetic identity.

---

## 12. Matching and Reconciliation Rules

### Rule R1 — Strong identity first

Use the strongest reliable source identifier available.

### Rule R2 — Identity levels are not interchangeable

An order identifier cannot automatically serve as an order-line identity.

An order-line identifier cannot automatically serve as a refund-event identity.

### Rule R3 — Descriptive attributes are evidence

The following are generally attributes/evidence, not identity by themselves:

- product name;
- variation name;
- price;
- quantity.

### Rule R4 — Fallback matching is explicit

Fallback matching is allowed only when stronger identity is unavailable.

Every fallback rule MUST be deterministic, documented, and tested.

### Rule R5 — Ambiguity is valid

When multiple candidates remain plausible and evidence is insufficient, the system MUST preserve an ambiguous/unmatched state.

### Rule R6 — No silent merge

The system MUST NOT merge distinct source events merely to obtain a cleaner UI or complete reconciliation.

### Rule R7 — Refund is not automatically return

A financial refund event does not automatically imply physical item return.

Returned quantity must be supported by fulfillment/return evidence.

### Rule R8 — Financial quantity is not fulfillment quantity

Financial quantities MUST NOT be used as a proxy for physical fulfillment state without an explicit domain rule.

---

## 13. Financial Requirements

The product should be able to derive a canonical financial projection from source facts.

Potential components include:

- product/subtotal;
- marketplace discounts;
- seller/platform promotions;
- shipping-related amounts;
- platform/admin fees;
- processing fees;
- taxes;
- refund amounts;
- other adjustments;
- HPP/cost;
- profit.

Exact formulas are domain contracts and implementation documentation, not UI-specific formulas.

Requirements:

1. Raw source values remain traceable.
2. Derived values are reproducible.
3. Missing evidence remains unknown/null when appropriate.
4. Unknown must not silently become zero.
5. A financial adjustment must not automatically mutate fulfillment semantics.
6. Reconciliation results must explain relevant evidence.

---

## 14. Import and Sync Requirements

### Report import

Importer MUST:

- validate source data;
- normalize values;
- preserve source identifiers;
- preserve relevant source facts;
- deduplicate only according to identity semantics;
- be deterministic;
- use transaction boundaries where atomicity is required.

Current core importers:

- `OrderReportImporter`
- `IncomeReportImporter`

### API import/promotion

API workflow MUST:

- preserve identity semantics;
- use the same domain rules as report ingestion;
- preserve refund event identity when available;
- avoid creating duplicate financial events;
- record relevant audit information.

Current integration boundary:

- `ShopeeApiClient`
- `ShopeePromotionService`

---

## 15. UI / UX Requirements

The UI exists to make domain information understandable and actionable.

### UI-001 — Backend-derived truth

The UI MUST display backend-calculated reconciliation and financial values.

### UI-002 — Explainability

Where practical, users SHOULD be able to see the source/evidence behind a result.

### UI-003 — Exceptions are visible

Unmatched and ambiguous records MUST NOT be hidden simply because they make reports less clean.

### UI-004 — Identity clarity

Order, order line, income event, and refund event should be visually distinguishable.

### UI-005 — Quantity clarity

Ordered, fulfilled, returned, cancelled, and financial refund quantities should not be presented as one generic quantity.

### UI-006 — Efficient investigation

An operator/finance user SHOULD be able to move from an order to its relevant financial evidence without manually reconstructing the relationship.

### UI-007 — Consistency

Use the project's established component system and interaction patterns.

PrimeVue is the default component library when an appropriate component exists.

---

## 16. Data Integrity Requirements

The system MUST preserve:

- source identifiers;
- source values needed for audit;
- identity provenance;
- matching evidence;
- meaningful NULL states;
- distinction between source and derived data.

The system MUST NOT:

- fabricate marketplace IDs;
- silently merge different source events;
- overwrite source facts with derived values;
- infer physical returns solely from financial refunds;
- convert ambiguity into arbitrary certainty.

---

## 17. Auditability Requirements

For a material reconciliation result, the system should be able to answer:

1. Which source order is involved?
2. Which order line is involved?
3. Which income record/event was matched?
4. Which refund event was involved, if any?
5. Which source identifiers supported the match?
6. Was a fallback rule used?
7. What financial projection was derived?
8. What cost/HPP was allocated?
9. Why is the result unmatched or ambiguous, if applicable?

Audit information should favor provenance and reproducibility over verbose logging without business value.

---

## 18. Error and Exception Model

Expected exception classes include:

### Identity missing

Source does not provide sufficient identity.

Expected behavior:

- preserve NULL/unknown where appropriate;
- do not fabricate identity;
- surface the limitation if it affects reconciliation.

### Multiple candidates

More than one possible match exists.

Expected behavior:

- do not silently select one unless an explicit deterministic rule resolves it;
- otherwise mark ambiguous.

### No candidate

No matching financial evidence exists.

Expected behavior:

- mark unmatched;
- do not fabricate a relationship.

### Duplicate representation

Multiple rows represent the same source event.

Expected behavior:

- deduplicate only when identity/evidence proves the same event.

### Import failure

Expected behavior:

- preserve transaction atomicity where required;
- expose actionable failure information;
- make retry behavior safe.

---

## 19. Success Metrics

The product should be evaluated primarily on correctness and usefulness rather than feature count.

### Data integrity

- 0 known silent merges of distinct source events.
- 0 fabricated marketplace identifiers.
- Regression tests cover known identity failure modes.

### Reconciliation

- Reconciliation decisions are deterministic.
- Material unmatched/ambiguous records are discoverable.
- Matching rules are explainable.

### Financial correctness

- Derived financial projections are reproducible.
- Raw source facts remain traceable.
- Financial refunds are not incorrectly converted into physical returns.

### Operational reliability

- Imports do not leave partial inconsistent state when atomicity is required.
- Retryable workflows do not double-count source events.

### User usefulness

Users can answer common business questions without manually reconstructing marketplace reports.

Metrics should be refined with real usage data after the MVP is exercised in production-like workflows.

---

## 20. MVP Scope

### In scope

1. Shopee as primary marketplace.
2. Order ingestion.
3. Income/financial ingestion.
4. Order-line identity.
5. Income-line identity.
6. Refund-event identity.
7. Fulfillment quantity semantics.
8. Reconciliation.
9. Canonical financial projection.
10. Cost/HPP allocation.
11. Auditability.
12. Reconciliation/exception visibility.
13. Shopee API integration.
14. Regression test coverage for core domain behavior.

### Not required for MVP completion

- Multiple additional marketplaces.
- Full accounting ledger.
- Automated tax filing.
- Advanced forecasting.
- AI-generated business recommendations.
- Full warehouse management.
- Complex workflow automation unrelated to reconciliation.

---

## 21. Future Scope

Potential future capabilities:

### Marketplace expansion

- Tokopedia;
- Lazada;
- TikTok Shop;
- other marketplaces.

Each integration must preserve marketplace-specific source identity.

### Advanced analytics

- product profitability;
- SKU profitability;
- marketplace profitability;
- margin trends;
- fee analysis;
- refund analysis;
- operational KPIs.

### Operational intelligence

- anomaly detection;
- reconciliation alerts;
- import health monitoring;
- data quality dashboards.

### Automation

- scheduled synchronization;
- automated exception workflows;
- configurable reports;
- notifications.

These are candidates, not commitments, until validated against user needs.

---

## 22. Product Boundaries

### Marketplace Analytics owns

- marketplace data ingestion;
- normalization;
- marketplace identity preservation;
- reconciliation;
- financial projection;
- cost allocation;
- auditability;
- analytics derived from marketplace data.

### Marketplace Analytics does not own

- marketplace source-of-truth order state;
- payment provider settlement authority;
- external accounting ledger;
- physical warehouse execution;
- tax authority records.

External systems remain external sources/authorities where applicable.

---

## 23. Quality Requirements

### Correctness

Domain behavior MUST be covered by automated tests.

### Determinism

The same input and rule set MUST produce the same result.

### Performance

Import and reconciliation should scale with realistic marketplace data volumes without introducing avoidable N+1 queries or repeated full-table scans.

Performance optimization must not weaken identity correctness.

### Security

The application MUST protect:

- API credentials;
- access tokens;
- database credentials;
- private configuration;
- sensitive marketplace data.

Secrets MUST NOT be committed to the repository.

### Reliability

Failed imports and sync operations should be recoverable without creating duplicate financial events.

### Maintainability

Domain semantics should be explicit in services, contracts, tests, and documentation.

---

## 24. Acceptance Criteria for Core Product

The product core is considered functionally sound when all of the following are true:

- [ ] A marketplace order can be represented without losing its source identity.
- [ ] Multiple order lines within an order remain distinguishable.
- [ ] Distinct variations remain distinguishable.
- [ ] Income lines are not treated as order lines by default.
- [ ] Distinct refund applications remain distinct.
- [ ] Missing refund application identity does not create a fake ID.
- [ ] Duplicate representations of the same source event do not double-count.
- [ ] Similar-looking but distinct source events are preserved.
- [ ] Ordered, fulfilled, returned, cancelled, and financial refund quantities remain distinct.
- [ ] Financial refund does not automatically imply physical return.
- [ ] Reconciliation uses the strongest available identity.
- [ ] Fallback matching is deterministic and tested.
- [ ] Ambiguous records remain visible as ambiguous/unmatched.
- [ ] Financial projection is backend-derived.
- [ ] Raw source facts remain distinguishable from derived values.
- [ ] Relevant reconciliation decisions are auditable.
- [ ] API ingestion preserves the same identity semantics as report ingestion.
- [ ] Core failure modes have regression tests.
- [ ] Atomic workflows do not leave partial inconsistent data.
- [ ] Retryable workflows do not double-count source events.

---

## 25. Development Prioritization

When requirements conflict, prioritize in this order:

1. **Data integrity**
2. **Correct domain semantics**
3. **Auditability**
4. **Deterministic reconciliation**
5. **Financial correctness**
6. **Operational usability**
7. **Performance**
8. **Convenience / cosmetic improvements**

A cleaner UI or higher apparent match rate must not be achieved by weakening the first four priorities.

---

## 26. Requirement-to-Engineering Mapping

This PRD intentionally does not prescribe framework-level implementation.

For implementation details, consult:

- `PROJECT_CONTEXT.md`
- `docs/ARCHITECTURE.md`
- `docs/DOMAIN_CONTRACTS.md`
- `docs/DATA_MODEL.md`
- `docs/DECISION_LOG.md`
- `docs/AUDIT_LOG.md`
- source code and tests

The engineering stack may evolve without changing this PRD's product intent.

Current implementation uses:

- Laravel 13;
- PHP 8.5;
- MySQL 8.4;
- Vue 3;
- Inertia.js;
- TypeScript;
- PrimeVue;
- Tailwind CSS;
- Vite;
- Docker/Compose;
- Caddy;
- Redis where configured.

Technology choices are implementation decisions, not product requirements.

---

## 27. Open Product Questions

These questions should be resolved with real business usage rather than assumptions:

1. Which financial report fields are considered canonical for each business metric?
2. What exact operational definition should determine "fulfilled" for every supported Shopee fulfillment state?
3. Which unmatched/ambiguous cases require manual user action?
4. Which financial adjustments should be shown separately versus included in canonical profit?
5. What minimum audit evidence must be retained for long-term financial investigation?
6. What reporting periods and timezone semantics are required for financial reporting?
7. What level of historical data must be retained?
8. Which additional marketplace should be integrated after Shopee, and what domain differences must be supported?
9. Which metrics are most important to the business owner on the primary dashboard?
10. Which reconciliation exceptions are high-value enough to trigger alerts?

Until explicitly decided, implementation should follow the existing domain contracts and choose the conservative interpretation where source evidence is insufficient.

---

## 28. Change Management

This is a living product document.

A requirement change that affects domain semantics SHOULD also trigger review of:

1. `docs/DOMAIN_CONTRACTS.md`
2. `docs/DATA_MODEL.md`
3. `docs/DECISION_LOG.md`
4. affected implementation;
5. regression tests.

For material domain changes:

```
Product requirement
    ↓
Domain contract
    ↓
Decision / impact analysis
    ↓
Implementation
    ↓
Regression tests
    ↓
Verification
```

Do not change implementation first and retrofit the product semantics afterward for core domain behavior.

---

## 29. Current Product Baseline

At the time this PRD was introduced:

- Product focus: marketplace analytics with Shopee as primary integration.
- Core domain: order, order line, income line, refund event, fulfillment, reconciliation, cost allocation.
- Product priority: hardening correctness and auditability.
- Architecture: Laravel + Inertia.js + Vue 3.
- Engineering principle: functionality first, minimal coherent changes.
- Existing identity/reconciliation contracts remain authoritative unless explicitly changed.

This section should be updated when the product enters a materially different phase.

---

## 30. Definition of Done for Product Work

A product change is not complete merely because the UI or code works in one happy-path scenario.

For a material feature:

- requirement is defined;
- scope and non-goals are clear;
- domain semantics are identified;
- source identity behavior is explicit;
- relevant edge cases are covered;
- acceptance criteria are testable;
- implementation preserves existing contracts unless intentionally changed;
- regression tests are added;
- relevant tests pass;
- full suite is run for core-domain changes;
- `git diff --check` passes;
- affected documentation is updated;
- commit is focused and explainable.

---

## 31. Guiding Statement

> **Marketplace Analytics should prefer an honest "we cannot determine this from the available evidence" over a confidently wrong financial result.**

That principle is central to the product.
