# Marketplace Analytics — Product Roadmap

## Scope

Marketplace Analytics saat ini berfokus pada **data Shopee** dan hanya mengolah tiga sumber data:

1. **Shopee Order** — order, order line, product/SKU/variation, quantity, fulfillment, return/refund evidence, dan order identity.
2. **Shopee Income** — product income, promotion, fees/charges, refund/adjustment, settlement/financial events, dan income identity.
3. **Master HPP dari user** — referensi biaya/HPP untuk mengalokasikan cost ke order line dan menghitung profitability.

**Out of scope saat ini:** POS, form penjualan manual, pembelian supplier, inventory/stock management umum, accounting module umum, atau sumber transaksi operasional lain yang tidak berasal dari ketiga sumber di atas.

Roadmap ini adalah product roadmap, bukan daftar seluruh audit engineering. Status milestone harus mencerminkan kemampuan produk yang benar-benar sudah tersedia di repository.

## Status Legend

- **Completed** — capability utama milestone sudah tersedia dan dapat diverifikasi dari implementation/tests.
- **In Progress** — sebagian capability sudah tersedia, tetapi milestone belum lengkap.
- **Planned** — belum menjadi capability produk yang selesai.

---

## Phase 1 — Data Foundation
**Status: Completed (core capability)**

### Goal
Membangun fondasi ingestion dan model data untuk tiga sumber resmi: Shopee Order, Shopee Income, dan Master HPP.

### Current state
- Shopee Order importer sudah tersedia.
- Shopee Income importer sudah tersedia.
- Source identity preservation dan deterministic import sudah menjadi contract.
- Import workflow sudah memiliki queue-backed execution dan persisted operation status.
- Master Product catalog dan unit model sudah tersedia.
- HPP versioning/effective-date resolution sudah tersedia dan diuji.
- Master HPP dapat menjadi sumber cost allocation untuk order line.
- Queue-backed ingestion untuk Shopee Order dan Income sudah tersedia.
- Data-quality state untuk cost allocation tersedia melalui mapping/HPP status.

### Remaining refinement
- Validasi workflow UX end-to-end terhadap kebutuhan pengguna aktual.
- Perluasan observability/reporting atas kualitas data bila dibutuhkan oleh analytics berikutnya.

---

## Phase 2 — Identity & Mapping
**Status: Completed (core capability)**

### Goal
Menghubungkan identity dari Order, Income, dan Master HPP tanpa silent merge atau fabricated identity.

### Completed foundation
- Order identity dan order-line identity dibedakan.
- Income-line identity dibedakan dari order-line identity.
- Refund-event identity dibedakan dari keduanya.
- Reliable source identifiers diprioritaskan.
- Missing refund application identifier tetap dapat menghasilkan NULL, bukan fake ID.
- Deduplication mengikuti source-event identity semantics.
- API promotion mempertahankan identity semantics importer.

### Current state
- Product/SKU/variation-to-HPP mapping sudah tersedia.
- Manual mapping, automatic/exact mapping, ambiguous mapping, dan tenant isolation sudah diuji.
- Mapping dan allocation mempertahankan provenance/status, termasuk `mapping_missing`, `mapping_ambiguous`, dan `mapping_unconfirmed`.
- HPP mapping page sudah mendukung server-side pagination/filter dan manual mapping workflow.

### Remaining refinement
- Penyempurnaan UX/exception workflow bila diperlukan oleh profitability analytics.

---

## Phase 3 — Order ↔ Income Reconciliation
**Status: Completed (core capability)**

### Goal
Merekonsiliasi Order dengan Income/financial records secara konservatif dan dapat diaudit.

### Current state
- Reconciliation merupakan core business function.
- Matching mempertahankan order, order-line, income-line, dan refund-event identity.
- Strong identity diprioritaskan.
- Fallback matching harus documented/tested.
- Ambiguity/unmatched state dipertahankan.
- Financial projection terintegrasi dengan reconciliation.
- Regression coverage untuk identity, refund, fulfillment, promotion, dan duplicate handling sudah tersedia.

### Future refinement
Milestone ini dianggap selesai pada level core engine. Penyempurnaan UI, analytics, dan exception workflows masuk phase berikutnya, bukan alasan untuk membuka kembali core reconciliation tanpa requirement.

---

## Phase 4 — Financial Projection
**Status: Completed (core capability)**

### Goal
Menghasilkan financial view yang reproducible dari Order + Income dan, bila tersedia, HPP.

### Current state
- `CanonicalFinancialProjectionService` menjadi boundary financial projection.
- Projection mencakup subtotal, fee, tax, refund, penghasilan, HPP, laba, status, dan provenance.
- Aggregate projection dan settlement comparison tersedia.
- HPP hanya masuk profitability ketika allocation berstatus `ok`; missing/ambiguous/unconfirmed HPP tidak diperlakukan sebagai zero cost.
- Reconciliation, order summaries, customer summaries, dan export menggunakan financial model yang konsisten.

### Remaining refinement
- Finalisasi vocabulary/contract analytics (misalnya revenue vs net revenue dan margin) sebelum membangun metric lintas dimensi.

---

## Phase 5 — Profitability Analytics
**Status: In Progress**

### Goal
Mengubah financial projection + HPP menjadi profitability analytics.

### Already available
- HPP per order line.
- Revenue/order subtotal, marketplace fee components, promotion fee, refund, penghasilan, dan laba.
- Profitability pada level reconciliation/order/customer summary.
- Margin calculation sudah tersedia pada dashboard/reconciliation layer.
- Historical HPP dan HPP data-quality status sudah ikut memengaruhi profitability.

### Completed in this step
- Profitability metric contract didokumentasikan di `docs/DOMAIN_CONTRACTS.md`.
- Canonical projection ditetapkan sebagai satu-satunya sumber formula profitability analytics.
- NULL/unknown dibedakan dari zero untuk HPP dan profitability.
- Aggregation analytics wajib mempertahankan availability/data-quality state.

### Completed in this step
- Regression test untuk canonical profitability values, margin, dan unavailable HPP sudah ditambahkan.

### Completed in this step
- Canonical profitability aggregation ditambahkan untuk product, SKU, variation, day, dan month.
- Aggregation mempertahankan HPP availability count dan tidak mengubah unavailable HPP menjadi zero.
- Profit dan margin menjadi NULL ketika profitability tidak dapat dipastikan.
- Regression tests ditambahkan untuk supported dimensions dan partial HPP availability.

### Remaining
- Verifikasi test suite pada repository runtime/CI.
- Expose analytics melalui application/UI boundary.
- Perluasan profitability analytics bila dimension tambahan benar-benar dibutuhkan.
- Profit/margin by SKU/product/variation/period.

Semua metric harus dapat ditelusuri kembali ke Order, Income, dan Master HPP.

---

## Phase 6 — Product / SKU Analytics
**Status: Planned**

### Goal
Menyajikan performa produk berdasarkan data transaksi Shopee dan profitability.

Target capability:
- Top SKU by quantity.
- Top SKU by revenue.
- Top SKU by profit.
- Margin ranking.
- Sales trend.
- Profit trend.
- Contribution terhadap total revenue/profit.
- Low-margin/high-volume products.
- High-margin/low-volume products.

Tidak ada inventory/POS semantics yang ditambahkan hanya untuk mendukung analytics ini.

---

## Phase 7 — Promotion / Fee / Refund Analytics
**Status: Planned**

### Goal
Membedah komponen yang mengurangi atau mengubah hasil finansial marketplace.

Target capability:
- Promotion cost/impact.
- Fee breakdown.
- Fee rate.
- Refund rate/value/quantity.
- Refund impact by SKU/product/period.
- Financial adjustments.
- Marketplace cost composition.

Physical return dan financial refund tetap diperlakukan sebagai konsep berbeda sesuai domain contract.

---

## Phase 8 — Dashboard & Reporting
**Status: Planned**

### Goal
Menyediakan presentation layer untuk seluruh analytics yang sudah memiliki backend/domain contract.

Target capability:
- Sales overview.
- Revenue/net revenue.
- HPP.
- Marketplace cost.
- Profit/margin.
- Orders/items.
- Refund metrics.
- Revenue/profit trends.
- Product/SKU performance.
- Reconciliation/data-quality exceptions.

Frontend hanya menampilkan hasil domain calculation dari backend dan tidak membuat formula bisnis sendiri.

---

## Phase 9 — Business Insights / Anomaly Detection
**Status: Planned**

### Goal
Membantu user menemukan kondisi penting secara otomatis dari data yang sudah tervalidasi.

Target capability:
- Profit turun saat revenue naik.
- Promotion/fee terlalu tinggi terhadap revenue.
- SKU terjual tetapi HPP belum tersedia.
- Unmatched Order/Income.
- Refund anomaly.
- Perubahan margin yang signifikan.
- Data-quality anomalies.

Insight harus berbasis evidence yang tersedia. Sistem tidak boleh mengubah data ambiguous menjadi kesimpulan pasti.

---

## Current Roadmap Position

**Current focus: Phase 5 — Profitability Analytics (aggregation complete; exposure next)**

Urutan prioritas berikutnya:

1. Tambahkan regression coverage untuk **profitability metric contract**.
2. Bangun **profitability analytics** by SKU/product/variation/period.
3. Perluas regression coverage untuk aggregation, margin, dan data-quality states.
4. Lanjutkan **product/SKU analytics**.
5. Tambahkan **promotion/fee/refund analytics**.
6. Baru bangun **dashboard/reporting** di atas metric yang sudah stabil.
7. Terakhir tambahkan **business insights/anomaly detection**.

### Product boundary

Jangan menambahkan fitur POS atau transaksi manual hanya karena fitur tersebut tampak berguna secara umum. Setiap capability baru harus menjawab:

> **Apakah capability ini mengolah atau menyajikan informasi yang dapat diturunkan dari Shopee Order, Shopee Income, dan/atau Master HPP?**

Jika tidak, capability tersebut berada di luar scope roadmap ini.
