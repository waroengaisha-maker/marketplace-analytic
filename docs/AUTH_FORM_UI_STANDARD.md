# Auth Form UI Standard

## Status
**Scope:** Authentication form UI  
**Status:** Active  
**Reviewed:** 2026-10-06

This document defines the UI and interaction baseline for the authentication forms in Marketplace Analytics.

## Covered Forms
- Login
- Register
- Forgot Password
- Reset Password

Authentication remains a standalone experience and does not use the authenticated application shell.

## Design Principles
- PrimeVue components + Tailwind utilities.
- Functionality first, with restrained visual customization.
- Server validation remains authoritative; client validation provides immediate UX feedback.
- Field-specific errors belong next to their fields.
- Global messages are reserved for genuinely global outcomes.
- Password visibility controls must remain inside the form field stacking context and must not escape modal/overlay layers.
- Auth forms must remain responsive and compatible with the application's light/dark theme.
- Avoid premature shared abstractions when the forms have materially different UX requirements.

## Validation Contract

### Login
- Accept the application's configured login identifier.
- Use a generic authentication failure message.
- Do not disclose whether an account exists.
- Show processing state while submitting.
- Provide Remember me, Forgot password, and Register navigation.

### Register
Required fields:
- Name
- Username
- Email
- Password
- Password confirmation

Phone is optional.

Password requirements are:
- At least 12 characters
- Uppercase letter
- Lowercase letter
- Number
- Special character
- Confirmation must match

The client-side password checklist mirrors the backend `PasswordValidationRules` policy.

### Forgot Password
- Validate email format before submission.
- Show processing state.
- Show a success message after the request completes.
- Do not reveal whether the submitted email belongs to an account.

### Reset Password
- Reuse the same password policy as Register.
- Validate password confirmation.
- Show server validation errors at the relevant field.
- After successful reset, remain on the reset page long enough to show a blocking success Dialog.
- The Dialog provides an explicit **Go to Login** action; there is no automatic redirect from the reset form.
- Password show/hide controls must not use an excessive global z-index.

## Error Presentation
Prefer this hierarchy:
1. Global `Message` for global form outcomes.
2. Field `:invalid` state for the affected input.
3. A single field-specific error message below the affected field.

Do not render the same server validation error both as a global Message and as a field error.

## Theme and Accessibility
- Use theme tokens such as `text-surface-900`, `text-surface-0`, and PrimeVue theme variables instead of hardcoded text colors where possible.
- Use the application's `app-dark` theme selector rather than a generic `.dark` selector.
- Inputs must have associated labels.
- Password visibility buttons must have meaningful `aria-label` and `aria-pressed` state.
- Use appropriate `autocomplete` values.
- Required fields must be identifiable without relying on color alone.

## Regression Checklist
Before considering an Auth UI change complete:
- Login success and invalid credentials.
- Register success and password policy validation.
- Forgot-password validation and success feedback.
- Reset-password validation and success Dialog.
- Password show/hide behavior.
- Dialog overlay does not expose underlying password controls.
- Light and dark theme rendering.
- Responsive layout.
- TypeScript check.
- Production build.
- Full Laravel test suite.
- `git diff --check`.

## Source of Truth
Implementation:
- `resources/js/Pages/Auth/Login.vue`
- `resources/js/Pages/Auth/Register.vue`
- `resources/js/Pages/Auth/ForgotPassword.vue`
- `resources/js/Pages/Auth/ResetPassword.vue`

Backend contracts:
- `app/Actions/Fortify/PasswordValidationRules.php`
- `app/Actions/Fortify/CreateNewUser.php`
- `app/Actions/Fortify/ResetUserPassword.php`
- `app/Http/Responses/PasswordResetResponse.php`
- `app/Http/Middleware/HandleInertiaRequests.php`

> **Auth UI principle:** clear feedback, consistent validation, no security leakage, and no unnecessary abstraction.