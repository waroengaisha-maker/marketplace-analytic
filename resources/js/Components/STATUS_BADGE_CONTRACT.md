# StatusBadge Contract

StatusBadge is the shared presentation component for non-interactive status/category indicators.

## Location

`resources/js/Components/StatusBadge.vue`

## Contract

- Wrap PrimeVue `Tag`; do not duplicate Tag styling in pages.
- `value` is the display label.
- `severity` controls the semantic visual tone.
- `rounded` and `icon` are optional presentation controls.
- Business/domain status mapping stays in the page, composable, or domain-facing adapter. The shared component MUST NOT contain marketplace-specific status rules.
- Use PrimeVue semantic severity values: `success`, `secondary`, `info`, `warn`, `danger`, `contrast`.
- Do not use hard-coded background/text colors to reproduce a status badge.
- Status badges are non-interactive. Do not use them as buttons or links.

## Usage

```vue
<script setup lang="ts">
import StatusBadge from '@/Components/StatusBadge.vue'
</script>

<StatusBadge value="Settled" severity="success" />
<StatusBadge value="Unmatched" severity="warn" />
```

## Accessibility

PrimeVue Tag does not add an interactive role by default. Keep status text visible and meaningful; do not rely on color alone to communicate the state. PrimeVue's Tag API supports `value`, `severity`, `rounded`, and `icon`.

## Theme

The component must remain theme-aware through PrimeVue semantic tokens. No page-specific light/dark override should be added to StatusBadge.

## Validation

Any change to StatusBadge or its contract must pass the project's frontend validation commands:

```bash
./vendor/bin/sail npm run typecheck
./vendor/bin/sail npm run build
git diff --check
```
