# Marketplace Analytics — Data Model

Dokumen ini mendeskripsikan semantic model. Nama tabel/kolom dapat berubah; makna domain tidak boleh berubah tanpa keputusan eksplisit.

## Conceptual model

Marketplace -> Order -> Order Line -> Income/Financial Records
Marketplace -> Order -> Order Line -> Refund Event
Order Line -> Product/SKU/Variation references
Order Line -> Fulfillment quantities
Order Line -> Cost allocation
Reconciliation -> matching decision/status/financial projection

## Marketplace Order

Represents one marketplace order.

Business identity:
marketplace + reliable marketplace order identifier.

Untuk Shopee, marketplace order number adalah order-level identity bila tersedia.

Order bukan product, order line, income event, atau refund event.

## Order Line

Represents one product/variation line belonging to an order.

Relevant attributes dapat mencakup:
- order number
- item/line index
- product name
- product key
- SKU reference
- variation name
- variation key
- source line identity
- ordered quantity
- fulfilled quantity
- returned quantity
- cancelled quantity
- prices
- fulfillment/shipping information

item_index dan source line identity tidak boleh dianggap interchangeable tanpa bukti dari source.

## Product, SKU, Variation

Ini adalah merchandise identity/classification concepts.

Bedakan:
- Product identity
- SKU identity
- Variation identity
- Order-line identity
- Financial-event identity

Variation berbeda tidak boleh digabung hanya karena product name mirip.

## Income and Income Line

Income merepresentasikan marketplace financial information associated with an order dan/atau financial event.

Dapat mencakup product/unit price, income amount, platform/admin fee, shipping fee, promotion/service fee, processing fee, tax, refund-to-buyer, refund event information, dan source identifiers.

Income line adalah source financial record/line. Identity mengikuti strongest source identity.

Product, variation, price, dan quantity adalah matching evidence, bukan identity otomatis.

## Refund Event

Refund event merepresentasikan refund application/financial event.

Jika source menyediakan No. Pengajuan, itu adalah source identity evidence.

Internal identity adalah deterministic fingerprint.

Jika application identifier tidak tersedia:
refund_event_identity = NULL

Jangan fabricate identity.

## Quantity model

Preserve separate:
- ordered_quantity
- fulfilled_quantity
- returned_quantity
- cancelled_quantity
- financial_refund_quantity

Masing-masing menjawab pertanyaan domain yang berbeda.

## Reconciliation

Reconciliation adalah derived business view atas order lines dan financial records.

Dapat mencakup:
- business status
- settlement status
- match method
- match confidence
- refund evidence
- financial projection
- cost/HPP status

Reconciliation status bukan source identity dan harus reproducible.

## Order Cost Allocation

Cost allocation menghubungkan HPP/cost dengan order line.

Relevant concepts:
- total HPP
- HPP per base unit
- quantity base unit
- effective HPP record
- master product
- master unit
- cost status

Cost allocation tidak boleh mengubah source order/income identity.

## Financial model

Current reconciliation code memusatkan financial projection melalui CanonicalFinancialProjectionService.

Derived concepts:
- order subtotal
- platform/admin fee
- shipping fee
- promotion fee
- processing fee
- tax
- total fee
- penghasilan
- HPP
- laba

Derived values harus tetap dapat dibedakan dari raw source values.

## Identity versus attributes

Identity menjawab: “which source entity/event is this?”

Attributes menjawab: “what does that entity/event contain?”

Product name, variation name, price, dan quantity umumnya adalah attributes/evidence.

## Ambiguity

Ambiguous source data adalah valid application state.

Contoh:
- missing refund application number
- multiple candidate income records
- insufficient evidence for exact matching

Jangan membuat certainty palsu.

## Model evolution

Saat menambah field:
1. tentukan apakah source fact, normalized value, derived value, atau identity;
2. tentukan null semantics;
3. tentukan uniqueness semantics;
4. tentukan provenance bila derived;
5. tambah regression coverage;
6. verifikasi migration safety pada MySQL 8.4.
