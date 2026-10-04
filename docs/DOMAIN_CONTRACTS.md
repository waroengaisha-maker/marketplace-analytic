# Marketplace Analytics — Domain Contracts

Dokumen ini berisi invariant domain yang tidak boleh dilanggar oleh implementation baru.

## Identity

Identity berikut berbeda:
- Order identity
- Order-line identity
- Income-line identity
- Refund-event identity

Jangan menggunakan satu identity sebagai pengganti identity lainnya tanpa aturan eksplisit.

## Order identity

Jika marketplace menyediakan reliable order identifier, gunakan identifier tersebut sebagai order identity.

Marketplace order number adalah identity level-order utama bila source menyediakannya.

Jangan membuat synthetic order identity bila source sudah menyediakan identifier reliable.

## Order-line identity

Order dapat memiliki lebih dari satu order line.

Product name + variation + price + quantity bukan identity yang cukup bila source menyediakan identifier yang lebih kuat.

Prioritas:
1. reliable source identifier;
2. deterministic fallback bila benar-benar diperlukan;
3. jangan fabricate identifier yang tidak diberikan source.

Fallback harus deterministic, terdokumentasi, diuji, dan tidak menggabungkan variant berbeda.

## Income-line identity

Income records merepresentasikan financial records/events dan tidak otomatis identik dengan order lines.

Bedakan minimal:
- normal order income;
- refund income;
- financial adjustment/event lainnya.

Product, variation, price, dan quantity adalah evidence/matching attributes, bukan identity dengan sendirinya.

## Refund-event identity

Refund application/event adalah financial event terpisah.

Jika source menyediakan No. Pengajuan, gunakan source identifier tersebut sebagai input deterministic internal identity.

Implementasi saat ini: App\Services\RefundEventIdentity.

Internal identity menggunakan deterministic SHA-256 fingerprint. Fingerprint tersebut bukan marketplace ID baru.

Jika source tidak menyediakan application number, refund_event_identity boleh NULL.

Jangan membuat arbitrary/fake refund event ID.

## Deduplication

Deduplication harus menjawab apakah rows merupakan representasi identik dari source event yang sama.

Jangan deduplicate hanya karena rows terlihat sama.

Same source event -> deduplicate.
Different source events -> preserve separately.

Summary/detail representation dapat merupakan event yang sama. Sebaliknya, product/variation/price/quantity yang sama tidak membuktikan event yang sama.

## Quantity semantics

Bedakan:
- ordered quantity
- fulfilled quantity
- returned quantity
- cancelled quantity
- financial refund quantity

Financial quantity tidak boleh menjadi proxy fulfillment quantity tanpa aturan eksplisit.

Perubahan quantity semantics wajib memiliki domain regression tests.

## Reconciliation

Priority:
1. strongest reliable identity;
2. documented fallback;
3. unresolved/ambiguous bila evidence tidak cukup.

Fallback tidak boleh diam-diam menjadi primary identity.

Refund event harus match ke order line yang benar bila line identity tersedia.

## Financial semantics

Source facts dan derived values harus tetap dibedakan.

Derived values seperti subtotal, fees, tax, penghasilan, HPP, laba, dan reconciliation status harus reproducible.

Missing evidence boleh tetap unknown/null. Jangan mengubah unknown menjadi zero tanpa domain justification.

## Auditability

Jangan:
- silent merge;
- destructive normalization;
- fabricated identifiers;
- hidden ambiguity;
- overwrite source facts dengan derived values tanpa provenance.

## API promotion

Shopee API promotion harus mempertahankan identity semantics yang sama dengan report importer.

Khususnya refund_event_identity tidak boleh hilang atau berubah karena data berpindah melalui API workflow.

## Contract changes

Perubahan contract harus memiliki alasan domain, impact analysis, regression tests, dan update dokumentasi.


## Profitability Metrics

Profitability analytics MUST consume the canonical financial projection rather than reimplementing financial formulas per report.

Canonical metric semantics:
- **Order Subtotal**: nilai subtotal order line sebagaimana direkonstruksi oleh canonical financial projection.
- **Total Fee**: agregasi platform/admin fee, shipping fee, promotion/service fee, dan processing fee sesuai projection contract.
- **Tax**: tax/PPH yang berasal dari financial record dan diproyeksikan secara canonical.
- **Refund**: financial refund amount; tidak boleh diperlakukan sebagai physical returned quantity.
- **Penghasilan**: canonical financial result after subtotal, refund, fees, and tax according to the existing projection contract.
- **HPP**: allocated Master HPP cost for the order line. HPP is only financially usable when cost allocation status is `ok`.
- **Laba**: `penghasilan - HPP` when both values are available.
- **Profit Margin**: `laba / penghasilan * 100` when `penghasilan` is non-zero and `laba` is available; otherwise NULL.

### Unknown versus zero

Unknown financial evidence remains NULL. In particular:
- missing/ambiguous/unconfirmed HPP MUST NOT become zero HPP;
- unavailable canonical profit MUST remain unavailable;
- zero is valid only when the underlying canonical metric is actually zero.

### Analytics aggregation

Analytics by SKU, product, variation, order, or period MUST aggregate canonical line-level metrics and preserve availability/status information.

A report MUST NOT replace NULL metrics with zero merely to make aggregation appear complete. If a requested aggregate contains unavailable financial components, the report must expose the corresponding availability/data-quality state according to its contract.

Legacy presentation names such as `gross_profit` or `net_profit` MUST NOT introduce a second financial definition. They must either map explicitly to a canonical metric or be removed/renamed as part of a documented contract change.
