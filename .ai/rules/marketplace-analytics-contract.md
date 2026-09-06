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

## Account hierarchy

The application has three roles:

- `super_admin`: the primary platform operator; can access admin tools and
  manage regular users and admins, including role changes.
- `admin`: can access admin tools and manage regular users, but cannot manage
  admins or change roles.
- `user`: can access the application only when their account is active and
  within its trial or subscription period.

Privileged users cannot manage their own account through admin actions. New
users are created as regular users and require activation according to the
existing account-access rules.
