# Tasks: Light and Dark Theme

**Input**: Design documents from `specs/005-dark-mode/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

**Tests**: Included where the plan asks for them: token completeness and contrast (R8), and theme preference reading. Screens are checked on device (quickstart).

**Organization**: By user story. Paths are relative to `shop-keeper-mobile/` unless they start with `specs/`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel (different files, no dependency on an unfinished task).
- **[Story]**: US1, US2, US3 from [spec.md](./spec.md).

---

## Phase 1: Setup

**Purpose**: Let the app follow the phone's scheme, and prove that per-theme variables work on the installed NativeWind before the bulk change.

- [X] T001 In `app.json`, set `"userInterfaceStyle": "automatic"` (was `"light"`); confirm with `npx expo config --type introspect` that Android picks it up through `expo-system-ui` (R4).
- [ ] T002 **Spike (gate)**: define one variable in a temporary CSS block with a dark value under `@media (prefers-color-scheme: dark)`, map one Tailwind color to it, and switch with `colorScheme.set('dark')` from a dev-only button in Expo Go. Confirm that the color changes without remount (an input keeps its text). If it fails, stop and revisit research R3/R4 before Phase 2. Remove the temporary code afterwards. **Status**: compile half done: NativeWind compiles every `--color-*` with a light and a dark value (`rootVariables`), and the native runtime resolves `rgb(var(…) / …)` from the color-scheme observable, so a switch restyles without remount. The dark values must sit under `@media (prefers-color-scheme: dark)`: a `.dark:root` block is read before NativeWind sets its `darkMode` flag and loses its dark values. The Expo Go check (an input keeps its text) is left for the device pass.

**Checkpoint**: CSS variables switch with the scheme.

---

## Phase 2: Foundational (blocks every story)

**Purpose**: All colors become per-theme variables with light values unchanged.

- [X] T003 Create `src/theme/tokens.css` with every role of [contracts/color-roles.md](./contracts/color-roles.md) as RGB channels: light values in `:root`, dark values in `@media (prefers-color-scheme: dark) { :root { … } }`. `scrim` keeps its alpha through a separate `--color-scrim-alpha` variable.
- [X] T004 In `tailwind.config.js`, map every color to `rgb(var(--color-<role>) / <alpha-value>)` (scrim: `rgb(var(--color-scrim) / var(--color-scrim-alpha))`), add the new roles (`surface-2`, `line-dashed`, `avatar-ink`, `on-fill`, `primary-fill`, `paid-fill`, `error-fill`, `warn-fill`, `chip-selected`, `on-chip-selected`, `theme-icon`, `elevation`, `viewfinder-mark`), keep `darkMode: 'class'`, and remove the file's comment line if any.
- [X] T005 In `src/global.css`, import `./theme/tokens.css` before the component style files.
- [X] T006 [P] Write `src/theme/tokens.test.ts`: parse `tokens.css`; every role has a light and a dark value; every light value equals the value the Tailwind config had before this feature (fixed list in the test), so the light theme is unchanged (FR-001).
- [X] T007 [P] Extend the no-color check (the 004 grep in `specs/004-async-feedback/tasks.md` T072, and the ESLint rule from 003 if it covers it) to also reject `rgba(` and `rgb(` in `.ts` / `.tsx` files (SC-005). **Done**: the ESLint rule now rejects hex, `rgb(` and `rgba(` in `.tsx` and in non-test `src/**/*.ts`; the inline-style rule targets `style={…}` only, so `<StatusBar style="auto" />` passes.
- [ ] T008 Run `npm run validate`, then compare light screens on device with the 003 screenshots in `README.md` (quickstart scenario 12).

**Checkpoint**: Same light app, now driven by variables. Setting the phone to dark already changes most colors.

---

## Phase 3: User Story 1 — Use the whole app in a dark theme (Priority: P1) 🎯 MVP

**Goal**: Every screen, sheet, toast and the loader is correct in dark, using the roles split by job (research R7).

**Independent Test**: Phone in dark (T001 makes the app follow it): go through quickstart scenarios 6, 7 and 8. No white surface, every text readable, viewfinder unchanged.

### Tests for User Story 1

- [X] T009 [P] [US1] Extend `src/theme/tokens.test.ts` with the dark contrast checks of research R8 (WCAG formula): `ink`/`surface` ≥ 12, `ink-2`/`surface` ≥ 7, `on-fill` on `primary-fill`, `paid-fill`, `warn-fill` ≥ 4.5, `on-fill` on `error-fill` ≥ 3, and `debt`, `paid`, `partial`, `error`, `primary` on `surface` ≥ 4.5.

### Implementation for User Story 1

- [X] T010 [US1] In `src/components/ui/ui.css` and `src/features/ledger/styles/ledger.css`: `ui-button-primary` and `ledger-primary-button` → `bg-primary-fill`; `ui-button-paid` → `bg-paid-fill`; `ui-input`, `ui-button-outline` → `bg-surface-2` where they are inputs; `ui-fab-shadow` → `shadow-elevation/30`; `ui-viewfinder-corner` → `border-viewfinder-mark`. Remove the comments in both files.
- [X] T011 [P] [US1] Fills and text on fills in `src/components/ui/`: `ActionTile.tsx`, `BusyButton.tsx`, `Fab.tsx`, `InfoBanner.tsx` (`bg-warn-strong` → `bg-warn-fill`), `SegmentedControl.tsx`, `Toast.tsx` (icon circles → `*-fill`, `text-surface` → `text-on-fill`, `shadow-ink/20` → `shadow-elevation/20`).
- [X] T012 [P] [US1] `src/components/ui/FilterChips.tsx`: selected chip → `border-chip-selected bg-chip-selected` and `text-on-chip-selected`. `src/components/ui/Avatar.tsx`: initials → `text-avatar-ink`.
- [X] T013 [P] [US1] Raised controls → `bg-surface-2`: `src/components/ui/QuantityStepper.tsx`, `src/components/ui/BlockingLoader.tsx` (loader card), `src/components/ui/SearchField.tsx`, `src/navigation/LanguageMenu.tsx` (button), `src/features/ledger/components/forms/LedgerField.tsx`, the amount box in `src/features/ledger/components/payment/PaymentForm.tsx`.
- [X] T014 [P] [US1] Remaining `bg-primary`, `bg-paid` and `text-surface` in screens and feature components: `src/app/_layout.tsx` (retry button), `src/navigation/TopBar.tsx` (logo), `src/navigation/BottomTabs.tsx`, `src/features/ledger/components/feedback/ConfirmAction.tsx`, and every other hit of `grep -rnE "bg-(primary|paid|error)\b|text-surface\b" src --include=*.tsx`. **Done**, plus a new `debt-fill` role for the destructive confirm button in `ConfirmAction.tsx` (contracts/color-roles.md), `bg-on-fill/15` in `ActionTile.tsx`, and the viewfinder texts and permission button in `BarcodeScanner.tsx` on `viewfinder-mark` / `viewfinder`.
- [X] T015 [P] [US1] Dashed cards → `border-line-dashed`: `src/features/ledger/components/customers/CustomerPickerCard.tsx` (walk-in card) and every other `border-dashed border-line-strong`.
- [X] T016 [US1] Run `grep -rnE "bg-(primary|paid|error|ink|warn-strong)\b|text-surface\b|border-surface\b|shadow-ink" src --include=*.tsx --include=*.css`: it must return nothing. Run `npm run validate`.
- [ ] T017 [US1] Device pass in dark, against the canvas screens (`MainDark`, `TicketFormDark`, `PaymentFormDark`, `CustomersDark`, `CustomerLedgerDark`, `ProductFormDark`) and the dark toasts and loader of `DarkPalette`: quickstart scenarios 6, 7, 8. Fix any role that looks wrong in `tokens.css` only, and keep T009 green.

**Checkpoint**: The app is fully usable in dark when the phone is dark.

---

## Phase 4: User Story 2 — Switch between light and dark in one tap (Priority: P1)

**Goal**: A sun/moon button next to the language button switches the whole app at once, keeping everything typed.

**Independent Test**: Quickstart scenarios 4, 5, 10 and 11 (the choice is not yet saved; that is US3).

### Implementation for User Story 2

- [X] T018 [P] [US2] Add `switchToDark` and `switchToLight` to `src/i18n/translations/fr.json`, `ar.json` and `en.json` with the wording of [contracts/theme-api.md](./contracts/theme-api.md).
- [X] T019 [US2] Create `src/theme/useThemeChoice.ts`: `{ scheme, toggle }` from NativeWind's `useColorScheme()`; `toggle()` sets the other scheme (saving comes in T027).
- [X] T020 [US2] Create `src/navigation/ThemeToggle.tsx` per the contract: 44 dp round button like the language button, `moon-outline` / `sunny` in `text-theme-icon`, `accessibilityRole="button"`, label `t.switchToDark` / `t.switchToLight`, no animation.
- [X] T021 [US2] Render `<ThemeToggle />` right after `<LanguageMenu />` in `src/navigation/TopBar.tsx`; check that the title still truncates and both buttons fit at 130% text.
- [X] T022 [US2] Make sure the status bar follows the app scheme: render `<StatusBar style="auto" />` from `expo-status-bar` once in `src/app/_layout.tsx` if it is not already rendered, so its icons turn light in dark (FR-005).
- [ ] T023 [US2] Device check: quickstart scenarios 4, 5, 10, 11 (form values, open sheet, visible toast and open keyboard all survive the switch; Arabic mirrored). Run `npm run validate`.

**Checkpoint**: One tap switches the theme for the session.

---

## Phase 5: User Story 3 — The app remembers the choice and starts like the phone (Priority: P2)

**Goal**: The choice is saved in `app_settings` like the language and applied before the launch screen hides; with no choice, the app follows the phone.

**Independent Test**: Quickstart scenarios 1, 2, 3 and 9.

### Tests for User Story 3

- [X] T024 [P] [US3] Write `src/theme/themeStorage.test.ts` on the real in-memory SQLite (`src/test/ledgerTestDb.ts`, as the language storage tests do): `'light'` and `'dark'` read back; no row and an unknown stored value read as `null`; saving twice keeps one row; an unsupported value is rejected; the `language` row is untouched.

### Implementation for User Story 3

- [X] T025 [US3] Create `src/theme/themeStorage.ts` (`getSavedThemeChoice`, `saveThemeChoice`) on the `app_settings` row `theme`, following `src/i18n/languageStorage.ts` (contracts/theme-api.md). No schema change.
- [X] T026 [US3] In `src/app/_layout.tsx`: call `SplashScreen.preventAutoHideAsync()` at module load; in the startup chain read the theme with the language (a failed theme read counts as `null`), apply `colorScheme.set(theme ?? 'system')`, then `SplashScreen.hideAsync()` once the database is ready or has failed (research R2). Remove the existing comments in the file if any. **Done**: startup reads the theme through `src/theme/applySavedThemeChoice.ts`.
- [X] T027 [US3] In `src/theme/useThemeChoice.ts`, make `toggle()` call `saveThemeChoice(next)` after setting the scheme, without blocking the switch; a failed save is ignored.
- [ ] T028 [US3] On a phone, run quickstart scenarios 1, 2, 3, 9: launch screen then the saved theme with no flash, the choice survives 10 relaunches, the phone setting is followed only before the first manual switch. Run `npm run validate`.

**Checkpoint**: Feature complete.

---

## Phase 6: Polish & Cross-Cutting Concerns

- [ ] T029 [P] Accessibility pass: `ThemeToggle` label and role in fr / ar / en with TalkBack, focus order (after the language button), 44 dp target; dark contrast of focus and pressed states (`active:bg-muted`).
- [X] T030 [P] Check the no-style rules: `grep -rnE "StyleSheet|style=\{\{|#[0-9A-Fa-f]{3,8}\b|rgba?\(" src --include=*.tsx --include=*.ts` returns nothing outside tests. Remove any comment left in the files touched by this feature (CLAUDE.md rule).
- [X] T031 [P] Document the theme in `shop-keeper-mobile/README.md` (the button, the saved choice in `app_settings`, the color roles in `src/theme/tokens.css`) and add dark screenshots next to the light ones. **Partly done**: text added; dark screenshots still to take on a device.
- [X] T032 [P] Update the root `CLAUDE.md`: 005 implemented and pending device validation; the color roles in `src/theme/tokens.css` are the only place colors live; new screens must use roles, never values.
- [X] T033 Run `npm run validate` (lint, typecheck, Vitest) and fix any failure.
- [ ] T034 Device validation of the 12 scenarios in [quickstart.md](./quickstart.md) on Android (and iOS if available), including SC-004 (10 relaunches) and the Arabic pass. This is the owner's task on a phone.

---

## Dependencies & Execution Order

### Phase dependencies

- **Setup (Phase 1)**: T001, then T002. **T002 gates Phase 2.**
- **Foundational (Phase 2)**: T003 → T004 → T005; T006 and T007 in parallel after T004; T008 last. Blocks every story.
- **US1 (Phase 3)**: after Phase 2. T010 first (shared CSS classes), then T011–T015 in parallel, then T016, T017.
- **US2 (Phase 4)**: after Phase 2. It can run alongside US1, but its device check (T023) is more meaningful after US1.
- **US3 (Phase 5)**: after US2 (T027 extends `useThemeChoice`).
- **Polish (Phase 6)**: after all stories.

### Story completion order

```text
Setup → Foundational ─┬─ US1 ─────────┬─ Polish
                      └─ US2 → US3 ───┘
```

---

## Parallel Examples

**Phase 2, after T004**: T006 and T007 together.

**US1, after T010**:
```text
T011 ui fills and on-fill     T012 chips and avatar     T013 raised controls
T014 screens and features     T015 dashed cards
```

**US2**: T018 (strings) alongside T019 (hook).

**US3**: T024 (test) alongside T025 (storage).

---

## Implementation Strategy

### MVP

1. Phase 1 (with the spike as a gate), then Phase 2: same light app on variables.
2. US1: the app is dark when the phone is dark. **Stop and validate** with quickstart scenarios 6–8 and 12.

### Incremental

3. US2: the theme button (scenarios 4, 5, 10, 11).
4. US3: saved choice and startup without flash (scenarios 1–3, 9).
5. Polish, `npm run validate`, then one PR to `main`.

## Notes

- Light must stay pixel-identical: T006 locks today's light values, and T008 checks on device.
- Only `tokens.css` holds color values. When a dark value looks wrong on device, change it there and keep the contrast test green.
- Display preferences (theme, language) move to SharedPreferences / UserDefaults in 006; keep `themeStorage` the only place that reads or writes the theme so that move stays small.
