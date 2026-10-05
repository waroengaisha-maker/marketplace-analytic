# UI Shell Contract — Sakai

## Status

**Contract:** Active  
**Reference:** Official PrimeVue Sakai Vue repository  
**Repository:** `primefaces/sakai-vue`  
**Reference date:** 2026-10-05

This document defines the application-shell contract for Marketplace Analytics.

The goal is to reproduce the **Sakai PrimeVue application-shell design language and structure** as closely as practical while keeping Marketplace Analytics routing, authorization, business logic, and Inertia architecture intact.

## 1. Authoritative Reference

The visual and structural reference is the official PrimeFaces repository:

- `primefaces/sakai-vue`
- Official repository: https://github.com/primefaces/sakai-vue
- Official demo: https://sakai.primevue.org/

The current official implementation separates the shell into:

- `AppLayout.vue`
- `AppTopbar.vue`
- `AppSidebar.vue`
- `AppMenu.vue`
- layout state/composable

The official Sakai `AppLayout.vue` places the topbar, sidebar, and main container as separate sibling regions. The shell state controls static/overlay/mobile menu modes.

## 2. Marketplace Analytics Adaptation

We do **not** copy Sakai's application code wholesale.

Marketplace Analytics keeps:

- Laravel
- Inertia.js
- Vue 3
- TypeScript
- PrimeVue
- Tailwind CSS
- existing authorization and route contracts
- existing page components

Sakai is the **UI shell reference**, not the application architecture.

## 3. Shell Structure

The canonical application shell is:

```text
Application
├── Topbar
│   ├── Menu toggle
│   ├── Application identity
│   └── Global actions / account
│
├── Sidebar
│   └── Navigation menu
│
└── Main container
    └── Page content
```

The topbar and sidebar must not be implemented as two competing page headers.

### Required behavior

- Desktop: static sidebar beside the main content.
- Collapsed desktop: sidebar may switch to the compact/icon state.
- Mobile: sidebar becomes an overlay/off-canvas menu.
- Main content must resize/reflow with the shell state.
- Sidebar and topbar must share the same visual system.
- No artificial spacer/header hack may be introduced to visually align the sidebar.

## 4. Topbar Contract

The topbar follows Sakai's application-shell pattern:

- full application width;
- fixed/sticky shell position as appropriate;
- subtle bottom border;
- menu toggle on the left;
- application identity adjacent to the menu toggle;
- global controls on the right;
- compact icon buttons;
- account interaction remains available without changing page content layout.

The topbar is the single global application header.

## 5. Sidebar Contract

The sidebar follows Sakai's visual model:

- dedicated application navigation region;
- consistent width on desktop;
- compact navigation items;
- section labels above groups;
- icon + label item structure;
- clear active route state;
- subtle hover state;
- responsive overlay behavior on mobile;
- no excessive vertical whitespace.

The sidebar must visually feel like a persistent part of the application shell, not like a floating card.

## 6. Navigation Model

Navigation remains application-owned.

For Super Admin the contract is exactly:

```text
Access Control
  Kelola Admin
  Kelola Akses User

Server
  Services

System Design
  UI Style Guide
```

Do not add unrelated business sections to Super Admin navigation without an explicit product decision.

## 7. Main Content Contract

The shell must provide a neutral content surface.

Pages own their own:

- section/domain context;
- page title;
- page description;
- business content.

Page-header rules are defined separately in:

`docs/PAGE_UI_STANDARD.md`

The Sakai shell contract does not override the page-header contract.

## 8. Visual Rules

Prefer the visual characteristics of Sakai:

- restrained borders;
- subtle surfaces;
- compact controls;
- consistent spacing;
- moderate corner radius;
- muted secondary text;
- clear primary/active state;
- PrimeIcons where appropriate;
- responsive behavior;
- dark-mode compatibility.

Avoid:

- oversized sidebar branding;
- duplicate global headers;
- arbitrary spacer components;
- heavy shadows around the entire shell;
- excessive rounded containers;
- decorative UI without a functional purpose;
- custom layout behavior that conflicts with Sakai's shell model.

## 9. Component Strategy

The implementation may use PrimeVue components, native elements, and Tailwind utilities.

However, component choice is subordinate to the shell contract.

A PrimeVue component should not be used merely because it exists if its default layout behavior conflicts with the Sakai shell model.

In particular, `SidebarSpacer`, nested sidebar headers, or equivalent layout hacks must not be used to compensate for incorrect shell structure.

## 10. Source of Truth

When there is a disagreement between an existing Marketplace Analytics shell implementation and this contract:

1. This contract defines the intended Marketplace Analytics shell.
2. The official `primefaces/sakai-vue` repository is the visual/structural reference.
3. Existing business functionality must be preserved.
4. Any intentional deviation must be documented with a UX/technical reason.

## 11. Change Control

Any future change to the global shell should be evaluated against this document before implementation.

A shell change should normally include:

- affected AppLayout/topbar/sidebar/menu code;
- responsive behavior verification;
- dark-mode verification;
- active-navigation verification;
- documentation update if the contract changes.

## Design Principle

> **Sakai shell, Marketplace Analytics behavior.**

The application should look and behave like a coherent Sakai-based admin application without becoming a copy of Sakai's demo application.
