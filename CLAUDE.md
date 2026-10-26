# Shop Keeper — guide for Claude Code

Monorepo for a Tunisian small-shop ecosystem. Today only the merchant app is being built.

- `shop-keeper-mobile/` — Expo + React Native + TypeScript + NativeWind + Redux Toolkit + expo-sqlite. Read `shop-keeper-mobile/AGENTS.md` first (Expo version rules, commands).
- `shop-keeper-backend/` — Laravel skeleton, not used yet (see its own `CLAUDE.md`).
- `specs/` — Spec Kit features: `001-kridi-credit-ledger`, `002-ledger-usability-dashboard` (done), `003-ledger-visual-redesign` (implemented; device validation pending).
- `.specify/memory/constitution.md` — governing rules. Read it before any change.

## Current focus

Feature **003 — Ledger visual redesign** (`.specify/feature.json` points to it).

1. Read `specs/003-ledger-visual-redesign/spec.md` and `design-reference.md`.
2. Resolve the merge-conflict markers in `.specify/memory/constitution.md` (keep the 1.5.0 / HEAD side) before planning.
3. Run the Spec Kit flow by following the skill files in `.github/skills/` in order: `speckit-clarify` (optional) → `speckit-plan` → `speckit-tasks` → `speckit-analyze` → `speckit-implement`. Their helper scripts are PowerShell (`.specify/scripts/powershell/`).
4. Work on a branch named `003-ledger-visual-redesign`.

Suggested implementation order: tokens + font → UI components (with tests) → shell restyle + 002 leftovers → Home → Ticket form → Payment form → Customers + Customer ledger → Product form → Ticket list + Product catalog → translations audit → validate.

## Approved designs waiting for a spec

- **Async feedback (toasts + loaders)** — `shop-keeper-mobile/docs/design/async-feedback.md`. Services will become async; the app needs one app-wide toast, one blocking loader that names the running action, busy buttons and skeletons. This is for a NEW Spec Kit feature, not 003 (closed). When the owner asks to start it, create the new feature with `speckit-specify` using this note as input.

## Non-negotiable rules (from the constitution)

- No `StyleSheet`, inline `style={{…}}` objects or hex colors in `.tsx` files. Styles live in Tailwind config / `.css` style files; components use classes.
- Every user-facing string goes in `src/i18n/translations/{fr,ar,en}.json`. Arabic is RTL: use direction-aware layout (`start`/`end`), mirror directional icons.
- Money is integer millimes (TND, 3 decimals). Never floats. Format with `src/lib/money.ts`.
- Offline-first (constitution VI, unchanged): every write goes to SQLite first with a `pending_changes` operation-log entry in the same transaction. Data syncs with the server in the background when the network is available; that sync ships with the backend (004). 003 adds no network code.
- Numbers look the same in every language, Arabic included: Latin digits, `1 284 500,500`; only the currency label (`DT` / `د.ت`) is translated.
- Common actions in 3 taps or fewer; touch targets ≥ 44 dp; accessibility labels and states on every control.
- Handle loading, empty, error and permission-denied states, not only the happy path.
- In 003, services and the SQLite schema change only where the spec allows it: read helpers, and User Story 8 (payment status chosen at sale: at-sale payment + walk-in customer), always with a migration, operation-log entries and tests.

## Commands (run from `shop-keeper-mobile/`)

```bash
npx expo install <pkg>   # never npm install for Expo packages
npx expo start
npm run validate         # lint + typecheck + vitest — must pass before a task is done
```

## Known issues to clean up

- The shell issues listed here before 003 (nested Redux `Provider`, unused `BottomTabBar` import, inline safe-area `style`) are fixed.
- Uncommitted change in `src/features/ledger/types/ticket.ts` (one blank line) — check with the owner before discarding.
- Unused Expo template packages (`expo-image`, `expo-symbols`, `@expo/ui`, `expo-glass-effect`, `expo-device`, `expo-web-browser`) are still installed. They are not imported by `src/`; remove them in a separate cleanup after checking `npx expo-doctor` (keep expo-linking, expo-constants, gesture-handler, reanimated, screens and safe-area-context: Expo Router uses them).

## Bigger picture

The product roadmap (5 phases: merchant app → customer app with opt-in price sharing → wholesalers → sponsor dashboard → dedicated instances for large retail chains) lives in the owner's "Feuille de route — Écosystème Shop Keeper" doc. Phase 1 next features after 003: WhatsApp kridi reminders + shareable receipt (local), then 004 account + encrypted backup + sync (needs a constitution amendment and the Laravel backend). Design code with that in mind: no hard-coded app name, API URL or colors outside config.

Every actor gets its own independent, standalone app (merchant, customer, wholesaler, sponsor, field agent), each a separate project in this monorepo with its own dependencies, tests and releases. Never add another actor's features as a "mode" inside `shop-keeper-mobile`; apps communicate only through the backend API and may share only a common package (UI components, translations, money formatting).
