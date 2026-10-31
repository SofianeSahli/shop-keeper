---

description: "Task list for 004 — Async services and app-wide feedback"
---

# Tasks: Async Services and App-wide Feedback

**Input**: Design documents from `specs/004-async-feedback/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md), design note [async-feedback.md](../../shop-keeper-mobile/docs/design/async-feedback.md)

**Tests**: Included. SC-003, SC-004 and SC-007 require them, and the plan names them: the queue, the loader timeline, the feedback slice, the async-action runner, and the 001–003 repository tests converted to `await`.

**Organization**: Tasks are grouped by user story. All paths are relative to `shop-keeper-mobile/`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task).
- **[Story]**: US1–US4 from spec.md.

## Rules for every task

- No `StyleSheet`, `style={{…}}` or hex color in `.tsx`; tokens and classes only.
- Every string goes in `src/i18n/translations/{fr,ar,en}.json`, with the design note's French and Arabic wording.
- Code explains itself: no comments. Remove existing comments in the files you touch. Tool directives such as `eslint-disable` or `@ts-expect-error` stay.
- Business rules, transactions, audit events and `pending_changes` entries stay exactly as in 001–003 (FR-001).
- `npm run validate` passes at each checkpoint.

---

## Phase 1: Setup (tokens and strings)

**Purpose**: Design tokens and translations that every later phase uses.

- [X] T001 Add color tokens to `tailwind.config.js`: `error #A3221A`, `error-soft #FDE8E6`, `error-line #F4BDB7`, `error-strong #6B1F18`, `paid-line #A9DCD2`, `primary-line #B7CDEB`, `skeleton #E6EBE9` (research R16). Add a `toast-in` keyframe (translate-y from -16 px and opacity 0 → 0 and 1) and an `animate-toast-in` animation lasting 220 ms (R11).
- [X] T002 [P] Add the French strings to `src/i18n/translations/fr.json`:
  - from the design note: `savingTicket`, `savingPayment`, `savingProduct`, `savingCustomer`, `voidingTicket`, `removingProduct`, `removingCustomer`, `saving`, `dataStaysOnPhone`, `takingLonger`, `doNotCloseApp`, `ticketSavedToast`, `paymentSavedToast`, `productSavedToast`, `customerSavedToast`, `amountCollected`, `notSavedToast`, `nothingChanged`, `valuesKept`, `view`, `youCanContinue`, `updating`;
  - also needed by the contract mapping:
    - `voidingPayment` ("Annulation du paiement…");
    - `ticketVoidedToast` ("Ticket annulé");
    - `paymentVoidedToast` ("Paiement annulé");
    - `productRemovedToast` ("Produit retiré");
    - `customerRemovedToast` ("Client retiré");
    - the `{thing}` nouns `thingTicket`, `thingPayment`, `thingProduct`, `thingCustomer`;
    - `itemsAndTotal` ("{count} articles · {amount}");
    - `loading` ("Chargement…");
    - `dismiss` ("Fermer");
    - `demoBackgroundTask` ("Tâche de test en cours").

  Reuse the existing `retry` key if it exists; otherwise add `retry` ("Réessayer").
- [X] T003 [P] Add the same keys to `src/i18n/translations/ar.json`, using the design note's Arabic for the listed keys and matching Arabic for the others. List the Arabic texts that are not in the design note in the PR description, for the owner to review.
- [X] T004 [P] Add the same keys to `src/i18n/translations/en.json`.
- [X] T005 Update the translation key type in `src/i18n/types.ts`, if keys are typed there, and run `src/i18n/translations.test.ts` to confirm fr, ar and en have the same keys.
- [X] T006 [P] Switch the error tone of `src/components/ui/InfoBanner.tsx` from the debt colors to `bg-error-soft border-error-line text-error-strong`, with the icon in `text-error` (R16).

---

## Phase 2: Foundational (async data layer, feedback state, reads)

**Purpose**: Make every database call asynchronous through one serial queue, and keep every screen reading its data. Until this phase is done, the app cannot load data.

**⚠️ CRITICAL**: No user story can start before this phase ends. Work on this branch only; it ships in one PR with the stories (plan, "Implementation order").

### Queue and test adapter

- [X] T007 Create `src/db/queue.ts`:
  - `runQueued<T>(task: () => Promise<T>): Promise<T>` chains tasks on a single promise so they never interleave (R2). A failed task must not break the chain for the next ones.
  - When `__DEV__` is true and `EXPO_PUBLIC_LEDGER_DELAY_MS` is a positive integer, wait that long before each task (R6). Otherwise there is no delay.
- [X] T008 [P] Write `src/db/queue.test.ts`. It checks:
  - tasks run in call order;
  - a second task does not start before the first settles;
  - a rejection is passed to its caller and the next task still runs;
  - the delay is applied only when set.
- [X] T009 [P] Extend `createTestLedgerDb` in `src/test/ledgerTestDb.ts` with `runAsync`, `getFirstAsync` and `withTransactionAsync`, implemented over the same `node:sqlite` calls. `withTransactionAsync` must roll back on a rejected task. Convert `countRows` and `insertTestCustomer` to async versions, and keep the sync helpers only if a test still needs them (R5).

### Async database access (contract: async-data-access.md)

- [X] T010 Make `withLedgerTransaction(operation, db)` in `src/db/index.ts` async. It runs `runQueued(() => db.withTransactionAsync(() => operation(db)))`, and `operation` is `async (db) => T`. Use `execAsync` and `runAsync` instead of every remaining sync call.
- [X] T011 Convert `src/db/migrations.ts` (including `ensureWalkInCustomer`) to `execAsync`, `getFirstAsync` and `runAsync`, and make `openLedgerDatabase` await them. Update `src/db/migrations.test.ts` to `await` with unchanged assertions.
- [X] T012 [P] Make `appendAuditEvent`, `listAuditEvents`, `persistPendingChange` and `listPendingChanges` async in `src/features/ledger/services/auditService.ts` and `src/features/ledger/services/pendingChangeService.ts`. Calls inside a transaction take the transaction's `db` and never call `runQueued` (contract rule).
- [X] T013 [P] Make `writePaymentRow`, `insertPaymentRecords`, `voidPaymentRecords` and `listActiveSalePayments` async in `src/features/ledger/services/paymentRecords.ts`.
- [X] T014 Make `listProducts`, `getProduct`, `findActiveProductByBarcode`, `saveProduct`, `listCustomers`, `getCustomer` and `saveCustomer` async in `src/features/ledger/services/ledgerRepository.ts`. Return `Promise`s in its repository type, and route the public reads through `runQueued`. Depends on T010, T012.
- [X] T015 Make `listTickets`, `getTicket`, `totalPaid`, `saveTicket`, `insertTicketRecords`, `createTicket`, `updateTicket` and `voidTicket` async in `src/features/ledger/services/ticketRepository.ts`. Convert `src/features/ledger/services/ticketRepository.test.ts` to `await` with unchanged assertions, including the rollback cases. Depends on T010, T012, T013.
- [X] T016 Make `listPayments`, `getPayment`, `createPayment`, `updatePayment` and `voidPayment` async in `src/features/ledger/services/paymentRepository.ts`, and convert `src/features/ledger/services/paymentRepository.test.ts`. Depends on T013, T015.
- [X] T017 Make `listCustomers`, `getCustomer`, `createCustomer`, `updateCustomer` and `removeCustomer` async in `src/features/ledger/services/customerService.ts`, and `listProducts`, `getProduct`, `createProduct`, `updateProduct` and `removeProduct` async in `src/features/ledger/services/productService.ts`. Pure helpers (`create…Record`, `validateProductInput`, `find…`) stay synchronous. Convert `src/features/ledger/services/crudServices.test.ts` and its mocks to async. Depends on T014.
- [X] T018 Make `recordSale` async in `src/features/ledger/services/saleService.ts` (one transaction for the ticket and its `AT_SALE` payment), and convert `src/features/ledger/services/saleService.test.ts`. Depends on T015, T016.
- [X] T019 Make `getDashboardSummary`, `getTodaySalesMillimes` and `getOutstandingBalanceMillimes` async in `src/features/ledger/services/dashboardService.ts`, and `listDebtorSummaries`, `listTopDebtors` and `getCustomerLedgerEntries` async in `src/features/ledger/services/ledgerReadService.ts`. Pure `summarise…` and `build…` helpers stay synchronous. Convert `dashboardService.test.ts` and `customerBalanceService.test.ts` where they call the database. Depends on T015–T017.
- [X] T020 Make `getSavedLanguage` and `saveLanguage` async in `src/i18n/languageStorage.ts`. Await the language in `src/app/_layout.tsx` before leaving the startup screen (the startup error screen stays as it is), and await `saveLanguage` in `src/navigation/LanguageMenu.tsx` (R4).
- [X] T021 Run `grep -rnE "(run|getFirst|getAll|exec|withTransaction)Sync" src` and confirm that matches remain only in `src/test/`.

### Feedback state and reads

- [X] T022 Create `src/features/feedback/store/feedbackSlice.ts` and register it as `feedback` in `src/store/index.ts`. State: `toasts: Toast[]` and `loader: { label, detail, shownAt, slow } | null` (data-model §1–2).
  - `Toast` fields: `id`, `kind: 'success' | 'info' | 'error' | 'pending'`, `title`, `detail: string | null`, `actionId: string | null`, `actionLabel: string | null`, `dedupeKey: string | null`.
  - Reducers:
    - `enqueueToast`: a success with the same `dedupeKey` as the visible head replaces it instead of queueing;
    - `dismissToast(id)`;
    - `resolvePending(id, outcome)`, which changes the toast in place;
    - `showLoader`, `markLoaderSlow`, `hideLoader`.
  - Only serializable values go in the state.
- [X] T023 [P] Write `src/features/feedback/store/feedbackSlice.test.ts`. It covers: queue order, the head being the visible toast, dismiss, dedupe replacement, a pending toast resolved in place, and the loader show, slow and hide.
- [X] T024 [P] Create `src/components/ui/useReduceMotion.ts`. It returns `AccessibilityInfo.isReduceMotionEnabled()` and follows the `reduceMotionChanged` event (FR-011, R11).
- [X] T025 Create `src/features/feedback/useLedgerQuery.ts`, returning `useLedgerQuery<T>(load: () => Promise<T>, deps) → { data, status: 'loading' | 'refreshing' | 'ready' | 'error', error, retry }` (contract, data-model §5):
  - `loading` only when there is no data yet, and data is kept while refreshing;
  - reloads on screen focus and when `state.ledger.refreshVersion` changes;
  - errors are mapped with `getLedgerErrorMessage(cause, t, t.loadFailed)`;
  - results from an older request or an unmounted screen are dropped.

  Put the status transitions and the request numbering in a pure `src/features/feedback/queryState.ts` and test it in `queryState.test.ts` (first load, refresh keeps data, error with and without data, stale result dropped).
- [X] T026 [P] Move `src/features/ledger/screens/HomeScreen.tsx` to `useLedgerQuery` (dashboard summary and top debtors), keeping today's rendering. While `status === 'loading'` it shows what it shows today when it has no data.
- [X] T027 [P] Move `src/features/ledger/screens/CustomerListScreen.tsx` and `src/features/ledger/screens/CustomerBalanceScreen.tsx` to `useLedgerQuery`.
- [X] T028 [P] Move `src/features/ledger/screens/CustomerLedgerScreen.tsx` to `useLedgerQuery` (customer and ledger entries).
- [X] T029 [P] Move `src/features/ledger/screens/TicketListScreen.tsx` and `src/features/ledger/screens/TicketDetailScreen.tsx` to `useLedgerQuery` for their reads. The void action keeps its current flow until T048.
- [X] T030 [P] Move `src/features/ledger/screens/PaymentHistoryScreen.tsx` and `src/features/ledger/screens/LedgerHistoryScreen.tsx` to `useLedgerQuery`.
- [X] T031 [P] Move `src/features/ledger/screens/ProductCatalogScreen.tsx` to `useLedgerQuery`.
- [X] T032 [P] Load the record being edited and the pick lists with `useLedgerQuery` in `src/features/ledger/screens/ProductFormScreen.tsx`, `src/features/ledger/screens/PaymentFormScreen.tsx`, `src/features/ledger/screens/TicketFormScreen.tsx` and `src/features/ledger/screens/CustomerFormScreen.tsx` / `src/features/ledger/components/customers/CustomerForm.tsx`. Initialise the form state once the data is `ready`, without overwriting later edits.
- [X] T033 [P] Load the debtor summaries in `src/features/ledger/components/customers/CustomerPickerCard.tsx` with `useLedgerQuery`, and check that `PaymentCustomerSheet.tsx`, `PaymentForm.tsx` and `ProductSearchSheet.tsx` still receive loaded data from their parents.
- [X] T034 For the interim write calls not yet on the async helper (ticket, payment, product and customer saves, voids and removals), `await` the service before navigating and keep each screen's current error banner, so the app works end to end. Then run `npm run validate`.

**Checkpoint**: Every database call is async through `runQueued`. The 001–003 tests pass unchanged apart from `await`. Every screen loads its data, and saves still work.

---

## Phase 3: User Story 1 — Saving never freezes and always shows progress (Priority: P1) 🎯 MVP

**Goal**: Every write shows a busy button at once, and a blocking loader if it lasts more than 300 ms, which turns "slow" after 2 s. A double tap writes once.

**Independent Test**: With `EXPO_PUBLIC_LEDGER_DELAY_MS=1200`, save a ticket. The button shows "Enregistrement…" at once; the loader appears at about 300 ms with "Enregistrement du ticket…" and "4 articles · 13,150 DT" and stays at least 500 ms; Android back does nothing; a second tap creates nothing. With `3000`, the slow message and amber spinner appear 2 s after the loader shows (quickstart scenarios 1–4).

### Tests for User Story 1

- [X] T035 [P] [US1] Write `src/features/feedback/loaderTimeline.test.ts` with Vitest fake timers. It checks:
  - the loader never shows when work ends before 300 ms;
  - it shows at 300 ms;
  - when work ends between 300 and 800 ms, the outcome waits until 500 ms of visibility;
  - it turns slow at `shownAt + 2000`.
- [X] T036 [P] [US1] Write `src/features/feedback/asyncActionRunner.test.ts`. It checks:
  - `busy` is set synchronously;
  - a second run while busy returns `undefined` and calls `work` only once;
  - the loader is shown, made slow and hidden through the injected dispatch;
  - the result is returned on success, and `undefined` on failure.

### Implementation for User Story 1

- [X] T037 [US1] Create `src/features/feedback/loaderTimeline.ts`:
  - constants `SHOW_AFTER_MS = 300`, `MIN_VISIBLE_MS = 500`, `SLOW_AFTER_MS = 2000`;
  - pure functions `shouldShow(elapsed)`, `outcomeDelay(finishedAt, shownAt | null)` (0 when never shown, otherwise `max(0, shownAt + 500 − finishedAt)`) and `isSlow(now, shownAt)` (data-model §3).
- [X] T038 [US1] Create `src/features/feedback/asyncActionRunner.ts`, a framework-free runner driven by `setTimeout` and an injected dispatch. On run it sets busy, schedules `showLoader({ label, detail })` at 300 ms and `markLoaderSlow` at 2 s after showing, awaits `work()`, waits `outcomeDelay`, dispatches `hideLoader`, and then reports the outcome. It ignores runs while busy (FR-003, FR-004, SC-004).
- [X] T039 [US1] Create `src/features/feedback/useAsyncAction.ts`. It returns `{ run, busy }` and wraps the runner with the Redux dispatch (contract: feedback-api.md). `busy` is React state mirrored by a ref, so a synchronous second tap is refused. Spec fields: `work`, `loaderLabel`, `loaderDetail?`, `successToast?`, `failureThing`, `keepValuesMessage` (outcome toasts are added in US2).
- [X] T040 [P] [US1] Create `src/components/ui/BlockingLoader.tsx`. It reads `state.feedback.loader` and renders a transparent `Modal` with:
  - a `bg-scrim` backdrop and `onRequestClose={() => {}}` (Android back does nothing);
  - a card with radius 20, a 44 `ActivityIndicator` (`text-primary`, or `text-partial` when slow), a 17/700 title, a 13.5 detail and a 12.5 `dataStaysOnPhone` note;
  - when slow, `takingLonger` and `doNotCloseApp` replace the title and note;
  - `accessibilityViewIsModal` and a live region on the title (R13, R14, FR-011).

  Add any missing font sizes or radii as tokens in `tailwind.config.js` or `src/components/ui/ui.css`.
- [X] T041 [P] [US1] Create `src/components/ui/BusyButton.tsx`, using the existing primary button classes. Props: `busy`, `label`, `busyLabel` (default `t.saving`), `onPress`, `accessibilityLabel`. When busy it is disabled, has `opacity-85`, shows a small spinner and `busyLabel`, and sets `accessibilityState={{ busy: true, disabled: true }}`. The touch target is at least 44 dp. Export it and `BlockingLoader` from `src/components/ui/index.ts`.
- [X] T042 [US1] Render `<BlockingLoader />` once in `src/app/_layout.tsx`, after the database is ready (no loader before startup completes).
- [X] T043 [US1] Make `src/features/ledger/hooks/useDirtyFormGuard.ts` accept a `busy` flag. While busy, `usePreventRemove` blocks leaving and does not show the discard prompt; Android back is ignored (spec edge case).
- [X] T044 [US1] In `src/features/ledger/screens/TicketFormScreen.tsx`, run the ticket save through `useAsyncAction`:
  - for a new ticket, `recordSale`; for an edit, `updateTicket`;
  - `loaderLabel: t.savingTicket` and `loaderDetail` from `itemsAndTotal` with `formatMillimeAmount`;
  - replace the save button with `BusyButton`;
  - navigate only when `run` resolves with a value.

  The inline product creation goes through the same helper with `savingProduct`.
- [X] T045 [P] [US1] In `src/features/ledger/screens/PaymentFormScreen.tsx`, run the payment save (`savingPayment`, with the amount as detail) and the void (`voidingPayment`) through `useAsyncAction`, with `BusyButton`.
- [X] T046 [P] [US1] In `src/features/ledger/screens/ProductFormScreen.tsx`, run save (`savingProduct`) and remove (`removingProduct`) through `useAsyncAction`, with `BusyButton`.
- [X] T047 [P] [US1] In `src/features/ledger/components/customers/CustomerForm.tsx`, run save (`savingCustomer`) and remove (`removingCustomer`) through `useAsyncAction`, with `BusyButton`. This covers `CustomerFormScreen` and the inline creation in `CustomerPickerCard`. Keep the 003 fix: create first, then call `onSaved(created)`.
- [X] T048 [P] [US1] In `src/features/ledger/screens/TicketDetailScreen.tsx`, run the void (`voidingTicket`) through `useAsyncAction`. Make the confirm button of `src/features/ledger/components/feedback/ConfirmAction.tsx` accept `busy` and use `BusyButton`.
- [X] T049 [US1] Run `grep -rnE "\b(recordSale|createCustomer|updateCustomer|removeCustomer|createProduct|updateProduct|removeProduct|createPayment|updatePayment|voidPayment|updateTicket|voidTicket)\(" src --include=*.tsx` and confirm that every write call in a `.tsx` file is inside a `run({ work })` (FR-002). Then run `npm run validate`.

**Checkpoint**: US1 works with slow mode: busy button, loader timing, slow state, back blocked, no duplicates. Failures are still shown by the screen until US2.

---

## Phase 4: User Story 2 — Every action ends with a clear outcome (Priority: P1)

**Goal**: One toast area under the top bar. A success toast after each write; an error toast that stays, with "Réessayer", while the form keeps its values; swipe right or × to dismiss.

**Independent Test**: Save each kind of record and see its success toast (with "13,150 DT encaissés" for a payment). Force a failure and see "{thing} non enregistré" with "Vos saisies sont gardées." and "Réessayer"; the form keeps its values; the retry saves. Toasts queue one at a time, a repeated success replaces the visible one, and a swipe right dismisses (quickstart scenarios 5–8).

### Tests for User Story 2

- [X] T050 [P] [US2] Extend `src/features/feedback/asyncActionRunner.test.ts`:
  - on success, the success toast is queued after `hideLoader`;
  - on failure, the error toast has `notSavedToast` with the thing, the keep-values message and a "Réessayer" action whose callback re-runs the same spec;
  - the retry calls the latest save handler: after a value changes, the retry saves the new value, not the failed attempt's.
- [X] T051 [P] [US2] Write `src/features/feedback/toastActions.test.ts`: a registered callback runs by id, is removed on dismiss, and an unknown id is a no-op.

### Implementation for User Story 2

- [X] T052 [US2] Create `src/features/feedback/toastActions.ts`, a module-level registry: `registerToastAction(callback) → id`, `runToastAction(id)` and `releaseToastAction(id)`. Callbacks never enter the store (R7).
- [X] T053 [US2] Create `src/features/feedback/toasts.ts` with `showToast({ kind, title, detail?, action?: { label, onPress }, dedupeKey? })`. It registers the action, dispatches `enqueueToast`, and exposes the store dispatch for non-React callers. `dismissToast` releases the registered action.
- [X] T054 [US2] Extend `asyncActionRunner.ts` and `useAsyncAction.ts`:
  - on success, after the loader closes, show `successToast` with `kind: 'success'`, an optional `view` action labelled `t.view`, and `dedupeKey` equal to the toast title;
  - on failure, after the loader closes, show an error toast with `formatMessage(t.notSavedToast, { thing: failureThing })`, the detail `keepValuesMessage`, and a `t.retry` action that calls the screen's latest save handler, kept in a ref updated on every render (not the stored spec, which holds the failed attempt's values);
  - errors are mapped with `getLedgerErrorMessage` and shown as the detail when they are more specific than the generic message (FR-007, FR-008).
- [X] T055 [P] [US2] Create `src/components/ui/Toast.tsx`, a kind-styled card:
  - layout: radius 16, min height 64, soft shadow (class), a 32 icon circle on the start side (check, "!", "i", or a 28 spinner for pending), a 15/700 title, a 13 detail (`text-error-strong` for errors), an optional text action, and a × button with `accessibilityLabel={t.dismiss}` (44 dp targets);
  - colors per kind:
    - success: `bg-paid-soft border-paid-line`, icon `text-paid`;
    - error: `bg-error-soft border-error-line`, icon `text-error`;
    - info: `bg-primary-soft border-primary-line`, icon `text-primary`;
    - pending: `bg-surface border-line`.

  Text wraps at 130%. Icons mirror in Arabic. Reuse the existing token names from 003 where they match.
- [X] T056 [US2] Create `src/components/ui/ToastHost.tsx`. It shows the head of `state.feedback.toasts` at `absolute top-3 inset-x-4` (R15) and:
  - auto-dismisses success after 3 s and info after 4 s; errors and pending stay. The durations come from a pure `toastDurationMs(kind)` in `src/features/feedback/toastDuration.ts` (3000 / 4000 / null), tested in `toastDuration.test.ts`;
  - starts the timer only while the app is active (`AppState`), so a toast for a save that ended in the background is still seen on return (spec edge case);
  - wraps the toast in `GestureDetector` with `Gesture.Fling().direction(Directions.RIGHT)`, which dismisses after the `translate-x-full` exit (R12);
  - uses `animate-toast-in` on enter unless `useReduceMotion()`;
  - is a live region, `polite` or `assertive` for errors (FR-005, FR-006, FR-011).

  Export `Toast` and `ToastHost` from `src/components/ui/index.ts`.
- [X] T057 [US2] In `src/app/_layout.tsx`, wrap the app in `GestureHandlerRootView` (with `cssInterop` for `className` in `src/components/ui/interop.ts`, `flex-1`). Render `<ToastHost />` once, only after the database is ready, inside the content area under the top bar, so the keyboard, the sticky footers and the tab bar never cover it (US2-AS6).
- [X] T058 [US2] Pass the outcome fields from the contract mapping table in each write site:
  - `TicketFormScreen.tsx`: `ticketSavedToast`, plus `amountCollected` when money was received and a `view` action to the ticket detail; failure `thingTicket` / `valuesKept`. The inline product creation uses `productSavedToast`.
  - `PaymentFormScreen.tsx`: `paymentSavedToast` with `amountCollected`, and on void `paymentVoidedToast` / `nothingChanged`.
  - `ProductFormScreen.tsx`: `productSavedToast`, and on remove `productRemovedToast` / `nothingChanged`.
  - `CustomerForm.tsx`: `customerSavedToast`, and on remove `customerRemovedToast` / `nothingChanged`.
  - `TicketDetailScreen.tsx`: `ticketVoidedToast` / `nothingChanged`.
- [X] T059 [US2] Remove the interim write-error banners added or kept in T034 from those screens: write failures are now only toasts. Read errors keep their inline state. Run `npm run validate`.

**Checkpoint**: US1 and US2 together form the shippable MVP. Every write ends with exactly one outcome toast (SC-002), and failures keep the form values (SC-005).

---

## Phase 5: User Story 3 — Lists and figures load without blank screens (Priority: P2)

**Goal**: Skeleton rows on first load; previous values with "Mise à jour…" while refreshing; read errors inline with "Réessayer"; no toast for reads.

**Independent Test**: With slow mode, open each tab and the dashboard: skeletons first, then content. After saving a ticket, the Home figures stay visible with "Mise à jour…". A forced read error shows the inline error with "Réessayer" (quickstart scenarios 9–10).

- [X] T060 [P] [US3] Create `src/components/ui/SkeletonRows.tsx` with props `count` and `variant: 'avatar' | 'tile' | 'stat'`:
  - bars in `bg-skeleton` and `bg-muted`, with `animate-pulse` unless `useReduceMotion()`;
  - the bars are hidden from accessibility, and the group has a single `t.loading` label;
  - the layout mirrors in RTL.
- [X] T061 [P] [US3] Create `src/components/ui/RefreshingPill.tsx`: a small pill with a 12 spinner and `t.updating`, exposed as `accessibilityRole="progressbar"`. Export it and `SkeletonRows` from `src/components/ui/index.ts`.
- [X] T062 [P] [US3] `HomeScreen.tsx`: `SkeletonRows variant="stat"` for the figures and `variant="avatar"` for the top debtors while `loading`, and `RefreshingPill` next to the figures while `refreshing`.
- [X] T063 [P] [US3] `CustomerListScreen.tsx`, `CustomerBalanceScreen.tsx` and `CustomerLedgerScreen.tsx`: `variant="avatar"` skeletons while loading, and the pill in the header or summary while refreshing.
- [X] T064 [P] [US3] `TicketListScreen.tsx`, `PaymentHistoryScreen.tsx`, `LedgerHistoryScreen.tsx` and `TicketDetailScreen.tsx`: skeletons matching their row shape, plus the pill.
- [X] T065 [P] [US3] `ProductCatalogScreen.tsx` (`variant="tile"`) and `CustomerPickerCard.tsx` (`variant="avatar"`): skeletons while loading.
- [X] T066 [US3] In every screen above, show the existing inline error state with `retry` from `useLedgerQuery` when `status === 'error'` and there is no data. Confirm that no read path calls `showToast` (FR-009, US3-AS3/AS4). Run `npm run validate`.

**Checkpoint**: No reading screen shows a blank state while loading.

---

## Phase 6: User Story 4 — Long background work keeps the app usable (Priority: P3)

**Goal**: A pending toast says that work is running and "Vous pouvez continuer.", then turns into success or error in place.

**Independent Test**: In a dev build, trigger the test background task: the pending toast stays while the app remains usable, then the same toast becomes success (quickstart scenario 11).

- [X] T067 [US4] In `src/features/feedback/toasts.ts`, add `showPendingToast({ title, detail })`, which returns an id, and `resolvePendingToast(id, { kind: 'success' | 'error', title, detail?, action? })`, which dispatches `resolvePending`. The resolved toast then follows its kind's duration. Extend `src/features/feedback/store/feedbackSlice.test.ts` to check that the resolved toast keeps its id and position and takes the new kind.
- [X] T068 [US4] Make `ToastHost.tsx` keep a pending toast until it is resolved, and restart the auto-dismiss timer when the kind changes. Make sure the pending toast takes no touches outside its own card, so the screen stays usable (FR-010).
- [X] T069 [US4] Add a test trigger in `src/navigation/TopBar.tsx`, present only when `__DEV__`: a long-press on the title starts a 3 s demo task with `showPendingToast({ title: t.demoBackgroundTask, detail: t.youCanContinue })` and resolves it to success. Release builds do not contain it.

**Checkpoint**: The pending toast is ready for 005 (backup and sync).

---

## Phase 7: Polish & Cross-Cutting Concerns

- [X] T070 [P] Accessibility pass on `Toast`, `ToastHost`, `BlockingLoader`, `BusyButton`, `SkeletonRows` and `RefreshingPill`: labels, roles, busy state, live regions (errors assertive), loader focus, 44 dp targets, and 130% text without clipping (FR-011).
- [X] T071 [P] RTL pass in Arabic: the toast icon is on the start side, the loader and skeletons mirror, and amounts in toasts and the loader keep Latin digits and left-to-right order through `formatMillimeAmount` (FR-012).
- [X] T072 [P] Check the no-style rules: `grep -rnE "StyleSheet|style=\{\{|#[0-9A-Fa-f]{3,8}\b" src --include=*.tsx` returns nothing. Also remove any comment left in the files touched by this feature (CLAUDE.md rule).
- [X] T073 [P] Document slow mode (`EXPO_PUBLIC_LEDGER_DELAY_MS`) and the feedback helpers in `shop-keeper-mobile/README.md`. In the root `CLAUDE.md`, mark 004 as implemented and pending device validation.
- [X] T076 [P] Add a development-only seed in `src/db/devSeed.ts` (2,000 tickets, 300 customers, through the normal services so the operation log stays consistent), reachable only when `__DEV__` from the same long-press menu as T069, for quickstart scenario 15 and SC-001.
- [X] T074 Run `npm run validate` (lint, typecheck, Vitest) and fix any failure.
- [x] T075 Device validation of the 15 scenarios in [quickstart.md](./quickstart.md), including SC-001 with 2,000 tickets, SC-004 with rapid double taps, and sending the app to the background during a slow save (the toast is shown on return). This is the owner's task on a phone. — Closed 2026-10-31 by the owner before moving to the phase 1 backend.

---

## Phase 8: Form validation stays in the form (FR-015, added 2026-10-28)

**Goal**: A missing or invalid field never produces a toast or a loader. Save on an invalid form does not start the action; every field in error is outlined in red with its message, and the first one gets the focus and is scrolled into view. A service validation error is mapped back to its field.

**Independent Test**: On each form, leave a required field empty and tap Save: no toast, no loader, the field is focused with a red outline and its message; filling it clears the error (quickstart scenario 16, SC-008).

### Tests for Phase 8

- [X] T077 [P] Write `src/components/ui/logic/fieldErrors.test.ts` (first field in error follows form order, a checked field clears as soon as it is valid, a reported service error shows until the field changes) and extend `src/i18n/errorMessages.test.ts` for `getFieldValidationKey` (required, amount, quantity, barcode, duplicate barcode, credit without customer, partial amount, payment over balance are field errors; "not found", walk-in and database errors are not).
- [X] T078 [P] Extend `src/features/feedback/asyncActionRunner.test.ts`: when `checkFields()` returns false, `work` is not called and nothing is dispatched (no busy state, loader or toast); when `work` throws and `showFieldError(cause)` returns true, no toast is shown and `onFailure` is not called; any other failure still shows the error toast.

### Implementation for Phase 8

Form validation is app-wide, so its building blocks live in `src/components/ui/` and do not depend on the ledger or the feedback system.

- [X] T079 Create `src/components/ui/logic/fieldErrors.ts` (`FieldErrors`, `ReportedFieldError`, `firstFieldInError`, `displayedFieldError`, `withoutField`). Add `getLedgerErrorKey` and `getFieldValidationKey` to `src/i18n/errorMessages.ts`, so the service error rules stay in one place.
- [X] T080 Extend `AsyncActionSpec` with `checkFields?: () => boolean` and `showFieldError?: (cause) => boolean`, and `RunOptions` with `onStarted`. The runner checks the fields before anything starts and routes field validation failures thrown by `work` to the form instead of `showFailure`. `useAsyncAction` turns the button busy only from `onStarted`.
- [X] T081 Create `src/components/ui/useFieldErrors.ts`, generic over the form's field names: `checkFields`, `errorOf`, `showReportedError` (with a caller-supplied `fieldErrorFor`), `fieldChanged`, `reset` and `register(field)` for refs. It scrolls the first field in error into view (`ScreenScroll` takes a `scrollRef`), focuses it, moves screen-reader focus and announces the message.
- [X] T082 [P] Create `src/components/ui/FieldErrorText.tsx` and switch `.ui-input-error` to the red `error` token. `LedgerField` and `MillimeInput` forward a `ref` and use both; `CustomerPickerCard` takes a `ref` and shows its error the same way. `src/features/ledger/components/forms/ledgerFieldError.ts` maps a ledger service error to a form field.
- [X] T083 [P] Wire `useFieldErrors` into `CustomerForm.tsx` (display label) and `ProductFormScreen.tsx` (barcode, name, price).
- [X] T084 [P] Wire `useFieldErrors` into `PaymentFormScreen.tsx` / `PaymentForm.tsx` (customer, amount) and `TicketFormScreen.tsx` (customer for credit, product lines, partial amount, and the name and price of the "new product" sheet). Save buttons are no longer disabled for missing fields: tapping them points to the field.
- [X] T085 Confirm that no field validation path reaches a toast, add `fieldRequired` and `customerRequired` in `fr`, `ar` and `en`, and run `npm run validate`.
- [x] T086 Device check of quickstart scenario 16 in French and Arabic, with TalkBack on one form. This is the owner's task on a phone, together with T075. — Closed 2026-10-31 by the owner before moving to the phase 1 backend.

**Checkpoint**: Toasts only report outcomes of actions that ran; form validation is handled by the form.

---

## Dependencies & Execution Order

### Phase dependencies

- **Setup (Phase 1)**: none.
- **Foundational (Phase 2)**: needs T001–T005 for strings and tokens. It blocks every story.
  - Inside: T007 → T010 → T011, T012, T013 → T014 → T015 → T016 → T017, T018 → T019; then T022 → T025 → T026–T033 → T034.
  - T009 can run alongside T007.
- **US1 (Phase 3)**: after Phase 2.
- **US2 (Phase 4)**: after US1, because it extends the runner, the hook and the same write sites (T054 on T038–T039, T058 on T044–T048).
- **US3 (Phase 5)**: after Phase 2 only; it can run in parallel with US1 and US2.
- **US4 (Phase 6)**: after US2 (ToastHost and toasts.ts).
- **Polish (Phase 7)**: after all stories.
- **Form validation (Phase 8)**: after US2 (it extends the runner and the write sites). T077 and T078 first, then T079 → T080 → T081; T082–T084 in parallel; then T085. T086 is the owner's device check.

### Story completion order

```text
Setup → Foundational ─┬─ US1 → US2 → US4 ─┬─ Polish
                      └─ US3 ─────────────┘
```

### Delivery constraint

Phase 2 makes the whole data layer async. Everything stays on `004-async-feedback` and merges in a single PR after Polish (plan, "Implementation order").

## Parallel Examples

**Phase 1**: T002, T003, T004 and T006 together.

**Phase 2, after T019 and T025**:
```text
T026 HomeScreen          T027 Customer list/balance   T028 CustomerLedger
T029 Ticket list/detail  T030 Payment/ledger history  T031 ProductCatalog
T032 Form loads          T033 CustomerPickerCard
```

**US1**: T035 and T036 (tests) together, then T040 and T041 (components). After T039, the write sites T045, T046, T047 and T048 can run in parallel, alongside T044.

**US2**: T050, T051 and T055 together.

**US3**: T060 and T061, then T062–T065 in parallel.

## Implementation Strategy

### MVP

1. Phase 1, then Phase 2 (the app reads and saves asynchronously, with today's visuals).
2. US1, then US2: the busy button, loader and outcome toasts. **Stop and validate** with quickstart scenarios 1–8.

### Incremental

3. US3: skeletons and refresh pills (scenarios 9–10).
4. US4: the pending toast (scenario 11).
5. Polish, `npm run validate`, then one PR to `main`.

## Notes

- T034 is a temporary bridge: write sites `await` their services and keep their banners until US1 and US2 replace them. It lets Phase 2 end with a working app.
- Unlike the contract text, the hook delegates to a framework-free `asyncActionRunner.ts`, so the timing and retry rules are unit-testable without rendering React. `useAsyncAction`'s public API is unchanged.
- No new dependency. `react-native-gesture-handler` and `react-native-reanimated` are already installed.
