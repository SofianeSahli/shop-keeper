---
description: "Task list for 003 — Ledger Visual Redesign"
---

# Tasks: Ledger Visual Redesign

**Input**: Design documents from `specs/003-ledger-visual-redesign/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md), [design-reference.md](./design-reference.md)

**Tests**: Included. The constitution (V), CLAUDE.md ("UI components (with tests)") and the spec (read helpers and US8 "with … tests") require them. Pure helpers and services get Vitest tests (node environment, existing mocked-db pattern from `paymentRepository.test.ts`). Rendering is validated manually through [quickstart.md](./quickstart.md) (research R7).

**Paths**: All source paths are relative to the repo root. App code is under `shop-keeper-mobile/src/`. Run commands from `shop-keeper-mobile/`.

**Definition of done for every task**: `npm run validate` passes. There are no hex colors, `style=` or `StyleSheet` in `.tsx`. Every new string is in `fr.json`, `ar.json` and `en.json`. Touch targets are ≥ 44 dp with accessibility role, label and state. Empty, error and loading states are handled.

## Format: `- [ ] [ID] [P?] [Story?] Description with file path`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Governance, dependency, tokens, lint guard and i18n plumbing.

- [X] T001 Get the owner's decision on the constitution scope (research R23). **Decided 2026-10-24: no amendment.** `.specify/memory/constitution.md` stays at 1.5.0. 003 follows Principle VI: SQLite-first writes with operation-log entries, and background server sync comes with 004 (spec FR-021). The plan's Constitution Check row is marked PASS in `specs/003-ledger-visual-redesign/plan.md`.
- [X] T002 Install the font with `npx expo install @expo-google-fonts/ibm-plex-sans-arabic` (updates `shop-keeper-mobile/package.json` and `package-lock.json`).
- [X] T003 [P] Add the design tokens to `theme.extend` in `shop-keeper-mobile/tailwind.config.js`:
  - colors (all 22 names and hex values of design-reference §1, incl. `primary-faint #EEF4FC`, `debt-line #F6CFAE`, `partial-line #F3D27A`, `scrim rgba(15,31,26,0.45)`);
  - `borderRadius` `card 18`, `tile 20`, `control 14`, `control-lg 16`;
  - `fontFamily` `plex: IBMPlexSansArabic_400Regular`, `plex-medium: …_500Medium`, `plex-semibold: …_600SemiBold`, `plex-bold: …_700Bold`;
  - `fontSize` `display 30`, `display-lg 34`, `title 19`, `heading 16`, `body 15`, `label 13`, `caption 12.5`, `tab 12`;
  - `minHeight`/`minWidth` `touch 44`, `button 52`, `button-lg 56`, `row 60`.
- [X] T004 [P] Create `shop-keeper-mobile/src/components/ui/ui.css` with composite `@layer components` classes:
  - `.ui-card` (surface, `line` border, radius card, padding 16);
  - `.ui-button-primary`, `.ui-button-paid`, `.ui-button-outline` (min-h-button, radius control-lg);
  - `.ui-input` (50 high, `line-strong` border, radius control);
  - `.ui-input-selected` (1.5 px primary);
  - `.type-display`, `.type-title`, `.type-heading`, `.type-body`, `.type-label`, `.type-caption`, `.type-tab`, each pairing a size with the matching `font-plex-*` family (research R2);
  - `.ui-fab-shadow`.
  Import it from `shop-keeper-mobile/src/global.css` and remove the unused `--font-*` web variables there.
- [X] T005 [P] Rewrite `.ledger-card`, `.ledger-primary-button` and `.ledger-touch-target` in `shop-keeper-mobile/src/features/ledger/styles/ledger.css` on top of the new tokens (keep `.ledger-rtl`/`.ledger-ltr`), so not-yet-migrated screens already use the palette.
- [X] T006 [P] Add a `**/*.tsx` block to `shop-keeper-mobile/eslint.config.js` with `no-restricted-syntax` for:
  - hex color literals and template literals (`/#[0-9a-fA-F]{3,8}\b/`);
  - `JSXAttribute[name.name='style']`;
  - `ImportSpecifier[imported.name='StyleSheet']`.
  Each rule's message cites the constitution (research R3).
- [X] T007 [P] Create `formatMessage(template, params)` in `shop-keeper-mobile/src/i18n/format.ts`, which replaces `{name}` placeholders and leaves unknown ones as-is. Add tests in `shop-keeper-mobile/src/i18n/format.test.ts` (numbers, strings, missing param, repeated param).
- [X] T008 [P] Create `shop-keeper-mobile/src/i18n/translations.test.ts`, asserting that `fr`, `ar` and `en` have identical key sets and identical `{placeholder}` sets per key.
- [X] T009 Add the component-level translation keys `currencySuffix` (DT / د.ت / DT), `appMark` (K / ك / K), `today`, `yesterday`, `clearSearch`, `increaseQuantity`, `decreaseQuantity`, `payment` (reuse if present) and `closeSheet` to `shop-keeper-mobile/src/i18n/translations/fr.json`, `ar.json` and `en.json` (FR/AR text from design-reference §4).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Pure helpers and the shared UI components that every story uses.

**⚠️ CRITICAL**: No screen work starts until this phase is complete.

### Helpers and tests

- [X] T010 Move `getLocalDateKey` from `shop-keeper-mobile/src/features/ledger/services/dashboardService.ts` to `shop-keeper-mobile/src/lib/dates.ts` and re-export it from `dashboardService.ts` so existing imports keep working.
- [X] T011 [P] Create `shop-keeper-mobile/src/features/ledger/constants.ts` with `WALK_IN_CUSTOMER_ID = 'walk-in'`, `WALK_IN_INTERNAL_CODE = 'WALK_IN'`, `isWalkInCustomer(id)`, and `QUICK_AMOUNTS_MILLIMES = [10_000, 20_000, 50_000]`.
- [X] T012 [P] Implement `getInitials(label)` in `shop-keeper-mobile/src/components/ui/logic/initials.ts`: max 2 letters, first letter of the first two words, Arabic-safe, symbols ignored, empty → `?`. Test it in `initials.test.ts`.
- [X] T013 [P] Implement `splitMillimeAmount(value, language)` in `shop-keeper-mobile/src/components/ui/logic/amountParts.ts`, returning `{ sign, dinars, millimes, currency }` via `Intl.NumberFormat#formatToParts`; the currency comes from the caller (research R8). Test it in `amountParts.test.ts`: 0, 999, 1 000, 1 284 500 000, negative values, fr/ar/en, and consistency with `formatMillimeAmount` digits.
- [X] T014 [P] Implement `groupByLocalDay(items, getDate, now)` in `shop-keeper-mobile/src/components/ui/logic/dayGroups.ts`, returning `{ dayKey, label: 'today' | 'yesterday' | 'date', items }[]` newest first, using `src/lib/dates.ts`. Test it in `dayGroups.test.ts` (midnight boundary, stable order within a day).
- [X] T015 [P] Implement `getQuickAmountOptions(remaining, chips)` in `shop-keeper-mobile/src/components/ui/logic/quickAmounts.ts`. It returns a `whole` option (= remaining, enabled when remaining > 0) and one option per chip, each enabled only when `remaining ≥ min(chips)`, with applied amount `min(chip, remaining)` (research R22). Test it in `quickAmounts.test.ts`: remaining 8 000, 15 000, 0.
- [X] T016 [P] Implement `clampQuantity(next, min = 1)` in `shop-keeper-mobile/src/components/ui/logic/quantity.ts` (integers only, ≥ min, safe-integer cap). Test it in `quantity.test.ts`.

### Shared components (contract: [contracts/ui-components.md](./contracts/ui-components.md))

- [X] T017 Create `shop-keeper-mobile/src/components/ui/Icon.tsx`: Ionicons registered with `cssInterop` so `className="text-*"` sets the color, with a `directional` prop that swaps back/forward and chevron names in RTL (reads the language via `useLedgerTranslations`). It is hidden from accessibility by default.
- [X] T018 [P] Create `shop-keeper-mobile/src/components/ui/StickyFooter.tsx` (surface, top `line` border, padding 12/16/16, optional `pb-safe`).
- [X] T019 Create `shop-keeper-mobile/src/components/ui/ScreenScroll.tsx`: `bg-ground`, padding 16, gap 14, optional `onRefresh`. An optional `footer` renders `KeyboardAvoidingView` + `ScrollView` + `StickyFooter` (depends on T018; research R14).
- [X] T020 [P] Create `shop-keeper-mobile/src/components/ui/AmountText.tsx` using `splitMillimeAmount`:
  - `size` `body|heading|display|display-lg`;
  - `tone` `ink|debt|paid`;
  - `signed` and `showCurrency` props;
  - `tabular-nums` and `ledger-ltr` digits;
  - an accessibility label set to the full `formatMillimeAmount` string.
- [X] T021 [P] Create `shop-keeper-mobile/src/components/ui/Avatar.tsx` (`bg-muted` circle, `getInitials`, `md` 40 / `lg` 44, hidden from accessibility).
- [X] T022 [P] Create `shop-keeper-mobile/src/components/ui/StatusChip.tsx` for `UNPAID|PARTIALLY_PAID|PAID|PAYMENT`, with tone classes from the contract and a text label always shown from translations (`unpaid`, `partiallyPaid`, `paid`, `payment`).
- [X] T023 [P] Create `shop-keeper-mobile/src/components/ui/StatCard.tsx`: caption, `AmountText size="display"` with `numberOfLines={1}` + `adjustsFontSizeToFit`, meta line, `tone neutral|debt`, optional `onPress`, and a combined accessibility label.
- [X] T024 [P] Create `shop-keeper-mobile/src/components/ui/ActionTile.tsx`: `primary` (min-h 84, 52 icon tile, title, subtitle, directional chevron) and `secondary` (min-h 64, 40 tinted tile `primary|paid`) variants, with `accessibilityRole="button"`.
- [X] T025 [P] Create `shop-keeper-mobile/src/components/ui/ListRow.tsx`: leading slot, one-line title and meta, trailing slot + caption, optional chevron, `selected` → `bg-primary-faint`, whole row pressable at `min-h-row`.
- [X] T026 [P] Create `shop-keeper-mobile/src/components/ui/FilterChips.tsx`: generic single-select pill group in a horizontal scroll. Use `radiogroup`/`radio` roles with `selected`; the selected chip is `bg-ink text-surface`.
- [X] T027 [P] Create `shop-keeper-mobile/src/components/ui/SegmentedControl.tsx`: three equal 44-high segments with per-option tone (`paid` → `bg-paid text-surface`, `partial` → `partial-soft/partial`, `debt` → `debt-soft/debt`) and radio semantics.
- [X] T028 [P] Create `shop-keeper-mobile/src/components/ui/SearchField.tsx` (50 high, search `Icon`, 44 clear button labelled `clearSearch`, `placeholder:text-ink-2`).
- [X] T029 [P] Create `shop-keeper-mobile/src/components/ui/QuantityStepper.tsx`: 44×44 − / + buttons via `clampQuantity`, `−` disabled at min, value with `accessibilityRole="adjustable"` and increment/decrement actions, labels `decreaseQuantity`/`increaseQuantity`.
- [X] T030 [P] Create `shop-keeper-mobile/src/components/ui/BottomSheet.tsx`:
  - `Modal` (`transparent`, slide) with a `bg-scrim` backdrop that closes on press;
  - `onRequestClose` for Android back;
  - handle, title (`header` role), helper line, and scrollable content;
  - optional confirm button inside `StickyFooter` with `confirmDisabled`.
- [X] T031 [P] Create `shop-keeper-mobile/src/components/ui/Fab.tsx` (pill, + `Icon`, label, `absolute bottom-4 end-4`, `.ui-fab-shadow`).
- [X] T032 [P] Create `shop-keeper-mobile/src/components/ui/InfoBanner.tsx`: `warning|success|info` tones, icon, title, text, optional action button; `alert` role for warning.
- [X] T033 [P] Create `shop-keeper-mobile/src/components/ui/EmptyState.tsx` (icon, one sentence, optional single action).
- [X] T034 [P] Create `shop-keeper-mobile/src/components/ui/DayHeader.tsx`: uppercase `type-caption`, `today` / `yesterday` / locale weekday + date via `formatLedgerDate`, `header` role.
- [X] T035 Create the barrel `shop-keeper-mobile/src/components/ui/index.ts` exporting all components (depends on T017–T034).

**Checkpoint**: `npm run validate` passes; components are available for screens.

---

## Phase 3: User Story 1 — Consistent, readable look across the app (P1) 🎯 MVP

**Goal**: The shell, startup screens and shared feedback components use the tokens, the font and RTL mirroring. The four 002 leftovers and the root-layout issues are fixed.

**Independent Test**: Open every route in FR and AR. Only tokens are used (lint is green); arrows, chevrons and tab order are mirrored; tabs and back buttons are ≥ 44 dp; there is exactly one dirty-form prompt for system back, swipe, top-bar back and tab tap (quickstart 1, 2, 14).

- [X] T036 [US1] Rewrite `shop-keeper-mobile/src/app/_layout.tsx`:
  - one `Provider` (remove the inner one and the `expo-router/build/react-navigation/bottom-tabs` import, FR-014);
  - `useFonts` for the four Plex weights, rendering once fonts are loaded **or** errored (FR-002);
  - database loading / failure + retry states built with tokens and `type-*` classes;
  - the shell as `TopBar` / `Stack` / `BottomTabs` on `bg-ground`.
- [X] T037 [P] [US1] Fix the registry in `shop-keeper-mobile/src/navigation/features.ts`:
  - give every item route a leading slash (`/ledger/products` …);
  - make `getActiveFeature` return `null` when no item or form fragment matches (research R11, R12).
  Keep `customer-ledger` out of the form fragments (the design shows the tab bar there), and update the callers' types.
- [X] T038 [P] [US1] Add `shop-keeper-mobile/src/navigation/features.test.ts`. It asserts that each registry route maps to an existing file under `src/app/` (via `fs`), that `getActiveFeature('/+not-found')` is `null`, and the active-item matching for `/ledger/ticket-form`, `/ledger/customer-ledger` and `/`.
- [X] T039 [US1] Create `shop-keeper-mobile/src/navigation/TopBarContext.tsx`: a provider and a `useTopBarOptions({ title?, action? })` hook so a screen can set a dynamic title and one trailing icon action (used by the customer ledger and ticket detail). Mount the provider in `_layout.tsx`.
- [X] T040 [US1] Restyle `shop-keeper-mobile/src/navigation/TopBar.tsx` to design-reference "Shell":
  - 60 high with `pt-safe` (remove `useSafeAreaInsets` and `style`);
  - on root screens, the app mark (34 square `bg-primary`, `t.appMark`) + `type-title`;
  - on forms/details, a 48 back button using `Icon directional`;
  - the title from `TopBarContext` or the registry, plus the optional action;
  - render nothing when the feature is `null`.
- [X] T041 [P] [US1] Restyle `shop-keeper-mobile/src/navigation/LanguageMenu.tsx`: a 44-high pill (globe icon, `FR` / `عربي`, chevron) that opens a `BottomSheet` listing the languages with `selected` state. No hex colors.
- [X] T042 [P] [US1] Restyle `shop-keeper-mobile/src/navigation/BottomTabs.tsx`:
  - `pb-safe` (remove `style` and the hex constants);
  - the active tab gets a 56×30 `bg-primary-soft` pill around the `Icon` and a `text-primary` 700 label; inactive tabs are `text-ink-2`;
  - `type-tab`;
  - render nothing when the feature is `null`.
- [X] T043 [US1] Create `shop-keeper-mobile/src/features/ledger/hooks/useDirtyFormGuard.ts`: `usePreventRemove(isDirty, …)` from `expo-router` with the localized `Alert` confirm, then clear the dirty flag and call `repeat()` (Expo Router 58 API). In `shop-keeper-mobile/src/navigation/useAppNavigation.ts`, make `goBack` stop prompting itself and make `switchTo`/`pushTo` dispatch `clearFormDirty` after confirming, so only one prompt appears (research R13, FR-013).
- [X] T044 [US1] Call `useDirtyFormGuard()` in `TicketFormScreen.tsx`, `PaymentFormScreen.tsx`, `ProductFormScreen.tsx` and `components/customers/CustomerForm.tsx` under `shop-keeper-mobile/src/features/ledger/`.
- [X] T045 [P] [US1] Restyle `shop-keeper-mobile/src/features/ledger/components/feedback/ConfirmAction.tsx` (outline/debt button tokens, ≥ 44 dp, `Alert` texts unchanged) and make `FeedbackState.tsx` delegate to `EmptyState` / `InfoBanner` so existing screens pick up the new look.
- [X] T046 [P] [US1] Restyle `shop-keeper-mobile/src/features/ledger/components/forms/LedgerField.tsx` and `MillimeInput.tsx` with `.ui-input`, `type-label` labels and `placeholder:text-ink-2`. Add an optional `suffix` prop (shows `t.currencySuffix`) to `MillimeInput`.
- [X] T047 [P] [US1] Rebuild `shop-keeper-mobile/src/features/ledger/screens/PaymentHistoryScreen.tsx` with `ScreenScroll`, `DayHeader` groups, `ListRow`, `AmountText signed tone="paid"` and `EmptyState`. Show the walk-in label via `walkInCustomer`.
- [X] T048 [P] [US1] Rebuild `shop-keeper-mobile/src/features/ledger/screens/CustomerBalanceScreen.tsx` and `LedgerHistoryScreen.tsx` with `ScreenScroll`, `ListRow`, `AmountText` and `EmptyState`.
- [X] T049 [P] [US1] Restyle `shop-keeper-mobile/src/features/ledger/components/customers/CustomerForm.tsx` with `ScreenScroll` + `StickyFooter` (Save), `.ui-input` fields, and a remove action behind `ConfirmAction`.
- [X] T050 [US1] Add the shell translation keys (language pill labels `languageFr`/`languageAr`, `menu` reuse, `appMark` already added) to `shop-keeper-mobile/src/i18n/translations/{fr,ar,en}.json`.
- [X] T051 [P] [US1] Delete `shop-keeper-mobile/src/constants/theme.ts`, `src/hooks/use-theme.ts` and `src/hooks/use-color-scheme*.ts` if `grep` shows no import (dark mode is out of scope).

**Checkpoint**: The shell matches the design in FR/AR, lint is green, and the 002 leftovers are closed.

---

## Phase 4: User Story 2 — Dashboard that answers "how is my day" (P1)

**Goal**: Home shows the date, the saved-on-phone pill, two stat cards with counts, the primary and secondary actions, and the top-3 debtors.

**Independent Test**: With seeded data, the stat cards equal `getDashboardSummary`, the top-3 match the highest balances, each action opens in one tap, and the kridi card opens Customers on "Owes" (quickstart 10, 11).

### Tests for User Story 2

- [X] T052 [P] [US2] Add `shop-keeper-mobile/src/features/ledger/services/customerBalanceService.test.ts` covering `summariseDebtors`:
  - balance, `openTicketCount`, `oldestOpenTicketAt` and `lastPaymentAt`;
  - sort by balance descending, then label;
  - voided tickets and payments ignored;
  - walk-in excluded;
  - `getCustomerFilterCounts` `{ owes, upToDate, all }`.
- [X] T053 [P] [US2] Add `shop-keeper-mobile/src/features/ledger/services/dashboardService.test.ts` (mock the repositories) covering `todaySalesMillimes` and `todayTicketCount` (local-day boundary, all statuses, voided excluded) and `outstandingBalanceMillimes` and `openTicketCount`.

### Implementation for User Story 2

- [X] T054 [US2] Implement `DebtorSummary`, `summariseDebtors`, `getCustomerFilterCounts`, `listDebtorSummaries` and `listTopDebtors(limit = 3)` (balance > 0 only) in `shop-keeper-mobile/src/features/ledger/services/customerBalanceService.ts` (one pass; walk-in excluded via `isWalkInCustomer`). Keep the existing exports unchanged.
- [X] T055 [US2] Extend `DashboardSummary` with `todayTicketCount` and `openTicketCount` in `shop-keeper-mobile/src/features/ledger/services/dashboardService.ts`, and compute the outstanding balance in one pass (payments summed into a `Map` by ticket).
- [X] T056 [US2] Create `shop-keeper-mobile/src/features/ledger/screens/HomeScreen.tsx`:
  - a date row (`type-label`, weekday + date) next to the `savedOnPhone` pill (`paid-soft`, phone-check icon);
  - two `StatCard`s. Sales today: amount + `ticketsCount`. Kridi to collect: `tone="debt"` + `openTicketsCount`, which navigates to `/ledger/customers?filter=owes`;
  - a primary `ActionTile` (`newTicket` / `newTicketHint`) to `/ledger/ticket-form`;
  - secondary tiles `collect` (paid tint, cash icon) to `/ledger/payment-form` and `products` (cube icon) to `/ledger/product-form`;
  - a `topDebtors` card with a `seeAll` link and up to 3 `ListRow`s (Avatar, label, `ticketsCount · sinceDate`, `AmountText tone="debt"`), each opening `/ledger/customer-ledger?customerId=…`;
  - an `EmptyState` when nobody owes;
  - data reloaded on focus (`useFocusEffect`) and on `refreshVersion`, with a load-error `InfoBanner` + retry.
- [X] T057 [US2] Make `shop-keeper-mobile/src/app/index.tsx` render only `<HomeScreen />` (remove the duplicate header and `SafeAreaView`), and make `shop-keeper-mobile/src/app/ledger/index.tsx` a `<Redirect href="/" />`.
- [X] T058 [US2] Add the keys `savedOnPhone`, `kridiToCollect`, `ticketsCount`, `openTicketsCount`, `newTicketHint`, `collect`, `topDebtors`, `seeAll`, `sinceDate` and `nobodyOwes` (positive empty state) to `shop-keeper-mobile/src/i18n/translations/{fr,ar,en}.json`.

**Checkpoint**: Home is complete and matches the services.

---

## Phase 5: User Story 3 — Faster ticket entry (P1)

**Goal**: A pinned customer card with balance, inline "Nouveau", Scan/Search buttons, stepper lines, and the total + save fixed in the footer.

**Independent Test**: Create a ticket with 3 products, use the steppers, and add a customer and a product inline. Totals, draft preservation and save match 002 (quickstart 12–14).

- [X] T059 [P] [US3] Create `shop-keeper-mobile/src/features/ledger/components/customers/CustomerPickerCard.tsx`:
  - label `customer`;
  - the selected state is a `.ui-input-selected` card with Avatar, label and `alreadyOwes` (`AmountText tone="debt"`, from `listDebtorSummaries`), opening a `BottomSheet` with `SearchField` + customer `ListRow`s (walk-in excluded);
  - a 56-wide dashed `newCustomer` button (person-add) that opens the existing inline customer creation in a `BottomSheet`;
  - an `error` prop draws a `debt` border + message;
  - a `walkInAllowed` prop renders the dashed "Client de passage / Choisir un client" card when nothing is selected.
  It replaces `CustomerSelect.tsx` (keep its creation logic).
- [X] T060 [P] [US3] Create `shop-keeper-mobile/src/features/ledger/components/ticket/ProductSearchSheet.tsx`: a `BottomSheet` with `SearchField` (`productSearchPlaceholder`) and product `ListRow`s (cube tile, name, code or `noCode`, price) that calls `onSelect`. It replaces `ProductPicker.tsx`.
- [X] T061 [P] [US3] Create `shop-keeper-mobile/src/features/ledger/components/ticket/TicketLineRow.tsx`: name, `unit DT × qty = total` via `AmountText`, a `QuantityStepper` (min 1), and a 44 remove icon button labelled `removeLine`. It replaces `TicketLineEditor.tsx`.
- [X] T062 [US3] Update `shop-keeper-mobile/src/features/ledger/components/ticket/BarcodeScanner.tsx` to support a `mode: 'sheet' | 'inline'` prop.
  - In `sheet` mode, the 64-high `scan` button opens a camera `BottomSheet` that closes after each accepted code (analysis U3: keeps SC-007 at New ticket → Scan → Scan → Encaisser), with permission-denied and unavailable states and the `allowCamera` button.
  - Use token classes only. Keep the existing `cssInterop(CameraView)`.
- [X] T063 [US3] Recompose `shop-keeper-mobile/src/features/ledger/screens/TicketFormScreen.tsx` with `ScreenScroll` + `footer`:
  - `CustomerPickerCard`;
  - two equal 64-high buttons, `scan` (primary) and `searchAction` (outline);
  - an "Articles (n)" `.ui-card` of `TicketLineRow`s whose last row is `missingProduct`, opening the existing inline product flow in a `BottomSheet`;
  - the unknown-barcode quick-create shown as an `InfoBanner` with its fields;
  - the `draftKept` helper line;
  - the footer with `ticketTotal` + `AmountText size="display"` and the Save button;
  - error states via `InfoBanner`.
  All 002 draft-preservation rules are unchanged.
- [X] T064 [US3] Add the keys `alreadyOwes`, `scan`, `searchAction`, `missingProduct`, `draftKept`, `ticketTotal`, `customer`, `articlesCount`, `productSearchPlaceholder` and `noCode` to `shop-keeper-mobile/src/i18n/translations/{fr,ar,en}.json`.

**Checkpoint**: The ticket form is redesigned. It still saves tickets as UNPAID until Phase 6.

---

## Phase 6: User Story 8 — Every sale is a ticket (P1)

**Goal**: Choose Payé / Partiel / Kridi at save, with walk-in sales and at-sale payments written in one transaction (contract: [contracts/sale-recording.md](./contracts/sale-recording.md)).

**Independent Test**: Save one ticket of each status (paid without a customer, partial with a customer, unpaid with a customer), then check the payments, statuses, balances and dashboard (quickstart 3–9).

**Depends on**: T001 (constitution decision, done: no amendment, FR-021 applies).

### Tests for User Story 8 (write first, see them fail)

- [X] T065 [P] [US8] Add `shop-keeper-mobile/src/features/ledger/services/saleService.test.ts` for the pure helpers:
  - `validateSaleChoice`: no customer or walk-in with PARTIAL/UNPAID → `kridiNeedsCustomer`; partial 0, null, negative, non-integer or above total → `partialAmountInvalid`; partial = total → `partialEqualsTotal`; PAID with no customer → `null`.
  - `getSalePaymentAmount` and `getSaleSaveLabel` for each choice.
- [X] T066 [P] [US8] Add `recordSale` tests to `saleService.test.ts` (mocked db, as in `paymentRepository.test.ts`):
  - one transaction;
  - write order ticket → audit → pending → payment → audit → pending;
  - `AT_SALE` kind with `appliedAt === ticket.createdAt`;
  - no payment for UNPAID;
  - walk-in only with PAID;
  - a failure on the payment insert leaves no ticket (rollback).
- [X] T067 [P] [US8] Add `shop-keeper-mobile/src/db/migrations.test.ts` covering `ensureWalkInCustomer`: it inserts the row with id `walk-in` and code `WALK_IN` plus one pending `customer/create`; a second run inserts nothing and logs nothing.
- [X] T068 [P] [US8] Extend `shop-keeper-mobile/src/features/ledger/services/paymentRepository.test.ts` and `crudServices.test.ts` with the walk-in guards:
  - `createPayment` on a walk-in ticket rejects;
  - `updatePayment`/`voidPayment` of a walk-in `AT_SALE` payment rejects;
  - `updateTicket` of a walk-in ticket with total ≠ paid rejects;
  - reassignment to walk-in rejects;
  - `updateCustomer`/`removeCustomer` of walk-in rejects;
  - `listCustomers` excludes walk-in unless `includeWalkIn`.
  The existing assertions stay unchanged. *(Done in `ticketRepository.test.ts` against a real in-memory SQLite database (`src/test/ledgerTestDb.ts`), which also proves rollbacks.)*
- [X] T069 [P] [US8] Add `voidTicket` cascade tests (in a new `shop-keeper-mobile/src/features/ledger/services/ticketRepository.test.ts`): active `AT_SALE` payments are voided with an audit `void` + pending `payment/soft_delete` each, in the same transaction; `TICKET` payments are untouched.
- [X] T070 [P] [US8] Add `shop-keeper-mobile/src/i18n/errorMessages.test.ts`, checking that the walk-in and kridi-needs-customer messages map to `walkInLocked`, `kridiNeedsCustomer` and `partialAmountInvalid` before the generic ticket and amount rules.

### Implementation for User Story 8

- [X] T071 [P] [US8] Add `'AT_SALE'` to `PaymentKind` in `shop-keeper-mobile/src/features/ledger/types/payment.ts`. Create `shop-keeper-mobile/src/features/ledger/types/sale.ts` with `SalePaymentChoice = { kind: 'PAID' } | { kind: 'PARTIAL'; amountMillimes: number | null } | { kind: 'UNPAID' }`.
- [X] T072 [US8] Implement `ensureWalkInCustomer(db)` in `shop-keeper-mobile/src/db/migrations.ts`:
  - `INSERT OR IGNORE INTO customers` with id `'walk-in'`, `internal_code 'WALK_IN'`, `display_label 'walk-in'`, `is_active 1`, and ISO timestamps;
  - when changes = 1, persist the pending `customer/create` in the same transaction;
  - call it last in `migrateLedgerDatabase`.
- [X] T073 [US8] Refactor `shop-keeper-mobile/src/features/ledger/services/ticketRepository.ts` without changing behaviour: extract `insertTicketRecords(ticket, db)` (customer check, `saveTicket`, audit, pending) and have `createTicket` wrap it in `transaction`. Existing tests stay green.
- [X] T074 [US8] Refactor `shop-keeper-mobile/src/features/ledger/services/paymentRepository.ts` without changing behaviour: export a module-level `insertPaymentRecords(payment, db)` (row, audit, pending) used by `createPayment`.
- [X] T075 [US8] Implement `validateSaleChoice`, `getSalePaymentAmount`, `getSaleSaveLabel` and `recordSale(ticket, choice)` in `shop-keeper-mobile/src/features/ledger/services/saleService.ts`. `recordSale` runs one `withLedgerTransaction` that calls `insertTicketRecords`, then `insertPaymentRecords` with `paymentKind 'AT_SALE'` and `appliedAt = ticket.createdAt` when the amount is above 0. A partial amount must be a "positive integer number of millimes strictly lower than the ticket total" (FR-018).
- [X] T076 [US8] Add the walk-in guards and the void cascade (research R18, R19):
  - in `ticketRepository.ts`: `createTicket` rejects walk-in; `updateTicket` keeps a walk-in ticket's total equal to the amount paid and rejects reassignment to walk-in; `voidTicket` voids the active `AT_SALE` payments through the shared payment writer, with audit and pending entries;
  - in `paymentRepository.ts`: reject payments on walk-in tickets, and reject updating or voiding a walk-in `AT_SALE` payment.
- [X] T077 [US8] Lock and exclude the walk-in customer in `shop-keeper-mobile/src/features/ledger/services/customerService.ts` (`updateCustomer`/`removeCustomer` reject; `listCustomers` gets an `{ includeWalkIn = false }` option) and `ledgerRepository.ts` (an `id <> ?` predicate).
- [X] T078 [US8] Add the error rules for `walkInLocked`, `kridiNeedsCustomer` and `partialAmountInvalid` in `shop-keeper-mobile/src/i18n/errorMessages.ts`, placed before the generic rules.
- [X] T079 [P] [US8] Create `shop-keeper-mobile/src/features/ledger/components/customers/customerLabel.ts` with `getCustomerLabel(customer | id, t)`, which returns `t.walkInCustomer` for walk-in. Use it in the payment history (T047), ticket list and ticket detail. *(Done early in Phase 3 to resolve analysis O1; the payment history uses it.)*
- [X] T080 [US8] Add the sale selector to the `TicketFormScreen.tsx` footer (new tickets only; hidden on edit, where the derived `StatusChip` is shown instead):
  - a `SegmentedControl` (`paymentStatus`: `statusPaidShort` / `statusPartialShort` / `statusKridiShort`), default Payé;
  - with Partiel, a `MillimeInput suffix` (`amountReceived`) and the `remainingToKridi` line in `debt`;
  - the Save button label from `getSaleSaveLabel` + `formatMessage`, with the `paid` button for Payé/Partiel and `primary` for Kridi;
  - `CustomerPickerCard walkInAllowed` while Payé is selected;
  - validation errors shown live (customer card `error`, message under the amount);
  - save calls `recordSale(buildTicketFromDraft(customerId || WALK_IN_CUSTOMER_ID, items), choice)`;
  - the draft is kept on failure.
- [X] T081 [US8] Add the keys `walkInCustomer`, `paymentStatus`, `statusPaidShort`, `statusPartialShort`, `statusKridiShort`, `collectAmount`, `collectWithRemainder`, `putOnKridi`, `remainingToKridi`, `kridiNeedsCustomer`, `partialAmountInvalid`, `partialEqualsTotal`, `paidAtSale`, `walkInLocked` and `voidTicketWithSalePayment` to `shop-keeper-mobile/src/i18n/translations/{fr,ar,en}.json`.

**Checkpoint**: Every sale is recorded as a ticket. The dashboard sales figure is the real daily total (SC-008).

---

## Phase 7: User Story 4 — Collect a payment in seconds (P1)

**Goal**: Customer and ticket cards, a ticket bottom sheet, a large amount field, quick chips, a live remaining line, and Save in the footer.

**Independent Test**: Pay a partial ticket with each chip and with a typed amount. Chips clamp, and the 001 validation still blocks zero or excessive amounts (quickstart 15–18).

- [X] T082 [P] [US4] Replace `TicketSelectionDialog.tsx` with `shop-keeper-mobile/src/features/ledger/components/payment/PaymentTicketSheet.tsx`: a `BottomSheet` (`selectTicket` / `oldestSelected`) of radio `ListRow`s (date, `StatusChip` · total, remaining in `debt`) with the current choice marked and a `validate` confirm.
- [X] T083 [P] [US4] Replace `CustomerSelectionDialog.tsx` with a customer `BottomSheet` built from the `CustomerPickerCard` list (walk-in excluded, only customers with open tickets first). It opens automatically when the form starts without a customer (SC-003: Collect → customer → Save).
- [X] T084 [US4] Rebuild `shop-keeper-mobile/src/features/ledger/components/payment/PaymentForm.tsx`:
  - a customer card (Avatar, label, "Doit en tout X DT · n tickets" in `debt`, `change`);
  - a `ticketToPay` card (receipt icon, ticket date, "Reste X DT sur Y DT", `change`);
  - a 72-high amount field (`type-display-lg`, DT suffix);
  - quick chips from `getQuickAmountOptions(remaining, QUICK_AMOUNTS_MILLIMES)`, with "Whole ticket" prefilled on open;
  - the remaining line (`debt-soft` `remainingAfter` X, or `paid-soft` `ticketFullyPaid`);
  - an `EmptyState` when the customer has no unpaid ticket.
- [X] T085 [US4] Update `shop-keeper-mobile/src/features/ledger/screens/PaymentFormScreen.tsx`:
  - use `ScreenScroll footer` with a `paid` button "Enregistrer X DT";
  - preselect the oldest unpaid ticket and the whole amount;
  - load customers without walk-in;
  - keep `paymentRepository` validation and the error mapping;
  - in edit mode, block a walk-in `AT_SALE` payment with `walkInLocked`.
- [X] T086 [US4] Add the keys `ticketToPay`, `change`, `amountReceived`, `wholeTicket`, `remainingAfter`, `ticketFullyPaid`, `oldestSelected`, `validate`, `owesInTotal`, `ticketOfDate` and `remainingOf` to `shop-keeper-mobile/src/i18n/translations/{fr,ar,en}.json`.

**Checkpoint**: Collecting a payment takes 3 taps from Home.

---

## Phase 8: User Story 5 — Find who owes what (P2)

**Goal**: A searchable and filterable customer list sorted by debt, and a customer ledger with a balance card, a call button and day-grouped history.

**Independent Test**: With mixed balances, check the order, chip counts, filters, search, conditional call button and day grouping (quickstart 19, 20).

- [X] T087 [P] [US5] Add tests for `getCustomerLedgerEntries(customerId)` to `shop-keeper-mobile/src/features/ledger/services/customerBalanceService.test.ts`: tickets with remaining, payments with `ticketCreatedAt` and `paymentKind`, voided entries excluded, newest first.
- [X] T088 [US5] Implement `getCustomerLedgerEntries(customerId)` in `shop-keeper-mobile/src/features/ledger/services/customerBalanceService.ts`, returning `{ type: 'ticket', ticket, remaining } | { type: 'payment', payment, ticketCreatedAt }`.
- [X] T089 [US5] Rebuild `shop-keeper-mobile/src/features/ledger/screens/CustomerListScreen.tsx`:
  - a `SearchField` (`customerSearchPlaceholder`);
  - `FilterChips` `filterOwes`/`filterUpToDate`/`filterAll` with counts from `getCustomerFilterCounts`, defaulting to Owes or to the `filter` route param;
  - the `sortedByDebt` caption;
  - one `.ui-card` of `ListRow`s (Avatar, label, meta `openTicketsCount · n j` or `allPaidOn`, `AmountText tone="debt"` + `owedSuffix` or `upToDate` in `paid`) that open the customer ledger;
  - a `Fab` "+ Client" to `/ledger/customer-form`;
  - an `EmptyState` per filter and search;
  - the edit action moved to the ledger top bar.
- [X] T090 [US5] Rebuild `shop-keeper-mobile/src/features/ledger/screens/CustomerLedgerScreen.tsx`:
  - `useTopBarOptions({ title: label, action: edit pencil })`;
  - a balance card (`remainingToPay`, `AmountText display tone="debt"`, `openTicketsCount · oldestOpen`);
  - a 48 `paid-soft` call button (`Linking.openURL('tel:…')`, label `call`) shown only when a phone exists;
  - a `Ticket` button (outline, to the ticket form with the customer) and a `collect` button (`paid`, to the payment form with the customer);
  - a `history` list grouped with `DayHeader` (ticket rows: "T" tile, `ticketItemsCount`, `StatusChip`, total + `remaining`; payment rows: "−" tile, `paidCash` or `paidAtSale`, `StatusChip PAYMENT`, signed paid amount, `onTicketOf`);
  - empty and load-error states.
- [X] T091 [US5] Make `shop-keeper-mobile/src/features/ledger/screens/TicketFormScreen.tsx` accept a `customerId` route param to preselect the customer (used by the ledger "Ticket" button).
- [X] T092 [US5] Add the keys `customerSearchPlaceholder`, `filterOwes`, `filterUpToDate`, `filterAll`, `sortedByDebt`, `upToDate`, `owedSuffix`, `allPaidOn`, `daysAgo`, `call`, `oldestOpen`, `remainingToPay`, `paidCash`, `onTicketOf` and `ticketItemsCount` to `shop-keeper-mobile/src/i18n/translations/{fr,ar,en}.json`.

**Checkpoint**: Owners can find who owes the most and drill into their history.

---

## Phase 9: User Story 6 — Product entry with inline scanning (P2)

**Goal**: An inline viewfinder, a code field with re-scan, duplicate / new-code / permission states, and Save disabled while a duplicate is shown.

**Independent Test**: Scan or type an existing code, a new code, and deny the camera. The three states appear and manual entry always works (quickstart 21).

- [X] T093 [US6] Implement `BarcodeScanner` `mode="inline"` in `shop-keeper-mobile/src/features/ledger/components/ticket/BarcodeScanner.tsx`:
  - a 200-high, radius-20 dark viewfinder with four corner marks and an amber scan line, using `.ui-viewfinder*` classes added to `src/components/ui/ui.css`;
  - the `aimAtCode` caption;
  - permission denied or unavailable → the same block with a message and an `allowCamera` button;
  - `onScanned` fills the code field.
- [X] T094 [US6] Rebuild `shop-keeper-mobile/src/features/ledger/screens/ProductFormScreen.tsx`:
  - the inline scanner at the top;
  - a `barcodeOptional` field + 52-square re-scan button;
  - duplicate → `InfoBanner warning` (`codeExists`, "name · price", action `openThisProduct`), with Save disabled (`accessibilityState.disabled`);
  - a new code → the `newCodeReady` success line;
  - the `noCameraHint`;
  - a `productName` field;
  - `MillimeInput suffix` with the `millimesHint`;
  - `StickyFooter` with `saveProduct`;
  - in edit mode, a remove action behind `ConfirmAction` (moved from the catalog).
- [X] T095 [US6] Add the keys `aimAtCode`, `codeExists`, `openThisProduct`, `newCodeReady`, `noCameraHint`, `millimesHint`, `allowCamera`, `barcodeOptional` and `saveProduct` to `shop-keeper-mobile/src/i18n/translations/{fr,ar,en}.json`.

**Checkpoint**: The product form matches the design. All 002 duplicate behaviour is preserved.

---

## Phase 10: User Story 7 — Ticket list and product catalog in the new style (P2)

**Goal**: The new row style, filters, search and floating add buttons. Destructive actions move to the detail and form screens.

**Independent Test**: Filter tickets by each status, open one and void it from the detail with confirmation; search products and remove one from its form (quickstart 22).

- [X] T096 [P] [US7] Add `filterTicketsByStatus(tickets, filter: 'ALL' | TicketStatus)` to `shop-keeper-mobile/src/features/ledger/services/ticketService.ts` (voided tickets always excluded). Test it in `ledgerDomain.test.ts`.
- [X] T097 [US7] Rebuild `shop-keeper-mobile/src/features/ledger/screens/TicketListScreen.tsx`:
  - `FilterChips` `filterAll`/`filterUnpaid`/`filterPartial`/`filterPaid`;
  - `DayHeader` groups, newest first;
  - `ListRow`s (Avatar, `getCustomerLabel`, `itemsCount · HH:mm`, `StatusChip`, the remaining amount for open tickets or the total for paid ones) that open `/ledger/ticket-detail`;
  - **no** edit or void buttons in rows;
  - a `Fab` "+ Ticket";
  - an `EmptyState` per filter.
- [X] T098 [US7] Rebuild `shop-keeper-mobile/src/features/ledger/screens/TicketDetailScreen.tsx`:
  - `useTopBarOptions` title;
  - a header card with customer label, date, `StatusChip`, total and remaining;
  - the lines;
  - a payment history using `paidAtSale` for `AT_SALE`;
  - an audit history;
  - an Edit button (outline) and a Void action through `ConfirmAction` whose message is `voidTicketWithSalePayment` when an active `AT_SALE` exists;
  - a `collect` button when the ticket is unpaid or partial and not walk-in.
- [X] T099 [US7] Rebuild `shop-keeper-mobile/src/features/ledger/screens/ProductCatalogScreen.tsx`:
  - a `SearchField` (`productSearchPlaceholder`);
  - one card of `ListRow`s (cube tile, name, code or `noCode`, price `AmountText`) that open `/ledger/product-form?id=…`;
  - **no** remove button;
  - a `Fab` "+ Produit";
  - an `EmptyState` for an empty catalog or no search results.
- [X] T100 [US7] Add the keys `itemsCount`, `filterUnpaid`, `filterPartial` and `filterPaid` to `shop-keeper-mobile/src/i18n/translations/{fr,ar,en}.json`.

**Checkpoint**: All user stories are functional.

---

## Phase 11: Polish & Cross-Cutting Concerns

- [X] T101 Delete the replaced components once `grep` shows no import:
  - `shop-keeper-mobile/src/features/ledger/components/LedgerPage.tsx`;
  - `components/utils/DialogShell.tsx`;
  - `components/ticket/ProductPicker.tsx`, `TicketLineEditor.tsx` and `TicketTotal.tsx`;
  - `components/payment/TicketSelectionDialog.tsx` and `CustomerSelectionDialog.tsx`;
  - `components/customers/CustomerSelect.tsx`.
- [X] T102 Translations audit. `grep` `.tsx` files under `shop-keeper-mobile/src/` for string literals rendered in `<Text>` and for keys used but missing. Verify that the AR wording follows the `فاتورة` convention and that `translations.test.ts` is green. Remove keys no longer used.
- [X] T103 [P] Accessibility audit of `shop-keeper-mobile/src/components/ui/*.tsx` and all screens: role, label and state on every pressable; min 44 dp; header roles on section titles; amounts announced in full.
- [X] T104 [P] Mark the four 002 leftovers done in `specs/002-ledger-usability-dashboard/tasks.md` (referencing T036–T044), and update the "Known issues" section of `CLAUDE.md`.
- [X] T105 Run `npm run validate` in `shop-keeper-mobile/` and fix every lint, type and test failure.
- [x] T106 **(Owner, on a device; automated part done, see [validation.md](./validation.md).)** Run every scenario of `specs/003-ledger-visual-redesign/quickstart.md` on Android (and iOS if available) in FR and AR, at 100% and 130% font scale, including the font-failure and long-value checks. Record results and screenshots in the PR description. — Closed 2026-10-31 by the owner before moving to the phase 1 backend.
- [X] T108 Write numbers identically in every language (spec FR-022): one number locale (`fr-TN-u-nu-latn`) for money in `shop-keeper-mobile/src/lib/money.ts` and `src/components/ui/logic/amountParts.ts`, Latin digits in dates (`-u-nu-latn`), comma in amount input values; only `currencySuffix` is translated. Tests in `src/lib/money.test.ts` and `amountParts.test.ts`.
- [X] T109 Upgrade `shop-keeper-mobile` to Expo SDK 58 (spec FR-023): `expo@^58`, the expected package versions, a clean `package-lock.json`, `npx expo-doctor` passing, and `useDirtyFormGuard` moved to the Expo Router 58 `usePreventRemove(…, ({ repeat }))` API.
- [X] T110 Add an operation-log assertion to every write test in Phase 6 (spec FR-021, SC-009): each write leaves one `pending` entry created inside the same transaction.
- [X] T107 Tick the remaining box in `specs/003-ledger-visual-redesign/checklists/requirements.md` and set the spec status to "Implemented" in `spec.md`. *(Checklist 7/7; status reads "Implemented, pending device validation (T106)".)*

---

## Dependencies & Execution Order

### Phase dependencies

- **Setup (Phase 1)**: none. T001 (done) gated Phase 6.
- **Foundational (Phase 2)**: needs T003–T009. Blocks all story phases.
- **US1 (Phase 3)**: after Phase 2. The other stories render inside its shell, so do it first.
- **US2 (Phase 4)**: after Phase 3.
- **US3 (Phase 5)**: after Phase 3.
- **US8 (Phase 6)**: after Phase 5 (it extends the ticket-form footer) and after T001.
- **US4 (Phase 7)**: after Phase 3. T085's walk-in block needs T076.
- **US5 (Phase 8)**: after Phase 4 (it reuses `summariseDebtors`). T091 touches the ticket form, so run it after Phase 6.
- **US6 (Phase 9)**: after Phase 5 (shared `BarcodeScanner` file, T062 → T093).
- **US7 (Phase 10)**: after Phase 6 (walk-in label, void cascade message).
- **Polish (Phase 11)**: after all stories.

### Story dependency graph

```text
Setup ─► Foundational ─► US1 ─┬─► US2 ─► US5
                              ├─► US3 ─► US8 ─► US7
                              │     └──► US6
                              └─► US4
T001 (constitution) ─────────────────► US8
```

### Within each story

Tests (where listed) are written first and must fail. Then types → services → components → screen → translations. Translation tasks in different phases all edit the same three JSON files, so they never run in parallel with each other.

## Parallel Opportunities

- Phase 1: T003, T004, T005, T006, T007 and T008 together.
- Phase 2: T011–T016 together; after T017 and T018, T020–T034 together (one file each).
- Phase 3: T037/T038, T041, T042, T045–T049 and T051 together after T036 and T040.
- Phase 6: tests T065–T070 together; T071 and T079 alongside the refactors.
- After Phase 3, US2, US3 and US4 can proceed in parallel. After US3, US8 and US6 can proceed in parallel.

### Parallel example: Phase 2 components

```text
Task: "Create AmountText in shop-keeper-mobile/src/components/ui/AmountText.tsx"
Task: "Create StatusChip in shop-keeper-mobile/src/components/ui/StatusChip.tsx"
Task: "Create FilterChips in shop-keeper-mobile/src/components/ui/FilterChips.tsx"
Task: "Create BottomSheet in shop-keeper-mobile/src/components/ui/BottomSheet.tsx"
```

### Parallel example: User Story 8 tests

```text
Task: "saleService.test.ts — validateSaleChoice / labels"
Task: "migrations.test.ts — ensureWalkInCustomer idempotency"
Task: "ticketRepository.test.ts — void cascade"
Task: "errorMessages.test.ts — walk-in mappings"
```

## Implementation Strategy

### MVP first

1. Phase 1 + Phase 2 (tokens, font, components, all tested).
2. Phase 3 (US1): the whole app already looks consistent, because restyled `ledger.css` and `FeedbackState` carry the palette to untouched screens.
3. **Stop and validate** quickstart 1, 2 and 14. This is shippable.

### Incremental delivery

US2 Home → US3 Ticket form → US8 Every sale is a ticket → US4 Payment → US5 Customers → US6 Product form → US7 Lists → Polish. Each phase ends with `npm run validate` green and its quickstart rows passing. Commit per task or logical group on `003-ledger-visual-redesign`.

## Notes

- Never use `npm install` for Expo packages; use `npx expo install`.
- The uncommitted change in `shop-keeper-mobile/src/features/ledger/types/ticket.ts` is a single blank line. Leave it to the owner, or drop it when T071 touches the types folder, after confirming with them.
- Services and schema change only as listed in Phase 6 and the read helpers (T054, T055, T088, T096).
