# Shop Keeper — guide for Claude Code

Monorepo for a Tunisian small-shop ecosystem. Today only the merchant app is being built.

- `shop-keeper-mobile/` — Expo + React Native + TypeScript + NativeWind + Redux Toolkit + expo-sqlite. Read `shop-keeper-mobile/AGENTS.md` first (Expo version rules, commands).
- `shop-keeper-backend/` — Laravel skeleton, not used yet (see its own `CLAUDE.md`).
- `specs/` — Spec Kit features: `001-kridi-credit-ledger`, `002-ledger-usability-dashboard` (done), `003-ledger-visual-redesign` (merged; device validation pending), `004-async-feedback` (merged; device validation pending), `005-dark-mode` (implemented on branch `005-dark-mode`; device validation pending).
- `.specify/memory/constitution.md` — governing rules. Read it before any change.

## Previous feature

Feature **004 — Async services and app-wide feedback**, merged.

1. Read `specs/004-async-feedback/spec.md` and the approved design note `shop-keeper-mobile/docs/design/async-feedback.md` (toasts, blocking loader, busy buttons, skeletons; wording, timings and colors).
2. Run the Spec Kit flow by following the skill files in `.github/skills/` in order: `speckit-clarify` (optional) → `speckit-plan` → `speckit-tasks` → `speckit-analyze` → `speckit-implement`. Their helper scripts are PowerShell (`.specify/scripts/powershell/`).
3. 003 (ledger visual redesign) is merged; its device validation (T106) is still pending.
4. 004 is implemented (tasks T001–T076 done except T075, the owner's device validation). Phase 8 (FR-015, added 2026-10-28) is done except T086 (device check): form validation never uses toasts or the loader; forms use `useFieldErrors` and `FieldErrorText` from `src/components/ui/` to focus the first field in error and outline it in red. Writes go through `useAsyncAction`, reads through `useLedgerQuery` (`src/features/feedback/`); every database call goes through `runQueued` (`src/db/queue.ts`), and code inside a transaction uses the scoped repository it receives, never the queue.

## Current focus

**005 — Light and dark theme** (`.specify/feature.json` points to it), branch `005-dark-mode`. Spec: `specs/005-dark-mode/spec.md`, from the approved design note `shop-keeper-mobile/docs/design/dark-mode.md`. Implemented; the device tasks remain (T002 device half, T008, T017, T023, T028, T029, T034, dark screenshots for T031).

- Colors live only in `src/theme/tokens.css`: each role is a CSS variable (RGB channels) with its light value in `:root` and its dark value under `@media (prefers-color-scheme: dark)` (NativeWind ignores a `.dark:root` block placed before its `darkMode` flag). `tailwind.config.js` maps each role to `rgb(var(--color-<role>) / <alpha-value>)`; `src/theme/tokens.test.ts` locks the light values and the dark contrast targets.
- New screens use roles, never values: solid fills use `*-fill` with `text-on-fill`; inputs and small controls use `bg-surface-2`; the selected chip uses `chip-selected`; the viewfinder uses `viewfinder` / `viewfinder-mark`. ESLint rejects hex, `rgb(` and `rgba(` in source files.
- The choice is saved in SQLite `app_settings` (key `theme`) by `src/theme/themeStorage.ts`, applied at startup by `applySavedThemeChoice` while the splash screen is held, and switched by `ThemeToggle` (`useThemeChoice`, NativeWind `colorScheme`). No native code; the app stays in Expo Go. Moving display preferences to SharedPreferences / UserDefaults is deferred to 006.

## Non-negotiable rules (from the constitution)

- No `StyleSheet`, inline `style={{…}}` objects or hex colors in `.tsx` files. Styles live in Tailwind config / `.css` style files; components use classes.
- Every user-facing string goes in `src/i18n/translations/{fr,ar,en}.json`. Arabic is RTL: use direction-aware layout (`start`/`end`), mirror directional icons.
- Money is integer millimes (TND, 3 decimals). Never floats. Format with `src/lib/money.ts`.
- Offline-first (constitution VI, unchanged): every write goes to SQLite first with a `pending_changes` operation-log entry in the same transaction. Data syncs with the server in the background when the network is available; that sync ships with the backend (006). 004 and 005 add no network code.
- Numbers look the same in every language, Arabic included: Latin digits, `1 284 500,500`; only the currency label (`DT` / `د.ت`) is translated.
- Common actions in 3 taps or fewer; touch targets ≥ 44 dp; accessibility labels and states on every control.
- Handle loading, empty, error and permission-denied states, not only the happy path.
- In 004, services become asynchronous but keep their rules (transactions, operation log, validation); no schema change. Screens never run their own loading logic: every write goes through the shared async-action helper.
- Code explains itself: clear names for variables, functions and components, small functions, named constants instead of magic values. Do not write comments; if code needs one, rename or extract until it does not. Remove existing comments when touching a file.

## Commands (run from `shop-keeper-mobile/`)

```bash
npx expo install <pkg>   # never npm install for Expo packages
npx expo start
npm run validate         # lint + typecheck + vitest — must pass before a task is done
```

## Git and pull requests

- Every pull request has exactly one commit. Before pushing a branch for review, squash all its commits into one (`git reset --soft $(git merge-base main HEAD)` then a single `git commit`), and after new changes amend that commit and force-push with `--force-with-lease`.
- One feature or fix per branch; the commit message summarizes the whole PR.

## Known issues to clean up

- The shell issues listed here before 003 (nested Redux `Provider`, unused `BottomTabBar` import, inline safe-area `style`) are fixed.
- Uncommitted change in `src/features/ledger/types/ticket.ts` (one blank line) — check with the owner before discarding.
- Unused Expo template packages (`expo-image`, `expo-symbols`, `@expo/ui`, `expo-glass-effect`, `expo-device`, `expo-web-browser`) are still installed. They are not imported by `src/`; remove them in a separate cleanup after checking `npx expo-doctor` (keep expo-linking, expo-constants, gesture-handler, reanimated, screens and safe-area-context: Expo Router uses them).

## Bigger picture

The product roadmap (5 phases: merchant app → customer app with opt-in price sharing → wholesalers → sponsor dashboard → dedicated instances for large retail chains) lives in the owner's "Feuille de route — Écosystème Shop Keeper" doc. Phase 1 next features after 003: WhatsApp kridi reminders + shareable receipt (local), then 006 account + encrypted backup + sync (needs a constitution amendment and the Laravel backend). 004 is async services + feedback (owner's choice, 2026-10-27); 005 is the light and dark theme (owner's choice, 2026-10-29). Design code with that in mind: no hard-coded app name, API URL or colors outside config.

Every actor gets its own independent, standalone app (merchant, customer, wholesaler, sponsor, field agent), each a separate project in this monorepo with its own dependencies, tests and releases. Never add another actor's features as a "mode" inside `shop-keeper-mobile`; apps communicate only through the backend API and may share only a common package (UI components, translations, money formatting).
