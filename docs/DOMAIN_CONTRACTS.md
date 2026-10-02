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
