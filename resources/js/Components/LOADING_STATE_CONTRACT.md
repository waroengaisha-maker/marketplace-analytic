# LoadingState Contract

`LoadingState` is the shared presentation component for contextual loading states outside data-table rendering.

## Location

`resources/js/Components/LoadingState.vue`

## Contract

- Use it when a bounded UI region is waiting for asynchronous data, such as a detail dialog.
- `label` describes what is being loaded and defaults to `Memuat...`.
- The component owns loading presentation only; it does not fetch data or infer loading state.
- The spinner is decorative; the visible label communicates the state.
- Use semantic theme tokens; do not hard-code light/dark colors.
- Do not use it as a replacement for `DataTableState` inside `AppDataTable`.

## Accessibility

The container uses `role="status"` and `aria-live="polite"`. The spinner is decorative and hidden from assistive technology.

## Validation

```bash
./vendor/bin/sail npm run typecheck
./vendor/bin/sail npm run build
git diff --check
```
