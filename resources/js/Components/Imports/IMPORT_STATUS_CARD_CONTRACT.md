# ImportStatusCard Contract

## Purpose

`ImportStatusCard` presents the live state of a report import operation without embedding polling or API concerns in the page.

## Responsibilities

- Render the semantic import state: queued, processing, completed, or failed.
- Use shared `StatusBadge` semantics for the current state.
- Show an indeterminate PrimeVue progress indicator while processing.
- Show imported order/income totals only after completion.
- Show the backend-provided failure message when an operation fails.
- Emit `dismiss` only for terminal states.

## Non-responsibilities

- Fetching or polling the operation endpoint.
- Mutating the import operation.
- Calculating a progress percentage that the backend does not provide.

## Usage

The parent page owns polling and passes the latest operation through the `operation` prop.

The component must not present a percentage unless the backend exposes a real progress metric.
