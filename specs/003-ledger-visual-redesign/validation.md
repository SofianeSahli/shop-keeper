# Validation record: Ledger Visual Redesign (003)

**Date**: 2026-10-25 · **Branch**: `003-ledger-visual-redesign` · **Expo SDK**: 58 (React Native 0.88, React 19.3)

## 1. Automated checks (all passing)

| Check | Command (from `shop-keeper-mobile/`) | Result |
|---|---|---|
| Lint, incl. the no-hex / no-`style=` / no-`StyleSheet` rule (SC-001) | `npm run lint` | 0 problems |
| Strict typecheck with typed routes | `npm run typecheck` | clean |
| Unit and service tests | `npm run test` | 19 files, 289 tests |
| Expo dependency health | `npx expo-doctor` | 20/20 checks |
| Android bundle | `npx expo export --platform android` | exported |
| Web bundle | `npx expo export --platform web` | exported |
| Translations: key parity fr/ar/en, same placeholders, no empty values | `src/i18n/translations.test.ts` | passing; 188 keys each, 0 unused, no hard-coded JSX text |
| Colors limited to tokens | `tailwind.config.js` replaces the default palette | a non-token color class no longer exists |

What the tests cover:

- **Sale recording (US8):** run against a real in-memory SQLite database with the app's schema (`src/test/ledgerTestDb.ts`, built on Node's `node:sqlite`). Covered: Paid, Partial and Kridi; the walk-in customer; the rollback when the payment write fails; the void cascade; every walk-in guard; one operation-log entry per write (FR-021, SC-009).
- **Read models:** debtor summaries, dashboard counts, customer ledger entries and the ticket filter.
- **Pure UI helpers:** amounts, initials, day groups, quick amounts, quantity and bidi.
- **Formatters:** numbers identical in every language (FR-022, SC-010).

## 2. Manual device validation (T106): not yet run

This machine has no Android SDK, emulator or device, so [quickstart.md](./quickstart.md) has not been run on a phone. What to run, in this order:

1. Build a development build or open Expo Go **for SDK 58** (`npx expo start`).
2. Quickstart scenarios 1–26 in French, then Arabic.
3. The cross-cutting checks: 130% font size, long values, font failure (on iOS too, see risk 2), and TalkBack / VoiceOver.

Record the results and screenshots in the PR description, then tick T106 in [tasks.md](./tasks.md).

Risks to watch during that run:

1. **Keyboard:** `KeyboardAvoidingView` with `behavior="padding"` on Android edge-to-edge. Check that the ticket form footer stays above the keyboard.
2. **Font failure on iOS:** if a Plex weight fails to load, iOS may warn about an unknown font family. The app still starts, but the system font replaces Plex only where iOS falls back by itself.
3. **Hermes Intl:** confirm Hermes on the device honours the `-u-nu-latn` locale extension (Latin digits in Arabic dates) and `fr-TN` grouping. Node does; Hermes uses the platform ICU.
4. **Dirty-form guard:** with Expo Router 58's `usePreventRemove(…, ({ repeat }))`, Android back, iOS swipe and the top-bar back should each show exactly one confirmation (quickstart 14).

## 3. Decisions taken during implementation (please review)

Each one is recorded in the spec, research or tasks file named here.

| # | Decision | Where |
|---|---|---|
| 1 | Constitution unchanged; 003 follows Principle VI with SQLite-first writes and an operation log. Server sync comes with 004. | spec FR-021, research R23 |
| 2 | Numbers use one format in **every** language, English included (`1 284 500,500`). Only the currency word changes. | spec FR-022 |
| 3 | The scanner closes after each code. Scanning the same product twice adds a second line (the 002 draft rule is unchanged). | spec FR-025, T062 |
| 4 | No destructive buttons in list rows. Void a payment from its edit screen; remove a customer or product from its form; edit or void a ticket from its detail. | spec FR-024 |
| 5 | Home is `/`; `/ledger` redirects there. | spec FR-027 |
| 6 | The walk-in customer is a reserved row `walk-in`. A walk-in ticket can't be edited to a different total: void it and record it again. | research R15, R18 |
| 7 | Bug fix: editing a payment now keeps its kind, so "Payé à la vente" is not lost on edit. | `paymentRepository.ts`, test |
| 8 | Bug fix: the payment form honours `?customerId=` (from the customer page and ticket detail). | `PaymentFormScreen.tsx` |
| 9 | The payment form's customer picker lists only customers who owe money, highest debt first. | `PaymentCustomerSheet.tsx` |
| 10 | The balances screen rows no longer carry a "record payment" button. Tap the row, then use "Encaisser" on the customer page. | `CustomerBalanceScreen.tsx` |
| 11 | Repository-backed read helpers live in `ledgerReadService.ts`; the maths stays pure in `customerBalanceService.ts`. | contracts/sale-recording.md §4 |
| 12 | 23 translation keys no longer used (001/002 leftovers) were removed. The ticket tile letter is translated (T / ف). | T102 |
| 13 | Unused Expo template packages are still installed; listed in `CLAUDE.md` for a separate cleanup. | `CLAUDE.md` |
