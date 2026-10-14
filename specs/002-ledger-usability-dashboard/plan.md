# Implementation Plan: Ledger Usability Dashboard

**Branch**: `002-ledger-usability-dashboard` | **Date**: 2026-10-11 | **Updated**: 2026-10-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/002-ledger-usability-dashboard/spec.md`

## Summary

Reorganize the existing local Kridi ledger around an app-wide navigation shell (a slim top bar plus a bottom tab bar for the five main sections) and a Home dashboard that summarizes current-day ticket sales and remaining debt. Reuse the existing Expo Router screens, SQLite-backed repositories, camera scanner, Redux mutation refresh signal, and translations. Add a feature-registry-driven shell with back handling, a language menu, and a hidden-on-forms tab bar; a derived dashboard summary; camera-based product lookup with active-duplicate handling; dashboard-origin action returns; inline customer/product creation that preserves ticket drafts; and a confirmation guard when any in-app navigation control would discard an unsaved form.

## Technical Context

**Language/Version**: TypeScript 6, React Native 0.86, Expo SDK 57.

**Primary Dependencies**: Expo Router, `expo-camera`, `expo-sqlite`, Redux Toolkit/react-redux, NativeWind, and Vitest, all present in `shop-keeper-mobile`. Added by this feature: `@expo/vector-icons` (with its `expo-font` peer) for tab and top bar icons, installed with `npx expo install`. `react-native-safe-area-context` is used for safe-area handling and ships with Expo Router.

**Storage**: Existing local SQLite product, customer, ticket, ticket-line, and payment records. Dashboard values are derived from current non-voided tickets and active payments; no new persistent dashboard entity is required. Navigation state (current route, history) is held by Expo Router and is not persisted.

**Testing**: Vitest domain/service tests, React Native component/interaction tests where suitable, `npm run lint`, `npm run typecheck`, and an Expo web export. Navigation tests cover active-section matching, tab bar hidden on form routes, back button shown or hidden per route, back fallback to Home when there is no history, tab selection not stacking, dirty-form confirmation, mirrored back icon in RTL, and feature switcher hidden with a single feature. Validate device camera permissions and scanning on Android/iOS hardware or simulator where camera support is available.

**Target Platform**: Android and iOS phone-sized screens, with existing Expo web support retained subject to Expo SQLite's experimental web requirements and hosting isolation headers. Tablet and web-specific navigation layouts are out of scope.

**Project Type**: Expo Router mobile application in a monorepo; all feature code remains in `shop-keeper-mobile`.

**Performance Goals**: On the existing single-shop scale, dashboard totals and section navigation update directly after a saved change, without app restart; standard navigation and draft-state actions must not block the UI while waiting on avoidable work.

**Constraints**: Single-shop, single-device, local-only ledger scope; no backend, authentication, or synchronization. Preserve financial history and integer millime rules from feature 001. Keep all user-visible text localized in Arabic and French, use direction-aware layouts (including mirrored back icon and tab order), provide accessible touch targets, labels, and selected states, and keep style declarations in dedicated CSS resources. Manual product-code entry remains available when scanning is unavailable. Unsaved forms require confirmation before the back button or any other in-app navigation control discards their values. The tab bar holds at most five items.

**Scale/Scope**: Five main areas (Home, Products, Tickets, Customers, Payments), existing ledger detail/form routes, one small local catalog and ticket history. The shell is app-wide and designed so a second feature can be added by registration, but only the ledger exists in this feature. No new multi-user, shared-data, server, or analytics platform.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- PASS: Work stays within `shop-keeper-mobile`; feature 002 is the explicitly approved usability extension to the feature 001 ledger.
- PASS: The approved 1.5.0 local-only exception explicitly covers features 001 and 002. Records and pending changes remain on device; no backend, sign-in, upload, or synchronization is added.
- PASS: Navigation, back behavior, language switching, dashboard states, duplicate warnings, camera denial, validation, inline creation, and unsaved-change confirmation are specified as user-facing behavior with failure paths.
- PASS: The shell (top bar, tab bar, language menu), dashboard summary, confirmation guard, and inline entry use focused reusable responsibilities rather than duplicating presentation or financial calculations. Feature-specific labels, icons, and routes live in a single descriptor per feature.
- PASS: User-facing additions and errors are localized; the shell, modals, and scanner controls support accessibility and Arabic RTL/French LTR.
- PASS: No CSS, `StyleSheet`, or inline style objects are introduced in TS/TSX. Safe-area spacing uses `SafeAreaView` with `edges` and NativeWind classes, and icon colors come from shared constants. Styles stay in the existing style resources.
- PASS: Dashboard money and remaining-balance calculations remain in the ledger domain/service layer and use integer millimes, not duplicated UI arithmetic.
- PASS: Automated unit and interaction tests, lint, strict typecheck, web export, and native permission/scanning scenarios are part of validation.

## Research Decisions

1. Host the shell (top bar, tab bar) in the root `src/app/_layout.tsx`, inside the existing provider and database-startup gate, so it is app-wide rather than ledger-specific. The shell is not shown on the language-selection entry route (`src/app/index.tsx`). The ledger layout (`src/app/ledger/_layout.tsx`) keeps only the ledger stack.
2. Describe each feature once in a descriptor: id, label, entry route, its tab items (route, label key, icon, active icon, path fragments for section matching), and its form-route fragments. A single registry lists the descriptors. The tab bar, the title, the back button, the feature switcher, and the tab-bar-hidden rule all read from it. The feature switcher renders only when the registry holds two or more features.
3. Use three navigation actions. Switching tabs or features uses `router.navigate`, so peers do not stack. Opening a form or detail from a list uses `router.push`, so back returns to the list. Back uses `router.back`, falling back to `router.replace` to the feature's entry route when there is no history. Dashboard quick actions open with push and return to Home after a successful save.
4. Show the tab bar on the dashboard, list screens, and non-form detail screens (customer ledger, balances, history). Hide it on form screens and ticket detail, and show the back button there. Main-area root screens show no back button.
5. Compute a presentation-ready dashboard summary from repositories in a domain service, reuse the existing mutation refresh signal, and refresh when the dashboard regains focus so totals do not depend on restarting the app.
6. Extend the existing camera scanner and product duplicate check instead of creating a second scanning or barcode-matching implementation.
7. Keep the in-progress ticket draft in its current form while inline customer/product creation is presented as an in-place dialog or sheet. Add the created customer to the selection and the created product as a one-quantity draft line only after persistence succeeds.
8. Route every in-app navigation action through one hook that checks the dirty-form flag and shows a localized cross-platform confirmation; the pending navigation runs only when the owner confirms discard. The dirty flag is read through a single selector over the existing ledger form state, so it can move to an app-level slice when more features exist. Guarding the Android system back action (for example with `usePreventRemove`) is a separate follow-up task.
9. Place a language menu in the top bar that calls the existing language setter and direction handling used by the entry screen. Form values are preserved across a switch. Direction changes may need a native layout refresh, so validate Arabic and French switching on device.

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
│   │   ├── _layout.tsx                 # Provider/database startup gate; hosts the app-wide shell
│   │   ├── index.tsx                   # Existing language-selection and ledger entry screen (no shell)
│   │   └── ledger/
│   │       ├── _layout.tsx             # Ledger stack for child routes
│   │       ├── index.tsx               # Home dashboard
│   │       └── *.tsx                   # Existing product/customer/ticket/payment routes
│   ├── navigation/                     # App-wide shell, independent of any feature
│   │   ├── features.ts                 # Feature and tab item types, registry, route matching helpers
│   │   ├── useAppNavigation.ts         # Guarded navigate, push, and back with dirty-form confirmation
│   │   ├── TopBar.tsx                  # Back button, title, language menu, feature switcher
│   │   ├── BottomTabs.tsx              # Tab bar for the active feature, hidden on form routes
│   │   └── LanguageMenu.tsx            # Compact language selector
│   ├── features/ledger/
│   │   ├── navigation.ts               # Ledger feature descriptor registered in the shell
│   │   ├── components/                 # Summary cards, inline-entry dialogs
│   │   ├── screens/                    # Existing forms plus dashboard presentation
│   │   ├── services/                   # Dashboard aggregates and existing ledger operations
│   │   ├── store/                      # Existing mutation refresh and form-dirty state
│   │   └── styles/                     # Existing NativeWind CSS resources
│   ├── i18n/translations/              # Arabic, French, and existing English resources
│   └── db/                             # Existing local SQLite schema/repositories
├── app.json                            # Existing camera plugin and web hosting configuration
├── package.json
└── vitest.config.ts
```

**Structure Decision**: Extend the current feature 001 Expo application and ledger module. Keep the root layout's provider and database-startup behavior and the root language-selection/entry screen. Place the shell in the root layout under the provider and database gate, where it stays visible across every registered feature's routes, and hide it on the entry route. The shell lives in `src/navigation` and knows nothing about the ledger; the ledger registers itself through a descriptor in `src/features/ledger/navigation.ts`. Turn `src/app/ledger/index.tsx` into the dashboard/Home area; keep `src/app/index.tsx` as the language-selection entry route linking to it. Ledger-specific summaries, scanning, inline forms, and guard UI stay in `src/features/ledger`; persistent business data continues through existing repositories and SQLite.

## Complexity Tracking

No constitution violations remain after the user's approval to extend the feature 001 local-only exception to feature 002. No new project, framework, data store, or external API is introduced. The only new dependency is `@expo/vector-icons`, a first-party Expo package used for navigation icons.