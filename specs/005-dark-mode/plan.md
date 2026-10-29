# Implementation Plan: Light and Dark Theme

**Branch**: `005-dark-mode` | **Date**: 2026-10-29 | **Spec**: [spec.md](./spec.md) | **Design**: [dark-mode.md](../../shop-keeper-mobile/docs/design/dark-mode.md)

**Input**: Feature specification from `specs/005-dark-mode/spec.md`

## Summary

The work has three parts:

1. **Colors become per-theme variables.** Every Tailwind color becomes a CSS variable with a light value (today's) and a dark value (the design note's), defined once in `src/theme/tokens.css`. Components keep their classes; a few classes are renamed where dark needs a different value for the same light color: fills versus text, white text on fills, the selected chip, avatars, inputs, dashed cards, shadows and the viewfinder corners.
2. **The choice is saved like the language.** A `theme` row in the existing `app_settings` table is read at startup, together with the language. The choice is applied with NativeWind's `colorScheme.set`, which drives React Native's `Appearance`, while the launch screen is still up, so there is no flash. With no saved choice, the app follows the phone.
3. **A theme button.** A sun/moon button sits next to the language button in the top bar, and one tap switches and saves.

No new dependency, no native code, no schema change: the app keeps running in Expo Go. Moving display preferences to SharedPreferences / UserDefaults is left to 006.

## Technical Context

**Language/Version**: TypeScript 6 (strict), React 19.3, React Native 0.88, Expo SDK 58.

**Primary Dependencies**:
- NativeWind 4.2 / react-native-css-interop: CSS variables with light and dark values, `colorScheme`, `useColorScheme`.
- expo-system-ui: already installed; Android needs it for `userInterfaceStyle`.
- expo-splash-screen: already installed; keeps the launch screen up until the saved theme is applied (research R2).
- expo-sqlite: the existing `app_settings` table, through `accessLedger` / `runQueued`.
- **No new dependency.**

**Storage**: SQLite `app_settings`, row `theme` (`light` / `dark`), next to `language`. No schema change, no migration.

**Testing**:
- Vitest for `themeStorage` on the real in-memory SQLite (`src/test/ledgerTestDb.ts`), and for `tokens.css`: completeness and contrast (research R8).
- The existing no-color grep check is extended to `rgba(`.
- Manual device scenarios in [quickstart.md](./quickstart.md), in Expo Go.

**Target Platform**: Android and iOS phones; the web build keeps working.

**Project Type**: Expo mobile app in the monorepo (`shop-keeper-mobile/`).

**Performance Goals**: The whole screen changes within 300 ms of the tap (SC-003). The saved choice is applied before the launch screen hides (SC-004).

**Constraints**:
- No color in `.ts` / `.tsx` files.
- The light theme must look exactly as today (FR-001).
- No component remounts on switch, so form values are kept (FR-006).
- Dark contrast targets: 12:1, 7:1 and 4.5:1 (FR-009).
- fr / ar / en strings, RTL.

**Scale/Scope**:
- 45 color roles: 31 existing, plus 14 new or split (`debt-fill` added during implementation for the destructive confirm button).
- About 90 class edits in about 25 component files, mostly mechanical renames of fills and white-on-fill text.
- 3 small TypeScript modules (`themeStorage`, `useThemeChoice`, the startup change), 1 component and 2 strings per language.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle / rule | Status | Notes |
|---|---|---|
| I. Monorepo boundaries | PASS | Mobile only. |
| II. Accessible, simple UI | PASS | Contrast targets are tested (R8). The button is 44 dp, labelled, with the button role. One tap. |
| II. No CSS/StyleSheet/inline style in TSX | PASS | Values live in `tokens.css` and the Tailwind config; components use classes only (R3). |
| II. Reusable components without speculative abstraction | PASS | One new component (`ThemeToggle`) and one hook; no theme framework. |
| III. i18n AR/FR, RTL; preference persisted | PASS | 2 new strings in fr / ar / en. The theme is persisted and restored, like the language (FR-008). |
| IV. Backend | N/A | No server work. |
| V. Testability | PASS | Pure tests for preference reading and token contrast; `npm run validate` gates every phase. |
| VI. Offline-first, operation log | PASS | No ledger data. The preference is a local setting like the language: no operation-log entry (FR-013). |
| Additional constraints: Expo as the platform | PASS | Only installed Expo packages; no native code. |
| Additional constraints: NativeWind for styling | PASS | Theming uses NativeWind's own variables and color scheme. |
| Additional constraints: Redux for shared client state | PASS (justified) | The scheme is owned by React Native `Appearance` / NativeWind; mirroring it in Redux would duplicate the source of truth (R5). |

**Post-design re-check**: no violation.

## Phase 0 / Phase 1 outputs

- [research.md](./research.md): decisions R1–R12.
- [data-model.md](./data-model.md): the theme choice, its states, the active scheme, color roles.
- [contracts/theme-api.md](./contracts/theme-api.md): `themeStorage`, the startup chain, `useThemeChoice`, `ThemeToggle`, strings and configuration.
- [contracts/color-roles.md](./contracts/color-roles.md): every role with its light and dark value, and the class renames.
- [quickstart.md](./quickstart.md): 12 device scenarios.

## Project Structure

### Documentation (this feature)

```text
specs/005-dark-mode/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── theme-api.md
│   └── color-roles.md
├── checklists/
│   └── requirements.md
└── tasks.md            (speckit-tasks)
```

### Source Code

```text
shop-keeper-mobile/
├── app.json                              userInterfaceStyle → "automatic"
├── tailwind.config.js                    colors → rgb(var(--color-…) / <alpha-value>)
└── src/
    ├── global.css                        imports theme/tokens.css first
    ├── theme/                            NEW
    │   ├── tokens.css                    light :root + dark media query
    │   ├── tokens.test.ts                completeness + contrast
    │   ├── themeStorage.ts               app_settings('theme'), like languageStorage
    │   ├── themeStorage.test.ts
    │   ├── applySavedThemeChoice.ts      startup: saved choice or follow the phone
    │   └── useThemeChoice.ts             { scheme, toggle }
    ├── navigation/
    │   ├── ThemeToggle.tsx               NEW
    │   └── TopBar.tsx                    renders ThemeToggle after LanguageMenu
    ├── app/_layout.tsx                   splash held until language + theme are applied
    ├── components/ui/*.tsx, ui.css       class renames (contracts/color-roles.md)
    ├── features/ledger/**/*.tsx, ledger.css   class renames
    └── i18n/translations/{fr,ar,en}.json switchToDark, switchToLight
```

**Structure Decision**: Theme code lives in `src/theme/`, which any screen can use and which no ledger code depends on. Storage follows `src/i18n/languageStorage.ts`, so 006 can move both preferences to native storage in one place.

## Implementation order

1. **Spike**, a gate: a single color as a CSS variable with a dark value, switched by `colorScheme.set` in Expo Go. This confirms R3 and R4 on the installed NativeWind before the bulk change.
2. Tokens and Tailwind config, with the light values unchanged. `npm run validate` and a visual check that light is identical.
3. Class renames (fills, on-fill, chip, avatar, inputs, dashed, shadows, viewfinder).
4. `themeStorage`, the startup chain with the held splash screen, `useThemeChoice`.
5. `ThemeToggle` in the top bar, and the strings.
6. Device scenarios, README.

## Complexity Tracking

No constitution violation to justify.
