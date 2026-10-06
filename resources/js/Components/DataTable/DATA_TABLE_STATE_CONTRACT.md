# DataTableState Contract

`DataTableState` standardizes the presentation of table loading, empty, and error states.

## Location

`resources/js/Components/DataTable/DataTableState.vue`

## Contract

- Supported states are `loading`, `empty`, and `error`.
- The component owns presentation only; it does not fetch data or infer state.
- `AppDataTable` uses it automatically for default loading and empty rendering.
- Pages may provide a custom `#loading` or `#empty` slot when domain-specific wording is needed.
- `title`, `description`, and `icon` are optional.
- Semantic theme tokens are mandatory; no page-specific light/dark color is embedded.
- The component is non-interactive. Actions, if needed, belong in the default slot.

## Accessibility

The state container uses `role="status"` and `aria-live="polite"`. The icon is decorative. State meaning must remain available as text.

## Usage

```vue
<DataTableState state="loading" title="Memuat transaksi..." />
<DataTableState state="empty" title="Belum ada transaksi" />
<DataTableState state="error" title="Gagal memuat transaksi" description="Coba lagi." />
```

## Validation

```bash
./vendor/bin/sail npm run typecheck
./vendor/bin/sail npm run build
git diff --check
```
