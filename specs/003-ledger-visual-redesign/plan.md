# Implementation Plan: Ledger Visual Redesign

**Branch**: `003-ledger-visual-redesign` | **Date**: 2026-10-24 | **Spec**: [spec.md](./spec.md) | **Design**: [design-reference.md](./design-reference.md)

**Input**: Feature specification from `specs/003-ledger-visual-redesign/spec.md`

## Summary

Apply the "Carnet Kridi" design to every ledger screen built in 001/002. To do this, add design tokens to `tailwind.config.js`, load IBM Plex Sans Arabic, and build 16 small presentation components in `src/components/ui/`. Every screen is rebuilt from these components. Their logic (amount splitting, initials, day grouping, quick amounts, filters) lives in plain `.ts` helpers covered by Vitest.

One data change comes with User Story 8. When a ticket is saved, the owner picks Paid, Partial or Kridi. A Paid or Partial sale writes an `AT_SALE` payment in the same transaction as the ticket. A sale paid in full may use a built-in walk-in customer: a reserved row with the fixed id `walk-in`, seeded by an idempotent migration. Ticket status stays derived from payments. Every write keeps the 001 audit and pending-change rules. The four open 002 shell tasks and the duplicated root `Provider` are fixed in the shell phase.

## Technical Context

**Language/Version**: TypeScript 6 (strict), React 19.3, React Native 0.88, Expo SDK 58 (upgraded from SDK 57 during implementation, 2026-10-24).

**Primary Dependencies**: Expo Router (typed routes), NativeWind 4 + Tailwind 3, Redux Toolkit, expo-sqlite, expo-camera, expo-font, `@expo/vector-icons` (Ionicons), react-native-safe-area-context. **Added**: `@expo-google-fonts/ibm-plex-sans-arabic`, installed with `npx expo install`. No other new dependency (no bottom-sheet, keyboard or testing library; see research R6, R7, R16).

**Storage**: Existing local SQLite database `shop_keeper_ledger.db`. Changes:
- One idempotent migration step seeds the walk-in customer row.
- A new payment kind value, `AT_SALE`, needs no column change.
- No new tables or columns.

**Testing**: Vitest (node environment, `src/**/*.test.ts`), using the existing mocked-database pattern from `paymentRepository.test.ts`. Pure UI helpers and the sale service are unit-tested. Rendering is checked manually (quickstart) in FR/AR at 100%/130% font scale. `npm run validate` must pass.

**Target Platform**: Android and iOS phones (390×844 reference); Expo web kept building. Tablet and dark mode out of scope.

**Project Type**: Expo Router mobile app inside the monorepo (`shop-keeper-mobile/`). Backend untouched.

**Performance Goals**: Screens render from synchronous local queries, as today. Debtor summaries are computed in one pass over tickets and payments (O(tickets + payments)), replacing the current per-ticket payment filter in `dashboardService`.

**Constraints**:
- Local-only: no network, no backend.
- Money is integer millimes.
- No `StyleSheet`, inline `style` or hex colors in `.tsx`; this is now enforced by ESLint.
- All text goes in `fr/ar/en.json`.
- RTL is mirrored.
- Touch targets ≥ 44 dp.
- Common actions take ≤ 3 taps.
- Loading, empty, error and permission states are covered on every screen.

**Scale/Scope**: 14 routes, 16 shared components, about 70 new translation keys, 1 migration step, 1 new payment kind, 3 new read helpers, 1 new sale write path.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle / rule | Status | Notes |
|---|---|---|
| I. Monorepo boundaries | PASS | Mobile only; backend untouched. |
| II. Accessible, simple UI; no CSS/StyleSheet/inline style in TS/TSX | PASS | Tokens in `tailwind.config.js`, composite classes in `.css`. Icon colors use `cssInterop` classes, not hex props (R4). Safe areas use NativeWind `*-safe` classes, which removes the inline `style` from `TopBar`/`BottomTabs` (R5). A new ESLint rule blocks hex literals, `style=` and `StyleSheet` in `.tsx` (R3). |
| II. Non-happy-path states | PASS | Every screen task lists its empty, error, permission and loading states; the shared `EmptyState`/`InfoBanner` are used. |
| III. i18n AR/FR, RTL | PASS | All keys added in fr/ar/en, guarded by a key-parity test. `formatMessage` handles `{param}` placeholders. Logical `start`/`end` classes are used, and directional icons mirror through the `Icon` wrapper. |
| IV. Backend / auth | N/A | No server work. |
| V. Testability | PASS | Helpers, read models and the sale write path all get unit tests. `npm run validate` gates each phase. |
| VI. Offline-first, operation log | PASS | The ticket, its at-sale payment and the walk-in seed each write `ledger_audit_events` + `pending_changes` inside the same transaction as the data. |
| VI. Offline-first (no local-only exception for 003) | PASS | Owner decision (2026-10-24): the constitution stays unchanged and 003 is **not** added to the 001/002 local-only exception. 003 follows Principle VI as written: every write goes to SQLite first, with its audit event and a `pending_changes` entry (client UUID, status `pending`) in the same transaction (FR-021). Background sync with the server when the network is available consumes that log; it is delivered with the backend (004). 003 adds no network code. |
| Money = integer millimes | PASS | Partial amounts are parsed by the existing `parseMillimes`; quick amounts are integer constants (10 000 / 20 000 / 50 000). |
| Kridi data private | PASS | Nothing leaves the device. The `tel:` link opens the phone dialer and sends no data. |
| ≤ 3 taps | PASS | SC-002, SC-003 and SC-007 are verified in the quickstart. |
| Merge-conflict markers | PASS | Resolved in favour of HEAD (1.5.0) on 2026-10-24. |

**Post-design re-check (after Phase 1)**: no new violations. The data model adds no table or column and stays on-device. The contracts keep the 001 validation and audit rules. No item is open: the constitution scope was settled on 2026-10-24 (see the Principle VI row).

## Phase 0 / Phase 1 outputs

- [research.md](./research.md): 23 decisions (R1–R23), no open clarifications.
- [data-model.md](./data-model.md): walk-in customer, at-sale payment, sale payment choice, read models, migration.
- [contracts/ui-components.md](./contracts/ui-components.md): props and behaviour of the 16 shared components and the `Icon` wrapper.
- [contracts/sale-recording.md](./contracts/sale-recording.md): service contract for sale recording, walk-in guards, void cascade, read helpers and error mapping.
- [quickstart.md](./quickstart.md): validation scenarios per user story and success criterion.

## Project Structure

### Documentation (this feature)

```text
specs/003-ledger-visual-redesign/
├── spec.md
├── design-reference.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── ui-components.md
│   └── sale-recording.md
├── checklists/requirements.md
└── tasks.md                 # /speckit-tasks
```

### Source Code (mobile application)

```text
shop-keeper-mobile/
├── tailwind.config.js                       # + color, radius, font-family, font-size tokens
├── eslint.config.js                         # + no hex / no style= / no StyleSheet in .tsx
├── src/
│   ├── global.css                           # drop unused web font vars
│   ├── app/
│   │   ├── _layout.tsx                      # single Provider, font loading, shell
│   │   ├── index.tsx                        # Home dashboard (no duplicate header)
│   │   └── ledger/…                         # route files unchanged; index redirects to /
│   ├── components/ui/                       # NEW shared presentation components
│   │   ├── Icon.tsx                         # cssInterop Ionicons + RTL mirroring
│   │   ├── ScreenScroll.tsx  StickyFooter.tsx  StatCard.tsx  ActionTile.tsx
│   │   ├── ListRow.tsx  Avatar.tsx  AmountText.tsx  StatusChip.tsx
│   │   ├── FilterChips.tsx  SearchField.tsx  QuantityStepper.tsx  SegmentedControl.tsx
│   │   ├── BottomSheet.tsx  Fab.tsx  InfoBanner.tsx  EmptyState.tsx  DayHeader.tsx
│   │   ├── ui.css                           # composite component classes (@apply)
│   │   └── logic/                           # pure helpers + *.test.ts
│   │       ├── initials.ts  amountParts.ts  dayGroups.ts  quickAmounts.ts  quantity.ts
│   ├── i18n/
│   │   ├── format.ts (+ test)               # formatMessage(template, params)
│   │   ├── translations.test.ts             # fr/ar/en key parity
│   │   ├── errorMessages.ts                 # + walk-in / kridi-needs-customer rules
│   │   └── translations/{fr,ar,en}.json
│   ├── navigation/                          # restyled TopBar, BottomTabs, LanguageMenu; features.ts fixes
│   ├── db/
│   │   └── migrations.ts                    # + ensureWalkInCustomer
│   └── features/ledger/
│       ├── constants.ts                     # WALK_IN_CUSTOMER_ID, QUICK_AMOUNTS_MILLIMES
│       ├── types/payment.ts                 # PaymentKind + 'AT_SALE'
│       ├── types/sale.ts                    # SalePaymentChoice
│       ├── hooks/useDirtyFormGuard.ts       # Android system back on dirty forms
│       ├── services/
│       │   ├── saleService.ts (+ test)      # validateSaleChoice, buildSaleLabel, recordSale
│       │   ├── ticketRepository.ts          # insert helpers, void cascade, walk-in guards
│       │   ├── paymentRepository.ts         # shared row writer, walk-in guards
│       │   ├── customerService.ts / ledgerRepository.ts  # exclude + lock walk-in
│       │   ├── customerBalanceService.ts (+ test)        # summariseDebtors
│       │   └── dashboardService.ts (+ test)  # counts, single-pass outstanding
│       ├── components/…                     # restyled CustomerSelect, BarcodeScanner, PaymentForm…
│       │   └── LedgerPage.tsx, utils/DialogShell.tsx    # removed (replaced by ScreenScroll / BottomSheet)
│       └── screens/*.tsx                    # rebuilt from components/ui
```

**Structure Decision**: Shared components go in `src/components/ui/`, not under the ledger feature, because the shell and future features (reminders, receipts) will reuse them. Ledger-specific composition (customer pickers, ticket lines, payment form) stays in `src/features/ledger/components/`. Business rules stay in `features/ledger/services/`.

## Implementation order

Follows `CLAUDE.md`: tokens + font → UI components (with tests) → shell + 002 leftovers → US8 data path → Home → Ticket form (with sale selector) → Payment form → Customers + Customer ledger → Product form → Ticket list + Product catalog → translations audit → validate.

US8 (P1) comes right after the ticket-form redesign (US3). US3 builds the `StickyFooter` with the total and Save, and US8 adds the sale services plus the payment-status selector into that footer. This avoids two stories editing the same layout at once.

## Complexity Tracking

| Item | Why needed | Simpler alternative rejected because |
|---|---|---|
| Sync required by Principle VI is not built in 003 | There is no backend yet; 003 keeps every write in the operation log so 004 can sync it in the background | Building a sync client without a server would be untestable dead code; the log already makes 003's data sync-ready (FR-021). |
| ESLint custom `no-restricted-syntax` rules | SC-001 asks for a lint/grep rule rejecting hex colors in `.tsx` | A grep in CI is not wired anywhere; ESLint already runs in `npm run validate`. |
