# Theme Engine Contract

## Purpose

Marketplace Analytics uses a single application-level theme engine for light and dark mode. The implementation follows the architectural principles used by Sakai and the PrimeVue theming model: one theme state, one root selector, and semantic design tokens shared by PrimeVue, Tailwind CSS, and application CSS.

This document is a project contract. New UI code MUST follow it.

## 1. Single source of truth

Theme state is owned by:

- `resources/js/composables/useTheme.ts`

Pages and components MUST NOT:

- create their own dark-mode `ref`;
- directly read or write `marketplace-dark-mode`;
- directly toggle `document.documentElement.classList`;
- introduce another dark-mode selector.

The application shell consumes `useTheme()` and calls `toggleDarkMode()`.

## 2. Canonical selector

The canonical dark-mode selector is:

`.app-dark`

It is applied to the root `<html>` element.

The same selector MUST be used by:

- PrimeVue `darkModeSelector`;
- Tailwind CSS `dark` variant;
- application-level CSS selectors;
- any future theme-aware third-party integration that supports a CSS selector.

Do not introduce `.dark`, `.dark-mode`, `.theme-dark`, or page-specific selectors.

## 3. Bootstrap without FOUC

The root theme MUST be established before the application renders.

`resources/views/app.blade.php` contains the minimal synchronous bootstrap that:

1. reads the persisted preference;
2. falls back to the operating-system preference when no preference exists;
3. applies `.app-dark`;
4. sets the native `color-scheme`.

The Vue composable then initializes the same state. This duplication is intentional: Blade prevents first-paint flashing, while the composable provides the runtime API.

## 4. Persistence

The preference key is:

`marketplace-dark-mode`

Values are:

- `true` — dark;
- `false` — light.

An absent value means "follow system preference" on first load. Once the user toggles the theme, the explicit preference is persisted.

## 5. PrimeVue

PrimeVue MUST use:

`darkModeSelector: '.app-dark'`

PrimeVue semantic/design tokens are preferred over component-specific CSS overrides. The PrimeVue theming system recommends semantic tokens and custom presets before direct CSS overrides.

## 6. Tailwind CSS

Tailwind's `dark:` variant MUST resolve against the same root selector:

`@custom-variant dark (&:where(.app-dark, .app-dark *));`

Do not configure Tailwind dark mode independently from the application theme engine.

## 7. Application CSS

Prefer semantic variables backed by PrimeVue tokens:

- `--text-color`
- `--text-color-secondary`
- `--surface-ground`
- `--surface-card`
- `--surface-hover`
- `--surface-overlay`
- `--surface-border`
- `--primary-color`

Avoid hard-coded light-only values such as `#fff` for surfaces when a semantic token is available.

## 8. Page UI contract

New or modified pages MUST:

1. use PrimeVue semantic classes/tokens where possible;
2. avoid mixing `bg-white`, `text-slate-900`, `border-slate-300` with semantic theme tokens unless there is a documented reason;
3. add a deliberate `dark:` variant when a Tailwind palette utility is genuinely necessary;
4. avoid assuming that `AppLayout` is present—Auth and Account Status pages must also render correctly because theme bootstrap is global.

## 9. Validation contract

Any theme-engine change MUST pass:

```bash
./vendor/bin/sail test
./vendor/bin/pint --test
npm run typecheck
npm run build
git diff --check
```

A visual smoke test MUST cover at minimum:

- login/auth page;
- account status page;
- dashboard;
- one DataTable page;
- one form-heavy page;
- admin pages;
- Shopee integration page;
- light → dark → light toggle;
- hard refresh while dark mode is active.

## 10. Architectural rule

Do not solve theme inconsistencies by adding page-specific dark-mode patches before fixing the shared theme engine.

The implementation order is:

1. theme engine;
2. bootstrap/persistence;
3. semantic token migration;
4. page-level visual cleanup;
5. regression validation.

This contract is intentionally compatible with the Sakai/PrimeVue architecture without coupling the application to Sakai source code.


## 15. Page Header Contract

Application pages MUST use the shared `PageHeader` component for the primary page heading:

`resources/js/Components/PageHeader.vue`

The component standardizes the hierarchy:

1. optional section / eyebrow;
2. page title;
3. optional description;
4. optional right-aligned actions via the `actions` slot.

Example:

```vue
<PageHeader
    section="Operations"
    title="Orders"
    description="Ringkasan transaksi per nomor order lengkap dengan rincian biaya hingga laba bersih."
/>
```

Page headers MUST use semantic theme tokens through the shared component. Pages MUST NOT recreate the primary heading with hard-coded `text-slate-900`, `text-slate-500`, `text-white`, or equivalent light-only typography.

The shared component is the default for application, analytics, finance, product, integration, import, account, and admin pages. Auth-specific layouts may use their own composition when the application shell is intentionally absent.

Do not introduce another page-header component or local page-header typography pattern without documenting the exception here.
