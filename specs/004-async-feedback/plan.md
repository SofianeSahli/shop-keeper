# Implementation Plan: Async Services and App-wide Feedback

**Branch**: `004-async-feedback` | **Date**: 2026-10-27 | **Spec**: [spec.md](./spec.md) | **Design**: [async-feedback.md](../../shop-keeper-mobile/docs/design/async-feedback.md)

**Input**: Feature specification from `specs/004-async-feedback/spec.md`

## Summary

The work has three parts:

1. **Asynchronous data access.** Every ledger read and write moves from expo-sqlite's synchronous calls to its asynchronous ones, through one serial queue so transactions never interleave. Business rules, transactions and the operation log stay exactly as in 001–003.
2. **App-wide feedback**, rendered once at the root: a Redux `feedback` slice holds the toast queue and the blocking-loader state, read by a `ToastHost` and a `BlockingLoader`.
3. **Two hooks used by every screen.** `useAsyncAction` drives the busy button, the loader timeline (300 ms / 500 ms / 2 s), the outcome toast and error mapping for every write. `useLedgerQuery` gives reads skeletons on first load and keeps previous values while refreshing.

## Technical Context

**Language/Version**: TypeScript 6 (strict), React 19.3, React Native 0.88, Expo SDK 58.

**Primary Dependencies**: Expo Router, expo-sqlite (async API), Redux Toolkit, NativeWind 4 (class animations via Reanimated), react-native-gesture-handler (fling to dismiss). **No new dependency**: Reanimated and gesture-handler are already installed and used by Expo Router.

**Storage**: Unchanged SQLite schema `shop_keeper_ledger.db`. No migration. Only the calling convention changes (async).

**Testing**:
- Vitest with fake timers for the loader timeline and the toast queue.
- The real in-memory SQLite adapter (`src/test/ledgerTestDb.ts`, extended with async methods) for the repositories.
- Existing 001–003 tests converted to `await` with unchanged assertions.
- Manual device checks in [quickstart.md](./quickstart.md), with a dev-only delay setting.

**Target Platform**: Android and iOS phones; web build kept working (no `withExclusiveTransactionAsync`, which web lacks).

**Project Type**: Expo mobile app in the monorepo (`shop-keeper-mobile/`).

**Performance Goals**: No tap or scroll blocked for more than 100 ms during reads or writes (SC-001). Busy feedback within 100 ms of a tap (SC-002).

**Constraints**:
- 300 ms / 500 ms / 2 s loader rules.
- One toast and one loader for the whole app.
- No `style`, `StyleSheet` or hex values in TSX (animations by classes).
- FR/AR/EN strings, RTL mirroring, reduce-motion support, touch targets of at least 44 dp.

**Scale/Scope**:
- 32 sync calls in 9 files.
- 13 reading screens and components.
- 14 write call sites.
- 6 new UI components.
- 2 hooks, 1 Redux slice and 1 queue module.
- About 30 new translation keys.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle / rule | Status | Notes |
|---|---|---|
| I. Monorepo boundaries | PASS | Mobile only. |
| II. Accessible, trustworthy UI; loading/error states | PASS | The feature *is* the loading, success and error states (FR-002 to FR-011). |
| II. No CSS/StyleSheet/inline style in TSX | PASS | Animations use NativeWind classes (research R11); the swipe is a fling gesture with no animated style (R12); colors are tokens (R16). The ESLint rule from 003 keeps enforcing it. |
| II. Reusable components without speculative abstraction | PASS | Each new component is used by several screens; the pending toast (P3) is in the approved design and used by 005. |
| III. i18n AR/FR, RTL | PASS | Every string in fr/ar/en (wording from the design note); toasts and loader mirror; numbers follow FR-022 of 003. |
| IV. Backend | N/A | No server work. |
| V. Testability | PASS | Pure loader timeline and slice, and real-DB repository tests; `npm run validate` gates every phase. |
| VI. Offline-first, operation log | PASS (with an accepted deviation) | Writes still go to SQLite first, with audit and `pending_changes` in the same transaction (R3). No network code. **Accepted deviation**: the local-only exception names 001 and 002 only; like 003, 004 still does not sync. The owner kept this on purpose (2026-10-27, "keep it as it is"); sync ships with the backend in 005, which will amend the constitution. |
| Additional constraints: Redux Toolkit for shared state | PASS | Toast queue and loader live in a `feedback` slice; callbacks kept outside the store (R7). |
| Additional constraints: no new framework | PASS | Only installed packages; no new state or styling system. |
| Money as integer millimes | PASS | Unchanged; amounts in toasts use `formatMillimeAmount`. |

**Post-design re-check**: no violation. The serial queue (R2) adds no persistence; the dev delay (R6) is inert in release builds.

## Phase 0 / Phase 1 outputs

- [research.md](./research.md): decisions R1–R17.
- [data-model.md](./data-model.md): client state only (toast, loader, loader timeline, action, query).
- [contracts/feedback-api.md](./contracts/feedback-api.md): `useAsyncAction`, `useLedgerQuery`, the toast functions, the components and the action → feedback mapping.
- [contracts/async-data-access.md](./contracts/async-data-access.md): which functions become async, and the queue rules.
- [quickstart.md](./quickstart.md): 15 device scenarios, including slow mode.

## Project Structure

### Documentation (this feature)

```text
specs/004-async-feedback/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── feedback-api.md
│   └── async-data-access.md
├── checklists/requirements.md
└── tasks.md            # /speckit-tasks
```

### Source Code (mobile application)

```text
shop-keeper-mobile/
├── tailwind.config.js                  # + error/paid-line/primary-line/skeleton tokens, toast-in keyframes
├── src/
│   ├── app/_layout.tsx                 # GestureHandlerRootView, ToastHost, BlockingLoader, async language
│   ├── db/
│   │   ├── index.ts                    # async withLedgerTransaction
│   │   ├── queue.ts (+ test)           # NEW runQueued + dev delay
│   │   └── migrations.ts               # async calls
│   ├── test/ledgerTestDb.ts            # + async methods
│   ├── i18n/languageStorage.ts         # async
│   ├── components/ui/
│   │   ├── Toast.tsx  ToastHost.tsx  BlockingLoader.tsx
│   │   ├── BusyButton.tsx  SkeletonRows.tsx  RefreshingPill.tsx
│   │   ├── useReduceMotion.ts
│   │   └── InfoBanner.tsx              # error tone → error tokens
│   └── features/
│       ├── feedback/                   # NEW presentation-layer feedback
│       │   ├── store/feedbackSlice.ts (+ test)
│       │   ├── toastActions.ts         # callback registry
│       │   ├── toasts.ts               # showToast / showPendingToast / resolvePendingToast
│       │   ├── loaderTimeline.ts (+ test)
│       │   ├── asyncActionRunner.ts (+ test)  # framework-free timing, retry and outcome logic
│       │   ├── useAsyncAction.ts
│       │   └── useLedgerQuery.ts
│       └── ledger/
│           ├── services/*.ts           # async (contract: async-data-access.md)
│           ├── services/*.test.ts      # awaited, same assertions
│           ├── screens/*.tsx           # useLedgerQuery + useAsyncAction + BusyButton/Skeletons
│           └── components/…            # CustomerPickerCard, CustomerForm, payment sheets: async
```

**Structure decision**: The feedback logic gets its own feature folder (`src/features/feedback/`), separate from the ledger: future features (sync, backup, reminders) will use it too. The visual pieces join `src/components/ui/`. Services stay UI-free.

## Implementation order

1. Tokens and strings.
2. `runQueued` + async test adapter.
3. Async data layer, service by service, with tests kept green after each one.
4. Feedback slice, timeline and hooks (tests).
5. UI components.
6. Root wiring.
7. Screens:
   - writes: ticket form → payment → product → customer → voids and removals;
   - reads: Home → lists → ledger → detail.
8. Pending toast.
9. Translations and accessibility audit, then validate.

The async conversion lands before any screen work. Until the screens move to `useLedgerQuery`, they cannot read data, so phases 3 to 7 stay on this branch and ship together.

## Complexity Tracking

| Item | Why needed | Simpler alternative rejected because |
|---|---|---|
| Serial database queue (`runQueued`) | `withTransactionAsync` is not exclusive: concurrent async queries would run inside an open transaction | `withExclusiveTransactionAsync` is unsupported on web and throws `database is locked` on concurrent writes |
| Toast action registry outside Redux | Redux state must be serializable; "Réessayer" and "Voir" are callbacks | A React context would add a second shared-state mechanism, against the constitution's Redux Toolkit standard |
