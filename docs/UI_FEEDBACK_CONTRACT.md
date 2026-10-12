# UI Feedback Contract

Status: Active project contract  
Scope: User-facing feedback for request throttling and HTTP 429 responses  
Applies to: Laravel responses, Inertia pages, Vue components, and API-driven actions

## 1. Too Many Requests (HTTP 429)

When an action is throttled by the server, the application MUST communicate the rate limit through a toast notification. It MUST NOT use a blocking modal/dialog for this case.

### Required behavior

- Display a toast using the project's shared notification mechanism (PrimeVue Toast where available).
- Clearly state that the user has made requests too quickly and should wait before retrying.
- Include the remaining wait time whenever the server provides it.
- Prefer the server's `Retry-After` header or explicit retry/reset metadata over a guessed duration.
- If the server provides a numeric duration in seconds, format it for people (for example, "Coba lagi dalam 30 detik" or "Coba lagi dalam 2 menit").
- If the remaining time is unknown, show a concise throttling message without inventing a countdown.
- Do not expose raw stack traces, internal exception details, or an unprocessed HTML error response in the toast.
- Do not automatically retry the request unless that behavior is explicitly designed for the action.
- Prevent repeated 429 responses from creating an overwhelming stack of identical toasts; update or deduplicate the active rate-limit notification where practical.

### Message guidance

When remaining time is known:

> Terlalu banyak permintaan. Coba lagi dalam {remaining_time}.

When remaining time is unknown:

> Terlalu banyak permintaan. Tunggu sebentar sebelum mencoba lagi.

The displayed remaining time must reflect the server-provided retry interval. If a live countdown is implemented, it MUST decrease from that interval and MUST NOT imply that the server has lifted the limit unless the countdown is based on authoritative server metadata.

## 2. Remaining-time sources and precedence

Use the most authoritative available server value in this order:

1. HTTP `Retry-After` header (support both delta-seconds and HTTP-date forms where relevant).
2. An explicit, documented response field containing retry-after or remaining seconds.
3. A documented rate-limit reset timestamp/header, converted to remaining time.

Do not derive a precise remaining time from the request timestamp or from a hard-coded default. Clamp negative durations to zero. If metadata is malformed or absent, use the unknown-time message.

## 3. Presentation rules

- Use a warning/error toast appropriate to the shared UI notification convention.
- The toast should be non-blocking and should not prevent the user from navigating or closing other UI.
- Keep the message concise, readable, and consistent across pages.
- Do not replace a task-specific workflow modal (such as an import progress dialog) with a generic modal to report HTTP 429.
- The toast should not erase or misrepresent the actual state of an in-progress operation.
- Respect accessibility: use the notification component's live-region support and avoid excessively frequent countdown announcements.

## 4. Implementation and testing requirements

When implementing or modifying rate-limit handling:

- Centralize common 429 presentation when possible, while preserving action-specific context.
- Handle both Inertia form validation/error flows and ordinary Axios/fetch/API responses where those paths exist.
- Verify that a 429 response displays a toast rather than a modal.
- Verify the toast includes the correct remaining time when retry metadata is present.
- Verify missing or invalid retry metadata produces the generic wait message without a fabricated duration.
- Verify repeated 429 responses do not flood the screen with duplicate notifications.
- Verify non-429 errors retain their existing handling.
- Add automated tests for response parsing/formatting and relevant UI behavior when the surrounding architecture supports them.

## 5. Contract boundaries

This document defines the required user-facing behavior. It does not authorize changing server throttle limits or bypassing rate limiting. Backend response metadata remains the source of truth for the retry interval.

Any intentional exception must be documented here and approved before introducing a blocking modal for HTTP 429.
