# EmptyState Contract

`EmptyState` is the shared presentation component for an empty or no-result state.

## Location

`resources/js/Components/EmptyState.vue`

## Contract

- Use it when a list, table, panel, or page has no content to display.
- `title` is optional; `description` is optional; `icon` defaults to `pi pi-inbox`.
- The default visual treatment uses semantic theme tokens (`text-color`, `text-color-secondary`).
- Optional slot content is reserved for contextual actions or supporting content.
- The component contains no domain logic and must not decide whether data is truly empty.
- Do not use hard-coded light-only colors such as `text-slate-*`, `bg-white`, or `text-white`.
- For DataTable empty slots, keep the component concise; domain-specific wording remains in the page.

## Usage

```vue
<template #empty>
    <EmptyState
        title="Belum ada transaksi"
        description="Import laporan order terlebih dahulu."
    />
</template>
```

## Accessibility

The icon is decorative. Meaningful state information must be present as text; color or iconography must not be the only signal.

## Validation

```bash
./vendor/bin/sail npm run typecheck
./vendor/bin/sail npm run build
git diff --check
```
