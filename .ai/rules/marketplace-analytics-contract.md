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

## Inventory fulfillment and refunds

Shopee's ordered quantity can exceed the seller's physical stock when online
and store stock are temporarily inconsistent. If stock is insufficient, the
seller may ship only the available units and refund the unfulfilled quantity
or value. In this case, `refund_amount` can be nonzero while
`returned_quantity` is zero because the unshipped goods were never returned.

Keep these quantities and amounts distinct:

- `ordered_quantity`: the quantity originally ordered. The Order export provides
  `quantity`, but partial-cancellation behavior for that field is not yet
  validated.
- `fulfilled/sold_quantity`: the quantity actually shipped and sold. The
  current Order export has no explicit `fulfilled_quantity` field. Never invent
  this value from fields that do not establish it.
- `returned_quantity`: the Order export field is a physical-return signal. A
  positive value indicates a reported physical return; its full financial
  relationship with refunds still requires real return data.
- `refund_amount`: the monetary refund issued to the buyer, separate from a
  physical return.
- The current Order export has no explicit `cancelled_quantity` field.

Do not infer shipped or fulfilled quantity from `quantity`, tracking number,
shipment time, or `returned_quantity` unless real Shopee data validates that
inference. A nonzero `refund_amount` does not imply a physical return. A zero
`returned_quantity` does not mean there is no revenue adjustment, and
`returned_quantity` alone does not determine net revenue. Monetary refunds must
eventually be accounted for independently from physical returns.

Represent both supported cases accurately:

- **Refund without partial cancellation:** the order may remain complete or
  fulfilled even though fewer units were shipped and the missing quantity was
  refunded.
- **Partial cancellation before fulfillment:** the order quantity is reduced
  or cancelled before shipment; the cancelled quantity must not later be
  counted as a physical return.

Partial cancellation semantics remain unknown until validated with a real
Shopee partial-cancellation example. The available real workbook does not
establish whether `quantity` changes after partial cancellation.

`Total Penghasilan` is a release-detail field. It may be used as a reconciliation
or check value only after treatment of all relevant fee and tax components has
been confirmed. `Total Pendapatan` is a distinct report value and must not be
treated as automatically equivalent to `Total Penghasilan`.

Repository API research indicates multiple refund/return applications per
order are possible. Current Excel evidence does not establish how multiple
events are represented or aggregated, or whether an amount is final.

### Current Data Limitations

Additional real Shopee data is required to establish: partial-cancellation
quantity semantics; actual fulfilled/shipped quantity; positive physical-return
records and their relationship to refunds; refund application status, finality,
and multi-application aggregation; and the treatment of every relevant fee
and tax component in `Total Penghasilan`.

This contract records business rules and source limitations only; it does not
prescribe or implement a financial formula.

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

Account access and billing state are separate:

- Account status: `pending`, `active`, or `suspended`.
- Subscription status: `none`, `trialing`, `active`, `past_due`, `expired`, or
  `canceled`.
- Payment status: `not_required`, `pending`, `paid`, `failed`, or `refunded`.

Activating a pending account starts its configured trial and sets the
subscription status to `trialing`. Trial changes are only allowed for active
accounts. Payment gateway integration is not part of the current application
behavior.
