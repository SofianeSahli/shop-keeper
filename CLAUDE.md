# Shop Keeper — guide for Claude Code

Monorepo for a Tunisian small-shop ecosystem: a management app for small independent shops (shop, products, stock, sales, customer credit, dashboard), backed by a Laravel API. Today the merchant app and the backend are being built.

- `shop-keeper-mobile/` — Expo + React Native + TypeScript + NativeWind + Redux Toolkit + expo-sqlite. Read `shop-keeper-mobile/AGENTS.md` first (Expo version rules, commands).
- `shop-keeper-backend/` — Laravel 13 REST API, phase 1 backend in progress. **Before any change in this folder, read `shop-keeper-backend/CLAUDE.md`**: it holds the backend rules (modules, actions, data, API, sync, catalog, security, tests). Mobile rules in this file do not apply there.
- `specs/` — Spec Kit features. `001` to `005` (credit ledger, dashboard, visual redesign, async feedback, dark mode) are closed (2026-10-31). The next spec is `006`.
- `.specify/memory/constitution.md` — governing rules (version 2.0.0). Read it before any change.

## Current focus

**Phase 1 backend — spec 006: authentication and shop.** Backend only; no mobile change in this spec.

- Scope: merchant account by phone number + PIN (Laravel Sanctum, one token per device, rate limited), creation and reading of the merchant's shop, versioned REST API under `/api/v1`, consistent JSON errors with stable codes, MySQL, feature tests including cross-shop isolation.
- The API must be usable from Postman: ship a Postman collection (or an OpenAPI file Postman can import) with the spec, and keep it in sync with the endpoints.
- Out of scope for 006: products and the shared catalog, stock, sales, customer credit, dashboard, sync endpoints, employees, cash shifts, Google login, any mobile screen. They come in later specs, in the roadmap order.
- Follow `shop-keeper-backend/CLAUDE.md` for every backend file, and the Spec Kit flow in `.github/skills/` (`speckit-specify` → `speckit-clarify` → `speckit-plan` → `speckit-tasks` → `speckit-analyze` → `speckit-implement`). Helper scripts are PowerShell (`.specify/scripts/powershell/`).

## Mobile app state (for later specs)

- 004: writes go through `useAsyncAction`, reads through `useLedgerQuery` (`src/features/feedback/`); every database call goes through `runQueued` (`src/db/queue.ts`); forms use `useFieldErrors` and `FieldErrorText`.
- 005: colors live only in `src/theme/tokens.css` as CSS variables mapped in `tailwind.config.js`; screens use color roles, never values; ESLint rejects hex, `rgb(` and `rgba(`. The theme choice is saved in SQLite `app_settings` (key `theme`).

## Non-negotiable mobile rules (from the constitution)

- No `StyleSheet`, inline `style={{…}}` objects or hex colors in `.tsx` files. Styles live in Tailwind config / `.css` style files; components use classes.
- Every user-facing string goes in `src/i18n/translations/{fr,ar,en}.json`. Arabic is RTL: use direction-aware layout (`start`/`end`), mirror directional icons.
- Money is integer millimes (TND, 3 decimals). Never floats. Format with `src/lib/money.ts`.
- Offline-first (constitution VI): every write goes to SQLite first with a `pending_changes` operation-log entry in the same transaction. Sync with the backend (push operations, then pull changes) comes in a later spec, after the backend endpoints exist.
- Numbers look the same in every language, Arabic included: Latin digits, `1 284 500,500`; only the currency label (`DT` / `د.ت`) is translated.
- Common actions in 3 taps or fewer; touch targets ≥ 44 dp; accessibility labels and states on every control.
- Handle loading, empty, error and permission-denied states, not only the happy path.
- Screens never run their own loading logic: every write goes through the shared async-action helper.
- Code explains itself: clear names for variables, functions and components, small functions, named constants instead of magic values. Do not write comments; if code needs one, rename or extract until it does not. Remove existing comments when touching a file.

## Mobile commands (run from `shop-keeper-mobile/`)

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

The product roadmap lives in the owner's "Feuille de route — Écosystème Shop Keeper" doc: 5 phases (merchant app → customer app and price map → wholesalers → sponsor dashboard → dedicated instances for large retail chains). Phase 1 order: 006 shop (auth + shop), then stock and prices with the shared catalog (`global_products` / `shop_products`), sales, dashboard, then employees and cash-register states, then other business types and the Play Store. All entered data is collected by the backend (constitution VIII). Design code with that in mind: no hard-coded app name, API URL or colors outside config.

Every actor gets its own independent, standalone app (merchant, customer, wholesaler, sponsor, field agent), each a separate project in this monorepo with its own dependencies, tests and releases. Never add another actor's features as a "mode" inside `shop-keeper-mobile`; apps communicate only through the backend API and may share only a common package (UI components, translations, money formatting).
