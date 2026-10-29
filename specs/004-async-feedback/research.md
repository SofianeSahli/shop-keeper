# Research: Async Services and App-wide Feedback (004)

Every Technical Context unknown is resolved below. Format: **Decision**, **Rationale**, **Alternatives considered**.

## Data access

### R1. Asynchronous SQLite calls
- **Decision**: Replace every `runSync` / `getFirstSync` / `getAllSync` / `execSync` / `withTransactionSync` with the `…Async` equivalents of expo-sqlite (SDK 58): `runAsync`, `getFirstAsync`, `getAllAsync`, `execAsync`, `withTransactionAsync`. Inventory: 32 calls in 9 files (`ticketRepository` 11, `ledgerRepository` 7, `migrations` 3, `languageStorage` 2, `pendingChangeService` 2, `paymentRepository` 2, `paymentRecords` 2, `auditService` 2, `db/index` 1). Every repository and service function that touches the database becomes `async` and returns a `Promise`; signatures otherwise stay the same.
- **Rationale**: Sync calls run on the JavaScript thread and freeze the interface (spec FR-001). Without async calls, no loader can render while a save runs.
- **Alternatives**: keep sync calls and wrap them in `setTimeout` (rejected: still blocks the thread while the query runs); move SQLite to a worker (rejected: not supported by expo-sqlite on native).

### R2. One serial queue for database work
- **Decision**: Add a module `src/db/queue.ts` with `runQueued(task)`. It runs database tasks one after another (a promise chain). Every repository call goes through it. Writes run `withTransactionAsync` inside their queued task, so no other query can interleave with a transaction.
- **Rationale**: `withTransactionAsync` is not exclusive. Another async query started meanwhile would run inside the open transaction. `withExclusiveTransactionAsync` is not supported on web, and makes concurrent writes fail with `database is locked`. A single-user shop app does a few queries per screen, so serialising costs nothing visible.
- **Alternatives**: `withExclusiveTransactionAsync` on native only (rejected: two code paths, and lock errors to handle); a second read-only connection (rejected: more complexity than the scale needs).

### R3. Transactions keep their scope
- **Decision**: Functions that write inside a transaction (`insertTicketRecords`, `insertPaymentRecords`, `voidPaymentRecords`, `appendAuditEvent`, `persistPendingChange`, `ensureWalkInCustomer`) become `async` and keep receiving the database handle used by the transaction. `withLedgerTransaction(operation, db)` becomes `async` and awaits the operation inside `withTransactionAsync`. A thrown error still rolls the whole transaction back.
- **Rationale**: FR-001 requires 001–003 rules unchanged: one transaction per user action, with the operation-log entry and the audit event.

### R4. Startup and language
- **Decision**: `openLedgerDatabase` already returns a promise; its migrations switch to async calls. `getSavedLanguage` / `saveLanguage` (`app_settings`) become async. The root layout awaits the language before leaving the startup screen, as today.
- **Rationale**: Removes the last sync calls. The startup screen and its error state are unchanged (spec edge case).

### R5. Tests keep a real database
- **Decision**: Extend `src/test/ledgerTestDb.ts` (Node's `node:sqlite` adapter) with the async methods (`runAsync`, `getFirstAsync`, `getAllAsync`, `execAsync`, `withTransactionAsync`), implemented over the same synchronous Node calls. Existing tests become `async` / `await`; their assertions do not change (spec SC-007). The tests that mock the database (`crudServices`, `paymentRepository`) keep their mocks, adapted to async.
- **Rationale**: Proves rollbacks and operation-log entries for real, as in 003.

### R6. Test delay setting
- **Decision**: `src/db/queue.ts` reads `EXPO_PUBLIC_LEDGER_DELAY_MS`, honoured only when `__DEV__` is true. When set, each queued task waits that long first. It is off by default and has no effect in release builds.
- **Rationale**: The spec's independent tests and SC-003 need slow actions to check the 300 ms / 500 ms / 2 s rules on a device.
- **Alternatives**: a hidden settings screen (rejected: UI and strings for a developer tool).

## Feedback state

### R7. Where the toast queue and loader state live
- **Decision**: Add a Redux Toolkit slice `feedback`, in `src/features/feedback/store/feedbackSlice.ts`:
  - `toasts`: a queue whose head is the visible toast;
  - `loader`: `null` or `{ label, detail, shownAt, slow }`.
  Only serializable data goes in the store. Toast actions ("Voir", "Réessayer") are referenced by id, and the callbacks live in a module-level registry (`toastActions.ts`) that is cleaned when the toast closes.
- **Rationale**: The constitution makes Redux Toolkit the standard for shared client state. Redux requires serializable state, hence the registry.
- **Alternatives**: a React context (rejected: a second state mechanism next to Redux); putting functions in the store (rejected: breaks the serializability check).

### R8. One helper drives every write
- **Decision**: Add `useAsyncAction()` in `src/features/feedback/useAsyncAction.ts`. It returns `{ run, busy }`. `run(spec)` takes:
  - `{ work, loaderLabel, loaderDetail?, successToast?, failureThing, keepValuesMessage }`.
  It sets `busy` immediately and starts the loader timeline (R9). When the work ends, it shows the outcome toast and maps errors with `getLedgerErrorMessage`. It returns the result, or `undefined` on failure. "Réessayer" calls the screen's latest save handler, which the hook keeps in a ref updated on every render, so the retry builds a new spec from the current form values (a stored closure would replay the failed attempt's values). A second `run` while `busy` is ignored (FR-003, SC-004).
- **Rationale**: FR-002: screens never write their own loading logic. Services stay UI-free: the helper lives in the presentation layer and calls them.

### R9. Loader timing as a pure state machine
- **Decision**: Put the timing rules in a pure, unit-tested module `loaderTimeline.ts`:
  - show at 300 ms;
  - keep for at least 500 ms once shown;
  - mark "slow" at 2 s after showing;
  - on finish, delay the outcome until the minimum display time has passed.
  The hook drives it with `setTimeout`. Tests use Vitest fake timers.
- **Rationale**: These are the riskiest rules (flicker, SC-003); a pure module makes them testable without rendering.

### R10. Reads: `useLedgerQuery`
- **Decision**: Add `useLedgerQuery(load, deps)` in `src/features/feedback/useLedgerQuery.ts`. It returns `{ data, status: 'loading' | 'refreshing' | 'ready' | 'error', error, retry }`.
  - It keeps the previous `data` while refreshing (FR-009).
  - It refreshes on focus and on the existing `refreshVersion` signal.
  - It ignores results that arrive after the screen unmounted or after a newer request.
- **Rationale**: Replaces the sync `useMemo` / `useState` loaders on 13 screens and components with one pattern.

## Interface and motion

### R11. Animations with classes only
- **Decision**: Use NativeWind 4 class animations and transitions, which run on Reanimated (already installed):
  - toast enter: a custom `toast-in` keyframe in `tailwind.config.js`, 220 ms;
  - toast exit: a `translate-x-full` transition;
  - skeleton: `animate-pulse`.
  When `AccessibilityInfo.isReduceMotionEnabled()` is true, the components omit the animation classes (FR-011).
- **Rationale**: The constitution forbids `style` in TSX. Reanimated's `useAnimatedStyle` would need a `style` prop.
- **Alternatives**: Reanimated animated styles (rejected: `style` prop); no animation (rejected: the design specifies slide and pulse).

### R12. Swipe right to dismiss
- **Decision**: Use a `Gesture.Fling().direction(Directions.RIGHT)` from react-native-gesture-handler around the toast; on fling, run the exit transition and dismiss. Add `GestureHandlerRootView` (with `cssInterop` for `className`) at the app root in `src/app/_layout.tsx`, since Expo Router does not provide one at the root. The × button stays as the accessible alternative.
- **Rationale**: Spec FR-005 (swipe right, owner change of 2026-10-27). A fling is a single gesture with no position tracking, so no animated `style` is needed.

### R13. Spinners
- **Decision**: Use React Native `ActivityIndicator` with a token color class (already mapped by NativeWind). Sizes: `small` for buttons and the refreshing pill, `large` for the loader. The "slow" state uses the `partial` (amber) token.
- **Rationale**: Native and accessible, and no new dependency.

### R14. Blocking loader
- **Decision**: Use a single `<BlockingLoader />` rendered once at the root. It is a transparent `Modal` with the `scrim` backdrop, `onRequestClose` doing nothing (blocks Android back), `accessibilityViewIsModal`, and `accessibilityLiveRegion` on its title.
- **Rationale**: FR-004; a `Modal` blocks touches on everything below it.

### R15. Toast placement
- **Decision**: Render `<ToastHost />` once, inside the content area under the top bar, absolutely positioned at `top-3 inset-x-4`. It is outside screens, so the keyboard, fixed footers and the tab bar never cover it.
- **Rationale**: FR-005. Being under the top bar keeps it clear of the status bar and notch in every layout.

### R16. Colors
- **Decision**: Add these tokens to `tailwind.config.js`, with values from the design note:
  - `error #A3221A`, `error-soft #FDE8E6`, `error-line #F4BDB7`, `error-strong #6B1F18`;
  - `paid-line #A9DCD2`, `primary-line #B7CDEB`;
  - `skeleton #E6EBE9` (`skeleton-soft` reuses `muted`).
  `InfoBanner`'s error tone moves from the debt colors to the new `error` family.
- **Rationale**: FR-013; red stays distinct from the orange used for debt.

## Scope and governance

### R17. Constitution
- **Decision**: No amendment. The work stays in `shop-keeper-mobile`. Offline-first (VI) is unchanged: same transactions and operation log, and no network. Redux Toolkit holds the new shared state, NativeWind the styles. No new framework is added (`react-native-gesture-handler` and `react-native-reanimated` are already dependencies used by Expo Router).
- **Rationale**: See the plan's Constitution Check.
