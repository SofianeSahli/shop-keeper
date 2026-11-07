# Shop Keeper merchant app — rules for Claude Code

Read `AGENTS.md` in this folder first (Expo version rules, commands), then `../.specify/memory/constitution.md`.

## Non-negotiable rules (from the constitution)

- No `StyleSheet`, inline `style={{…}}` objects or hex colors in `.tsx` files. Styles live in Tailwind config / `.css` style files; components use classes.
- Every user-facing string goes in `src/i18n/translations/{fr,ar,en}.json`. Arabic is RTL: use direction-aware layout (`start`/`end`), mirror directional icons.
- Money is integer millimes (TND, 3 decimals). Never floats. Format with `src/lib/money.ts`.
- Offline-first (constitution VI): every write goes to SQLite first with a `pending_changes` operation-log entry in the same transaction. Sync with the backend (push operations, then pull changes) comes in spec 013.
- Numbers look the same in every language, Arabic included: Latin digits, `1 284 500,500`; only the currency label (`DT` / `د.ت`) is translated.
- Common actions in 3 taps or fewer; touch targets ≥ 44 dp; accessibility labels and states on every control.
- Handle loading, empty, error and permission-denied states, not only the happy path.
- Screens never run their own loading logic: every write goes through the shared async-action helper.
- Code explains itself: clear names, small functions, named constants instead of magic values. Do not write comments; if code needs one, rename or extract until it does not. Remove existing comments when touching a file.

## Existing patterns

- 004: writes go through `useAsyncAction`, reads through `useLedgerQuery` (`src/features/feedback/`); every database call goes through `runQueued` (`src/db/queue.ts`); forms use `useFieldErrors` and `FieldErrorText`.
- 005: colors live only in `src/theme/tokens.css` as CSS variables mapped in `tailwind.config.js`; screens use color roles, never values; ESLint rejects hex, `rgb(` and `rgba(`. The theme choice is saved in SQLite `app_settings` (key `theme`).

## Commands

```bash
npx expo install <pkg>   # never npm install for Expo packages
npx expo start
npm run validate         # lint + typecheck + vitest — must pass before a task is done
```

## Known cleanup

- Unused Expo template packages (`expo-image`, `expo-symbols`, `@expo/ui`, `expo-glass-effect`, `expo-device`, `expo-web-browser`) are still installed and not imported by `src/`. Remove them in a separate cleanup after `npx expo-doctor`; keep expo-linking, expo-constants, gesture-handler, reanimated, screens and safe-area-context (Expo Router uses them).
