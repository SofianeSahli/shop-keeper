# Implementation Plan: Ledger Usability Dashboard

**Branch**: `002-ledger-usability-dashboard` | **Date**: 2026-10-11 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/002-ledger-usability-dashboard/spec.md`

## Summary

Reorganize the existing local Kridi ledger around a persistent five-section toolbar and a Home dashboard that summarizes current-day ticket sales and remaining debt. Reuse the existing Expo Router screens, SQLite-backed repositories, camera scanner, Redux mutation refresh signal, and translations. Add a derived dashboard summary, camera-based product lookup with active-duplicate handling, dashboard-origin action returns, inline customer/product creation that preserves ticket drafts, and a confirmation guard when toolbar navigation would discard an unsaved form.

## Technical Context

**Language/Version**: TypeScript 6, React Native 0.86, Expo SDK 57.

**Primary Dependencies**: Expo Router, `expo-camera`, `expo-sqlite`, Redux Toolkit/react-redux, NativeWind, and Vitest, all present in `shop-keeper-mobile`.

**Storage**: Existing local SQLite product, customer, ticket, ticket-line, and payment records. Dashboard values are derived from current non-voided tickets and active payments; no new persistent dashboard entity is required.

**Testing**: Vitest domain/service tests, React Native component/interaction tests where suitable, `npm run lint`, `npm run typecheck`, and an Expo web export. Validate device camera permissions and scanning on Android/iOS hardware or simulator where camera support is available.

**Target Platform**: Android and iOS, with existing Expo web support retained subject to Expo SQLite's experimental web requirements and hosting isolation headers.

**Project Type**: Expo Router mobile application in a monorepo; all feature code remains in `shop-keeper-mobile`.

**Performance Goals**: On the existing single-shop scale, dashboard totals and section navigation update directly after a saved change, without app restart; standard navigation and draft-state actions must not block the UI while waiting on avoidable work.

**Constraints**: Single-shop, single-device, local-only ledger scope; no backend, authentication, or synchronization. Preserve financial history and integer millime rules from feature 001. Keep all user-visible text localized in Arabic and French, use direction-aware layouts, provide accessible touch targets and labels, and keep style declarations in dedicated CSS resources. Manual product-code entry remains available when scanning is unavailable. Unsaved forms require confirmation before toolbar navigation discards their values.

**Scale/Scope**: Five main areas (Home, Products, Tickets, Customers, Payments), existing ledger detail/form routes, one small local catalog and ticket history. No new multi-user, shared-data, server, or analytics platform.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- PASS: Work stays within `shop-keeper-mobile`; feature 002 is the explicitly approved usability extension to the feature 001 ledger.
- PASS: The approved 1.5.0 local-only exception explicitly covers features 001 and 002. Records and pending changes remain on device; no backend, sign-in, upload, or synchronization is added.
- PASS: Navigation, dashboard states, duplicate warnings, camera denial, validation, inline creation, and unsaved-change confirmation are specified as user-facing behavior with failure paths.
- PASS: Shared toolbar, dashboard summary, confirmation prompt, and inline entry use focused reusable responsibilities rather than duplicating presentation or financial calculations.
- PASS: User-facing additions and errors are localized; the toolbar, modals, and scanner controls support accessibility and Arabic RTL/French LTR.
- PASS: No CSS, `StyleSheet`, or inline style objects are introduced in TS/TSX; styles stay in the existing style resources.
- PASS: Dashboard money and remaining-balance calculations remain in the ledger domain/service layer and use integer millimes, not duplicated UI arithmetic.
- PASS: Automated unit and interaction tests, lint, strict typecheck, web export, and native permission/scanning scenarios are part of validation.

## Research Decisions

1. Reuse `src/app/ledger/_layout.tsx` for the persistent toolbar; map detail and form routes to their parent section and preserve existing child route behavior.
2. Make the existing `/ledger` route the dashboard/Home area. Preserve the root `/` language-selection and entry screen, which links into `/ledger`.
3. Compute a presentation-ready dashboard summary from repositories in a domain service, reuse the existing mutation refresh signal, and refresh when the dashboard regains focus so totals do not depend on restarting the app.
4. Extend the existing camera scanner and product duplicate check instead of creating a second scanning or barcode-matching implementation.
5. Keep the in-progress ticket draft in its current form while inline customer/product creation is presented as an in-place dialog or sheet. Add the created customer to the selection and the created product as a one-quantity draft line only after persistence succeeds.
6. Prevent toolbar navigation when the current form is dirty; show a localized cross-platform confirmation and dispatch the pending navigation only when the owner confirms discard.

## Project Structure

### Documentation (this feature)

```text
specs/002-ledger-usability-dashboard/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── ui-navigation-and-entry-contract.md
└── tasks.md
```

### Source Code (mobile application)

```text
shop-keeper-mobile/
├── src/
│   ├── app/
│   │   ├── _layout.tsx                 # Existing provider/database startup gate
│   │   ├── index.tsx                   # Existing language-selection and ledger entry screen
│   │   └── ledger/
│   │       ├── _layout.tsx             # Existing ledger stack; hosts persistent toolbar
│   │       ├── index.tsx               # Home dashboard
│   │       └── *.tsx                   # Existing product/customer/ticket/payment routes
│   ├── features/ledger/
│   │   ├── components/                 # Shared toolbar, summary cards, inline-entry dialogs
│   │   ├── screens/                    # Existing forms plus dashboard presentation
│   │   ├── services/                   # Dashboard aggregates and existing ledger operations
│   │   ├── store/                      # Existing mutation refresh state
│   │   └── styles/                     # Existing NativeWind CSS resources
│   ├── i18n/translations/              # Arabic, French, and existing English resources
│   └── db/                             # Existing local SQLite schema/repositories
├── app.json                            # Existing camera plugin and web hosting configuration
├── package.json
└── vitest.config.ts
```

**Structure Decision**: Extend the current feature 001 Expo application and ledger module. Keep the root layout's provider and database-startup behavior and the root language-selection/entry screen. Place the persistent toolbar in the existing ledger parent layout, where it remains visible across all ledger child routes. Turn `src/app/ledger/index.tsx` into the dashboard/Home area; keep `src/app/index.tsx` as the language-selection entry route linking to it. Ledger-specific summaries, scanning, inline forms, and guard UI stay in `src/features/ledger`; persistent business data continues through existing repositories and SQLite.

## Complexity Tracking

No constitution violations remain after the user's approval to extend the feature 001 local-only exception to feature 002. No new project, framework, data store, or external API is introduced.
