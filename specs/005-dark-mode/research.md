# Research: Light and Dark Theme (005)

Decisions R1–R12. Each lists the decision, why, and what else was considered. Versions checked against the installed packages (Expo SDK 58, React Native 0.88, NativeWind 4.2 / react-native-css-interop, expo-splash-screen 58) and the Expo docs index (`docs.expo.dev/llms.txt`, 2026-10-29). The feature adds no dependency and no native code; the app keeps running in Expo Go.

## R1 — Where the theme choice is stored: `app_settings` in SQLite, next to the language

**Decision**: Key `theme` in the existing `app_settings` table (`key TEXT PRIMARY KEY, value TEXT`), written and read exactly like the language in `src/i18n/languageStorage.ts`, through `accessLedger`. New module `src/theme/themeStorage.ts`: `getSavedThemeChoice(): Promise<'light' | 'dark' | null>` and `saveThemeChoice(choice): Promise<void>`. No schema change, no migration.

**Rationale**: The owner chose to keep the preference in SQLite for now (2026-10-29) so the feature needs no extra native development; native preferences (SharedPreferences / UserDefaults) are deferred to 006. The language already uses this table, so the pattern, the queue (`runQueued`) and the tests exist.

**Alternatives considered**:
- A local Expo module over SharedPreferences / UserDefaults: needs Kotlin/Swift and a development build instead of Expo Go; deferred to 006 by the owner.
- `@react-native-async-storage/async-storage` or `expo-secure-store`: a new dependency for one value already served by `app_settings`.

## R2 — Startup without a flash of the wrong theme

**Decision**: Keep the native launch screen up until the saved theme is known. `src/app/_layout.tsx` calls `SplashScreen.preventAutoHideAsync()` from `expo-splash-screen` (already installed) at module load. The startup chain becomes: open the database → read the language and the theme → `colorScheme.set(theme ?? 'system')` → `SplashScreen.hideAsync()`. If the database fails, the splash is hidden as well and the startup error screen shows in the phone's theme (no saved choice could be read, FR-008).

**Rationale**: The saved choice lives in SQLite, which is opened asynchronously, so the first React frame cannot know it. Holding the launch screen covers that time (a few tens of ms after the database opens) without any native code, and also removes today's brief "Chargement…" screen on a normal start.

**Alternatives considered**: rendering the "Chargement…" screen in the phone's theme and switching when the choice arrives: visible flash for anyone whose choice differs from the phone (fails SC-004).

## R3 — How colors change: CSS variables with a light and a dark value

**Decision**: Every color role becomes a CSS variable in a new `src/theme/tokens.css`: light values in `:root`, dark values in `@media (prefers-color-scheme: dark) { :root { … } }`. Variables hold RGB channels (`--color-ink: 15 31 26;`), and `tailwind.config.js` maps each color to `rgb(var(--color-ink) / <alpha-value>)`, so modifiers like `shadow-ink/20` keep working. Screens keep their classes (`bg-surface`, `text-ink`, …).

**Rationale**: react-native-css-interop stores a light and a dark value per variable and picks one from its color-scheme observable (`appearance-observables.js`, `cssVariableObservable`), so every class follows the theme with no change in components (FR-003). Hex values stay in `.css` and the Tailwind config, which the constitution allows; `.tsx` files keep zero colors.

**Alternatives considered**: `dark:` variants on every class (doubles every color class in every component, easy to forget one, breaks FR-003); `vars()` set from JS (puts color values in TypeScript); a JS theme object (needs `style` props, forbidden).

## R4 — Switching and restoring the scheme

**Decision**: NativeWind's `colorScheme.set('light' | 'dark' | 'system')`, which calls React Native's `Appearance.setColorScheme` (`'unspecified'` for system on RN ≥ 0.82). `app.json` `userInterfaceStyle` changes from `"light"` to `"automatic"`; `expo-system-ui` is already installed, which Android needs for this setting. `darkMode: 'class'` stays in the Tailwind config, which is what lets the scheme be set manually.

**Rationale**: `Appearance.setColorScheme` also drives the native side: the status bar with `style="auto"`, native alerts and the keyboard follow the app's scheme (FR-005). With `"light"` in `app.json`, the phone setting would never be seen (FR-007).

**Startup without flash**: see R2 (the launch screen stays up until the saved choice is applied).

## R5 — Where the current theme lives in the app

**Decision**: No Redux slice. The scheme is read with NativeWind's `useColorScheme()` (`colorScheme`, `setColorScheme`), wrapped in one hook `useThemeChoice()` that returns `{ scheme, toggle }`. `toggle()` sets the opposite scheme at once, then saves it in the background (a failed save is ignored: the switch still applies for the session).

**Rationale**: The scheme is already held by React Native's Appearance and NativeWind's observable; copying it into Redux would create two sources of truth that can disagree when the phone setting changes (FR-007). The constitution asks for Redux for *shared client state*; this is platform display state, like the system font scale.

**Alternative considered**: a `themeSlice` mirroring the scheme: rejected for the reason above.

## R6 — Color roles: today's light values win where the note differs

**Decision**: The light value of every role is the value shipped today. The design note's light column is used only for new roles. Concretely:
- `surface-2` (inputs, −/+ stepper, language and theme buttons, loader card) takes today's light value `#FFFFFF`, not the note's `#F3F5F4`: all these elements are white in light today (`bg-surface`), and the note's value would turn them grey. Dark is the note's `#1E2A26`.
- Dashed "new" and walk-in cards keep `border-line-strong` in light through a role `line-dashed` whose light value is today's `#C9D3CF` (dark `#4E5F59`).

**Rationale**: FR-001 says the light theme looks exactly as today; 003 approved it on device.

## R7 — Roles split for dark

The dark palette uses different values where light uses one color for two jobs. New or split roles (light value = today, dark value = design note):

| Role | Light | Dark | Replaces |
|---|---|---|---|
| `on-fill` | `#FFFFFF` | `#FFFFFF` | `text-surface` on filled buttons, FAB, icon circles, banner buttons (26 uses) |
| `primary-fill` | `#164E9C` | `#2F6FC4` | `bg-primary` (20 uses, plus `ui-button-primary`, `ledger-primary-button`) |
| `primary` (text, border) | `#164E9C` | `#8DB8F2` | `text-primary`, `border-primary` stay as they are |
| `paid-fill` | `#0F5F57` | `#1F7A6E` | `bg-paid` (12 uses, plus `ui-button-paid`) |
| `paid` (text, border) | `#0F5F57` | `#5FD0BE` | unchanged class names |
| `error-fill` | `#A3221A` | `#D9443B` | `bg-error` (error toast icon circle) |
| `chip-selected` / `on-chip-selected` | `#0F1F1A` / `#FFFFFF` | `#E8EEEC` / `#0E1513` | `bg-ink border-ink` + `text-surface` in `FilterChips` |
| `avatar-ink` | `#4A5A54` | `#C9D6D1` | avatar initials (`text-ink-2` in `Avatar`) |
| `warn-fill` | `#713F12` | `#8A5A16` | `bg-warn-strong` (warning banner button) |
| `warn-strong` (text) | `#713F12` | `#F5D58A` | `text-warn-strong` |
| `theme-icon` | `#0F1F1A` | `#F2C46B` | moon / sun of the theme button |
| `elevation` | `#0F1F1A` | `#000000` | `shadow-ink/20`, `shadow-ink/30` (FAB, toast) |
| `viewfinder-mark` | `#FFFFFF` | `#FFFFFF` | `border-surface` on the viewfinder corners (FR-011) |

Roles with values only in light today get dark values chosen from the palette: `primary-line` → `#2B4A73` (info toast border, between `primary-soft` and `primary`), `skeleton` → `#24312C` (same as `muted`, visibly different from `surface`), `scrim` → `rgba(0,0,0,0.6)` (from the note). `viewfinder` and `scan-line` keep one value in both themes (FR-011).

**Rationale**: Splitting by job (fill vs text) keeps white text readable on dark fills (FR-009) and keeps most class names unchanged; only fills and white-on-fill text are renamed.

## R8 — Contrast is checked by a test, not by eye

**Decision**: A Vitest test parses `src/theme/tokens.css` and checks: every role has a light and a dark value; WCAG contrast in dark of `ink` on `surface` ≥ 12:1, `ink-2` on `surface` ≥ 7:1, `on-fill` on `primary-fill`, `paid-fill` and `warn-fill` ≥ 4.5:1 (they carry text), `on-fill` on `error-fill` ≥ 3:1 (it carries only the "!" icon; WCAG 1.4.11 asks 3:1 for graphics), and the meaning colors (`debt`, `paid`, `partial`, `error`, `primary`) on `surface` ≥ 4.5:1.

Measured on the design-note values (2026-10-29): `ink`/`surface` 14.0, `ink-2`/`surface` 7.5, white on `primary-fill` 5.0, `paid-fill` 5.2, `warn-fill` 5.9, `error-fill` 4.3 (icon only, passes 3:1); `debt` 8.0, `paid` 8.8, `partial` 10.1, `error` 7.2, `primary` 8.1 on `surface`. All targets pass.

**Rationale**: SC-002 is measurable from token values alone; a test keeps it true when a value is tuned later. If a design-note value fails a target, the test fails and the value is raised with the owner before it ships.

## R9 — The theme button

**Decision**: `src/navigation/ThemeToggle.tsx`, rendered right after `LanguageMenu` in `TopBar`. Class `ui-icon-button` (44 dp) on `bg-surface-2`; Ionicons `moon-outline` (light, `text-theme-icon`) or `sunny` (dark, `text-theme-icon`); `accessibilityRole="button"` and label `t.switchToDark` / `t.switchToLight`. The swap is instant (no animation), so reduce motion needs no special case.

**Rationale**: The language button already lives in `TopBar` on every screen that has a top bar (FR-004). The title already has `flex-1` and `numberOfLines={1}`, so a second 44 dp button fits at 130% text.

## R10 — Form values and position survive the switch

**Decision**: Nothing to build: the switch only changes CSS variable values; components re-render with new classes but are not remounted (no `key` change, no navigation). A test-free check in quickstart confirms it on device (FR-006).

**Risk and guard**: react-native-css-interop remounts a component when it *gains* an animation or a variable after its first render (the "upgrade" behaviour seen in 004's toast fix). Colors from variables are present from the first render in both themes, so no upgrade happens; the device check covers an open form, an open sheet and a visible toast.

## R11 — Out of scope, kept as is

Splash screen, app icon and Android navigation bar keep their current look (spec Assumptions). The root native background (`expo-system-ui` `setBackgroundColorAsync`) is not set from code, to keep color values out of TypeScript; the root view already paints `bg-ground` from the first frame.

## R12 — Shadows in dark

**Decision**: Only the FAB (`ui-fab-shadow`) and the toast keep a shadow; both switch from `shadow-ink/…` to `shadow-elevation/…`, which is black in dark. Cards rely on borders and lighter surfaces (FR-010); they have no shadow today.
