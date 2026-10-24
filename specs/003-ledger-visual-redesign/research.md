# Research: Ledger Visual Redesign (003)

All Technical Context unknowns are resolved below. Each entry: **Decision**, **Rationale**, **Alternatives considered**.

## Styling and design system

### R1. Where tokens live
- **Decision**: Add the colors, radii, font families and font sizes from design-reference §1 to `theme.extend` in `tailwind.config.js`. Use names exactly as in the reference (`ground`, `surface`, `ink`, `ink-2`, `line`, `line-soft`, `line-strong`, `muted`, `primary`, `primary-soft`, `primary-faint`, `debt`, `debt-strong`, `debt-soft`, `debt-line`, `paid`, `paid-soft`, `partial`, `partial-soft`, `partial-line`, `warn-strong`, `scrim`). Composite classes (`.ui-card`, `.ui-button-primary`, `.type-display` …) go in `src/components/ui/ui.css`, imported from `global.css`. Unused `src/constants/theme.ts` and `src/hooks/use-theme.ts` / `use-color-scheme*` are deleted if nothing imports them.
- **Rationale**: Matches FR-001 and the reference ("put these in tailwind.config.js"). There is one source of truth, and future white-labelling only touches config (CLAUDE.md: no colors outside config).
- **Alternatives**: a TS token module imported by components (rejected: puts hex values back into TS and invites `style` props); CSS variables (rejected: NativeWind variable support adds complexity for no benefit with a single light theme).

### R2. Font loading
- **Decision**: `npx expo install @expo-google-fonts/ibm-plex-sans-arabic`. In `src/app/_layout.tsx`, call `useFonts({ IBMPlexSansArabic_400Regular, _500Medium, _600SemiBold, _700Bold })` and render the app once fonts are **loaded or errored**. Define four Tailwind font families (`font-plex`, `font-plex-medium`, `font-plex-semibold`, `font-plex-bold`). Android picks a weight by family name, not by `fontWeight`, so type classes in `ui.css` pair each size with the matching family. If loading fails, React Native falls back to the system font, so the app stays usable (edge case).
- **Rationale**: The font covers Latin and Arabic (clarification). Runtime loading works in Expo Go and dev builds alike.
- **Alternatives**: the `expo-font` config plugin to embed fonts at build time (kept as a later optimisation; it doesn't work in Expo Go); keeping only `fontWeight` (rejected: Android would render every weight as Regular).

### R3. Enforcing "no hex / no inline style" (SC-001)
- **Decision**: Add a `**/*.tsx` block to `eslint.config.js` with `no-restricted-syntax` selectors:
  - `Literal[value=/#[0-9a-fA-F]{3,8}\b/]` and the template-literal equivalent (hex colors);
  - `JSXAttribute[name.name='style']` (inline style);
  - `ImportSpecifier[imported.name='StyleSheet']`.
  Each rule gets a message that points to the constitution.
- **Rationale**: `npm run validate` already runs ESLint, so the rule is enforced without new CI.
- **Alternatives**: a grep script (rejected: not wired into validate); a custom ESLint plugin (rejected: overkill).

### R4. Icon and other color props
- **Decision**: Create `src/components/ui/Icon.tsx`, which wraps Ionicons with `cssInterop(Ionicons, { className: { target: 'style', nativeStyleToProp: { color: true } } })`. Callers pass `className="text-primary"`. A `directional` prop swaps `arrow-back↔arrow-forward` and `chevron-back↔chevron-forward` in RTL. `TextInput` placeholder colors use the NativeWind `placeholder:text-ink-2` class. `ActivityIndicator` is wrapped the same way when it is used.
- **Rationale**: Removes every hex literal from `.tsx` (current offenders: `BottomTabs`, `TopBar`, `LanguageMenu`) and centralises RTL mirroring (US1-AS4).
- **Alternatives**: exporting hex constants from a `.ts` file (rejected: technically allowed but keeps colors outside the token config); `I18nManager.forceRTL` (rejected: it needs a restart, and the app already switches direction live).

### R5. Safe-area padding without inline style (002 leftover)
- **Decision**: Use NativeWind 4 safe-area utilities: `pt-safe` on the top bar, `pb-safe` on the tab bar and on `StickyFooter` when no tab bar is shown. Delete `useSafeAreaInsets` + `style` from the shell. The `SafeAreaProvider` comes from Expo Router's root.
- **Rationale**: Fixes the constitution violation tracked in 002 and keeps the logic in classes.
- **Alternatives**: `SafeAreaView edges={['top']}` (kept as a fallback if the `*-safe` utilities fail on a platform during validation).

### R6. Component location and granularity
- **Decision**: Put the 16 components from the reference in `src/components/ui/`, plus `SegmentedControl` (needed by the US8 payment-status selector) and `Icon`. Each component takes already-translated labels as props, except that `StatusChip` and `DayHeader` read their fixed labels via `useLedgerTranslations` so callers can't forget the text (US1-AS3). Ledger compositions (customer picker sheet, product search sheet, ticket line, payment ticket sheet) stay in `features/ledger/components/`. `LedgerPage` and `DialogShell` are removed once no screen uses them.
- **Rationale**: Constitution II asks for small reusable units with demonstrated reuse; each component here is used on at least two screens.
- **Alternatives**: a UI kit such as Tamagui or gluestack (rejected: a new styling system is forbidden without justification).

### R7. How UI components are tested
- **Decision**: Every component's non-trivial logic lives in a pure helper under `src/components/ui/logic/` (initials, amount split, day grouping, quick-amount clamping, quantity bounds, filter counts), and the ledger rules live in services. These are tested with Vitest in the existing node environment. Components stay thin, and their rendering is checked manually with the quickstart in FR/AR.
- **Rationale**: The repo has no React Native renderer test setup. Adding `jest-expo` + Testing Library would mean a second test runner, and the constitution says test scope should be proportionate.
- **Alternatives**: `@testing-library/react-native` on Vitest (rejected: RN on Vitest needs heavy mocking of the native modules); snapshot tests (rejected: low value for a visual redesign).

### R8. Amount display (AmountText, StatCard)
- **Decision**: Add `splitMillimeAmount(value, language)` in `logic/amountParts.ts`, which returns `{ sign, dinars, millimes, currency }`. It uses `Intl.NumberFormat` for locale grouping and the decimal separator, and translation key `currencySuffix` for the currency (`DT` / `د.ت`). `formatMillimeAmount` in `lib/money.ts` stays as the canonical full formatter; the split helper is tested against it. Digits use `tabular-nums` and the `ledger-ltr` class so they stay LTR inside RTL. For long amounts, stat cards use `numberOfLines={1}` + `adjustsFontSizeToFit` (edge case: 7+ digits).
- **Rationale**: The reference asks for dinars large, `,millimes` small, then the currency. Splitting needs parts that `formatMillimeAmount` can't give.
- **Alternatives**: `Intl.NumberFormat#formatToParts` everywhere (used inside the helper; the helper derives the separator with `format()` instead, so it does not depend on Hermes `formatToParts` support).

### R9. Translations with parameters and key parity
- **Decision**: Add `formatMessage(template, params)` in `src/i18n/format.ts`, which replaces `{name}` placeholders and leaves unknown ones visible (it doesn't throw). Add `src/i18n/translations.test.ts`, which asserts that fr/ar/en have identical key sets and identical placeholder sets per key. English text is written by us (the reference gives only FR/AR).
- **Rationale**: About 15 new keys carry `{count}`, `{amount}`, `{date}`, and no helper exists today. The parity test enforces FR-011.
- **Alternatives**: i18next (rejected: a new framework for one function).

## Shell and navigation

### R10. Root layout cleanup (FR-014) and font gate
- **Decision**: `RootLayout` keeps one `Provider`. Remove the inner `Provider` and the `expo-router/build/react-navigation/bottom-tabs` import. Gate order: fonts (loaded or failed) → database (loading / failed + retry / ready) → shell. The loading and failure screens use tokens.
- **Rationale**: CLAUDE.md known issues; one gate keeps startup states explicit.

### R11. Home route and the "hide shell outside registered features" task
- **Decision**: Home is `/` (the registry already points there). `src/app/index.tsx` renders `HomeScreen` with no extra header or `SafeAreaView`. `src/app/ledger/index.tsx` becomes `<Redirect href="/" />`. `getActiveFeature` returns `null` when no registry item or form fragment matches, for example the `+not-found` route; `TopBar` and `BottomTabs` render nothing for `null`. The language-selection entry screen no longer exists (the top-bar language pill replaced it in 002), so this is how the 002 task is closed.
- **Rationale**: Today Home is rendered twice (once via `/`, once via `/ledger`) with a duplicated header.

### R12. Typed routes check (002 leftover)
- **Decision**: Registry routes get a leading slash (`'/ledger/products'` …) and are typed as `Href`. `npm run typecheck` with `typedRoutes: true` is the verification. A Vitest test asserts that each registry route matches an existing file under `src/app/`.
- **Rationale**: `'ledger/products'` (no slash) type-checks today only because `Href` accepts relative strings.

### R13. Android system back on dirty forms (002 leftover, FR-013)
- **Decision**: Add a hook, `useDirtyFormGuard()`, used by every form screen. It calls `usePreventRemove(isDirty, ({ repeat }) => confirm(() => { clearFormDirty(); repeat(); }))` from `expo-router`. In Expo Router 58, a blocked removal continues only by turning the condition false and calling `repeat` (docs: router/migrate/sdk-57-to-58). This one hook then covers system back, iOS swipe and top-bar back. `useAppNavigation.goBack` stops prompting itself; `switchTo`/`pushTo` keep their guard and clear the dirty flag before navigating, so the user never gets two prompts.
- **Rationale**: One confirmation path for every way of leaving a form.
- **Alternatives**: `BackHandler` (rejected: Android only, misses the swipe gesture).

### R14. Bottom sheet, sticky footer and keyboard
- **Decision**: `BottomSheet` uses React Native `Modal` (`transparent`, `animationType="slide"`, `onRequestClose` for Android back). It has a `bg-scrim` backdrop that closes on press, a handle, title, helper line, scrollable content and an optional confirm button. `ScreenScroll` with a `footer` renders `KeyboardAvoidingView (behavior="padding")` → `ScrollView` + `StickyFooter`, so the footer stays above the keyboard.
- **Rationale**: No new native dependency. It matches the existing `DialogShell`/`Modal` usage.
- **Alternatives**: `@gorhom/bottom-sheet` (rejected: new dependency plus a Reanimated coupling for a simple list sheet); `react-native-keyboard-controller` (rejected: a new native module needs a dev build). Validate the keyboard behaviour on an Android device with edge-to-edge (quickstart).

## Data and services (User Story 8 and read helpers)

### R15. Walk-in customer storage
- **Decision**: Use a **reserved row with fixed id `walk-in`** in `customers` (`internal_code = 'WALK_IN'`, `display_label = 'walk-in'`, `is_active = 1`). An idempotent migration step `ensureWalkInCustomer(db)` inserts it with `INSERT OR IGNORE`. When a row was actually inserted, the step writes its `pending_changes` entry in the same transaction. The UI never shows `display_label`; it shows translation key `walkInCustomer`. Export `WALK_IN_CUSTOMER_ID` and `isWalkInCustomer(id)` from `features/ledger/constants.ts`.
- **Rationale**: No `ALTER TABLE`. The same id on every device means future sync (004) merges walk-in sales naturally. Exclusion is a single `id <> 'walk-in'` predicate.
- **Alternatives**: an `is_system` flag column (rejected: an extra column and a predicate in every query, with no added safety; ids are already the identity); a nullable `tickets.customer_id` (rejected: breaks the `NOT NULL` FK and every 001 query).

### R16. At-sale payment marking
- **Decision**: Extend `PaymentKind` to `'TICKET' | 'CUSTOMER_BALANCE' | 'AT_SALE'`. The `payments.payment_kind` column is `TEXT NOT NULL` without a CHECK, so no migration is needed for the column. The payment's `applied_at` is set to the ticket's `created_at`.
- **Rationale**: Matches the spec ("for example a new payment kind"), and the history can show "Payé à la vente". Future reports can separate cash sales from debt collection.

### R17. Single transaction for ticket + at-sale payment
- **Decision**: Refactor without behaviour change:
  - `TicketRepository.insertTicketRecords(ticket, db)` does the existing validation, `saveTicket`, audit and pending change, with no transaction of its own.
  - `paymentRepository.ts` exports `insertPaymentRecords(payment, db)`, a module-level writer that does the row, audit and pending change, with no transaction.
  - The existing public `createTicket` / `createPayment` wrap these inside `transaction(...)`.
  - The new `recordSale(ticket, choice)` in `saleService.ts` opens **one** `withLedgerTransaction`. It validates the choice and the customer, inserts the ticket, then inserts the `AT_SALE` payment when the amount is above 0.
- **Rationale**: `withTransactionSync` cannot nest. FR-016 and US8-AS6 require all-or-nothing writes, and the pending changes land in the same batch (ticket create, then payment create).

### R18. Walk-in invariants (spec edge cases, FR-017)
- **Decision**: These guards are enforced in services and mapped to localized errors:
  1. `recordSale` with the walk-in customer requires choice `PAID` (amount = total).
  2. `updateTicket` on a walk-in ticket is rejected when the new total differs from the amount already paid (a walk-in ticket can never carry a balance). To change it, the owner voids and re-records.
  3. Reassigning any ticket **to** walk-in is rejected.
  4. `createPayment` on a walk-in ticket is rejected (it is already paid). `updatePayment` / `voidPayment` of the `AT_SALE` payment of a walk-in ticket is rejected: void the ticket instead.
  5. `updateCustomer` / `removeCustomer` on walk-in are rejected.
  6. `listCustomers` excludes walk-in unless `{ includeWalkIn: true }` is passed (used only by ticket list/detail label lookups).
  7. Debtor summaries and filter counts exclude walk-in.
- **Rationale**: Prevents any path from giving the walk-in customer a balance, while the 001 rules for named customers stay unchanged.

### R19. Void cascade (FR-020)
- **Decision**: `voidTicket` voids the ticket and, in the same transaction, each **active `AT_SALE` payment** of that ticket. Each payment gets its own audit event + `soft_delete` pending change. Later debt-collection payments (`TICKET` kind) keep their 001 behaviour (not voided automatically). The ticket-detail confirmation text says the at-sale payment is cancelled too (new key `voidTicketWithSalePayment`).
- **Rationale**: Keeps today's sales and cash consistent (edge case) without changing 001 semantics for collected payments.

### R20. Partial amount and save label rules
- **Decision**: Pure functions in `saleService.ts`:
  - `validateSaleChoice(choice, totalMillimes, customerId)` returns `null | 'kridiNeedsCustomer' | 'partialAmountInvalid' | 'partialEqualsTotal'`. A partial amount must be a safe integer with `0 < amount < total`; `amount === total` maps to a message suggesting "Payé" (US8-AS3).
  - `getSalePaymentAmount(choice, total)` returns `total | amount | 0`.
  - `getSaleSaveLabel(choice, total, t, language)` returns `{ key, params }` for `collectAmount` / `collectWithRemainder` / `putOnKridi` (FR-019).
  The form calls them for live feedback; `recordSale` re-validates.
- **Rationale**: Business rules stay out of components (constitution II) and are unit-testable.

### R21. Read helpers (spec clarification 1)
- **Decision**:
  - `summariseDebtors(customers, tickets, payments, now)` in `customerBalanceService.ts` makes one pass and returns `DebtorSummary[]` sorted by balance descending, then label. Fields: balance, open-ticket count, oldest open ticket date, last payment date.
  - `getDashboardSummary` gains `todayTicketCount` and `openTicketCount`, and computes the outstanding balance through the same single-pass map.
  - `getCustomerFilterCounts(summaries)` returns `{ owes, upToDate, all }`.
  - `listTopDebtors(limit = 3)`.
  All exclude walk-in and voided tickets and payments.
- **Rationale**: US2 and US5 need these numbers. The current outstanding calculation is O(tickets × payments).

### R22. Quick amounts (US4)
- **Decision**: `QUICK_AMOUNTS_MILLIMES = [10_000, 20_000, 50_000]`. `getQuickAmountOptions(remaining)` returns "Whole ticket" (= remaining) and the fixed chips. Fixed chips are **enabled only when `remaining ≥ 10 000`**. Selecting a chip sets `min(chip, remaining)`.
- **Rationale**: Satisfies both US4-AS2 (a chip larger than the remainder clamps) and the edge case (under 10 DT only "Whole ticket" is enabled).

## Governance

### R23. Constitution scope for 003
- **Decision** (owner, 2026-10-24): no amendment. The constitution stays at 1.5.0 and 003 is not added to the local-only exception. 003 follows Principle VI: SQLite-first writes, each with an operation-log entry (`pending_changes`, client UUID, status `pending`) in the same transaction. Background synchronization with the server when the network is available is delivered with the backend (004), which reads that log.
- **Rationale**: Keeps the governance text intact and the data sync-ready. Sync code is not written before a server exists to test it against.
- **Alternatives**: a PATCH 1.5.1 amendment adding 003 to the local-only exception (rejected by the owner: the product needs server sync).
