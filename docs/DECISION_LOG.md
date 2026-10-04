# Marketplace Analytics — Decision Log

Dokumen ini menyimpan keputusan engineering/domain yang memiliki konsekuensi jangka panjang.

## Decision 001 — Preserve Separate Identity Levels

Status: Accepted

Order, order line, income line, dan refund event adalah identity levels yang berbeda.

Reason: financial representations dan refund events dapat memiliki semantics berbeda dari order lines. Menggabungkannya menciptakan risiko silent merge dan reconciliation yang salah.

Consequence: matching harus menggunakan identity yang sesuai levelnya.

## Decision 002 — Prefer Reliable Source Identifiers

Status: Accepted

Jika marketplace menyediakan identifier reliable, identifier tersebut diprioritaskan.

Reason: source identifier memberi evidence identity lebih kuat daripada kombinasi descriptive fields.

Consequence: jangan membuat synthetic identifier bila source sudah menyediakan identifier reliable.

## Decision 003 — Do Not Fabricate Missing Refund Identity

Status: Accepted

Jika source tidak menyediakan refund application identifier seperti No. Pengajuan, refund_event_identity boleh NULL.

Reason: tidak adanya source identifier adalah ambiguity nyata.

Consequence: application tidak membuat arbitrary/fake refund event ID.

## Decision 004 — Deduplicate Source Events, Not Similar Rows

Status: Accepted

Deduplicate hanya bila rows merupakan duplicate representation dari source event yang sama.

Reason: product, variation, price, dan quantity yang sama belum tentu financial event yang sama.

Consequence: matching/deduplication mempertimbangkan source identity dan representation semantics.

## Decision 005 — Separate Fulfillment and Financial Quantities

Status: Accepted

Ordered, fulfilled, returned, cancelled, dan financial refund quantities adalah konsep berbeda.

Reason: financial records tidak otomatis menjelaskan physical fulfillment state.

Consequence: financial quantity tidak boleh menjadi proxy fulfillment quantity tanpa explicit rule.

## Decision 006 — Conservative Reconciliation

Status: Accepted

Jika evidence tidak cukup untuk exact matching, pertahankan unmatched/ambiguous state daripada aggressive merge.

Reason: project membutuhkan auditability dan reproducibility.

Consequence: fallback matching harus documented dan tested.

## Decision 007 — API Promotion Preserves Identity Semantics

Status: Accepted

Shopee API promotion harus menggunakan identity semantics konsisten dengan report importer.

Reason: identity tidak boleh berubah hanya karena ingestion path berbeda.

Consequence: refund_event_identity dan identity fields relevan tidak boleh hilang saat promotion.

## Decision 008 — Backend Is Source of Truth for Domain Calculations

Status: Accepted

Domain financial/reconciliation calculations berada di backend.

Evidence: MarketplaceReconciliationService menggunakan CanonicalFinancialProjectionService untuk financial projection.

Consequence: Vue/Inertia menampilkan hasil domain calculation dan tidak membuat formula business yang berbeda.

## Decision 009 — Minimal Coherent Changes

Status: Accepted

Perubahan harus sekecil mungkin tetapi tetap benar secara domain.

Reason: project berada dalam fase hardening; speculative refactoring meningkatkan risiko tanpa kebutuhan konkret.

Consequence: jangan memperkenalkan abstraction, dependency, framework, atau module boundary baru tanpa alasan terverifikasi.

## Decision 010 — Source Code and Tests Remain Implementation Truth

Status: Accepted

Dokumentasi adalah contract/decision context, bukan pengganti source code dan test suite.

Jika dokumentasi dan implementation berbeda:
1. inspect source dan tests;
2. identify intended behavior;
3. tentukan mana yang stale;
4. update keduanya bila contract memang berubah.

Jangan menyelesaikan contradiction dengan asumsi diam-diam.

## Decision 011 — Maintain a Verified Clean Engineering Baseline

Status: Accepted

Pada 2026-10-04, commit `7ef275b` ditetapkan sebagai current clean engineering baseline untuk branch `development`.

Verified state:
- branch `development` synchronized dengan `origin/development`;
- working tree clean;
- test suite: 301 tests passed, 2,100 assertions;
- Laravel Pint: 143 files passed;
- `git diff --check`: clean;
- MVC/DI hardening, Shopee token-refresh ordering, Shopee sync connection precondition, dan local Docker/Caddy hardening sudah termasuk dalam baseline.

Reason: project membutuhkan checkpoint yang eksplisit dan reproducible sebelum melanjutkan pekerjaan domain/backlog berikutnya.

Consequence:
- perubahan berikutnya dimulai dari commit `7ef275b` sebagai baseline;
- jika terjadi regression, baseline ini dapat digunakan sebagai titik pembanding;
- baseline dapat digantikan hanya setelah checkpoint baru diverifikasi dan dicatat di decision log.

## Decision Change Procedure

Jika keputusan berubah:
1. jelaskan alasan;
2. identifikasi affected invariants;
3. inspect schema/importer/reconciliation/API behavior;
4. add/update regression tests;
5. update decision log;
6. update affected contracts;
7. run relevant tests;
8. run full suite untuk core-domain changes;
9. run git diff --check.

Gunakan nomor keputusan berikutnya secara berurutan.
