# Marketplace Analytics Contract

## Export filenames

Every Excel and PDF export from any menu must use the shared filename contract
and follow this format:

```text
analytics_<start-date>_sampai_<end-date>_<local-timestamp>.<extension>
```

Use `.xlsx` for Excel and `.pdf` for PDF. The shared helper in
`resources/js/utils/exportFilename.ts` is the reference implementation for the
base filename. Do not create menu-specific filename prefixes or formats.

## Export ordering

When exported data contains product information, rows must be sorted by product
name using Indonesian locale, then by unit price ascending when product names
match.

## Order status categories

All menus that display, filter, summarize, or export order status must use these
categories:

- `Batal`: raw `order_status` is `batal`.
- `Tidak Valid`: the order is not `Batal` and has no tracking number.
- `Settled`: the order has a tracking number and `total_income > 0`.
- `Unsettled`: the order has a tracking number and `total_income <= 0`.

Classification is evaluated in the order listed above so cancelled orders
always remain `Batal`, even when tracking or income data is present.

The default status filter is `Settled` and `Unsettled`. New menus must preserve
the same categories, classification priority, and default filter unless the
user explicitly requests a different behavior.
