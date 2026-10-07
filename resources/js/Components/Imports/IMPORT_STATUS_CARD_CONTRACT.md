# ImportStatusCard Contract

## Purpose

`ImportStatusCard` presents the live state of a report import operation inside a blocking PrimeVue Dialog without embedding polling or API concerns in the page.

## Responsibilities

- Render the semantic import state: queued, processing, completed, or failed.
- Present a two-point horizontal upload timeline:
  - `Uploading` → first point pulsing, connector inactive, and `Uploading...` message.
  - `Uploaded` → first point solid, a single moving-dot handoff travels toward the second point, and `Uploaded` message.
  - `Processing` → second point pulsing, connector becomes solid after the moving dot arrives, and `Processing...` message.
  - `Successful` / `Failed` → second point solid with the corresponding terminal message.
- Reflect real-time operation updates passed by the parent component.
- Show imported order/income totals only after completion.
- Show the backend-provided failure message when an operation fails.
- Keep the Dialog non-dismissable while the operation is queued or processing.
- Allow closing only after a terminal state: completed or failed, using the `Close` button.
- Emit `dismiss` only for terminal states.

## Non-responsibilities

- Fetching or polling the operation endpoint.
- Mutating the import operation.
- Calculating a progress percentage that the backend does not provide.

## Usage

The parent page owns real-time updates and passes the latest operation through the `operation` prop.

The component must not present a percentage unless the backend exposes a real progress metric.

## Dialog lifecycle

| Status | Dialog | User can close |
|---|---|---|
| `queued` | Open | No |
| `processing` | Open | No |
| `completed` | Open | Yes |
| `failed` | Open | Yes |

The parent remains responsible for the real-time Echo subscription. The Dialog is only a presentation and interaction boundary for the current operation.
