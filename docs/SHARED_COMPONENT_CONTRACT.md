# Shared Component Contract

Marketplace Analytics uses shared Vue components as **UI contracts**, not as arbitrary markup extraction. A shared component is justified when it standardizes repeated visual semantics or behavior across multiple pages.

## Rules

1. Extract a component only when the same UI contract is used in at least two meaningful places, or when the component establishes a project-wide rule.
2. Keep business/domain decisions outside generic presentation components.
3. Prefer PrimeVue components and semantic theme tokens as the implementation foundation.
4. Props and slots must express the component's contract explicitly.
5. Do not introduce page-specific styling into a shared component.
6. Every shared component must have a focused contract document or a documented section in this file.
7. Component migrations should be incremental and should not change unrelated behavior.

## Component roadmap

The current shared-component refactor is intentionally incremental:

1. `StatusBadge` — standardized status presentation.
2. `EmptyState` — standardized empty/no-result presentation.
3. `DataTableState` — standardized table loading/empty/error states.

Each component is committed independently so regressions can be isolated and reverted cleanly.

## 1. StatusBadge

**Implementation:** `resources/js/Components/StatusBadge.vue`

Use it for non-interactive status/category indicators that currently duplicate PrimeVue `Tag` usage.

The component owns presentation only. Status-to-severity mapping remains outside the component.

See `resources/js/Components/STATUS_BADGE_CONTRACT.md` for the detailed contract.

## 2. EmptyState

**Implementation:** `resources/js/Components/EmptyState.vue`

Use it for empty/no-result presentation. The page owns the wording and domain meaning; the component owns layout and semantic theme styling.

See `resources/js/Components/EMPTY_STATE_CONTRACT.md` for the detailed contract.
