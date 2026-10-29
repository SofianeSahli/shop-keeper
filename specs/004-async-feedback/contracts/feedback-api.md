# Feedback API Contract (004)

The presentation-layer API that screens use. Location: `shop-keeper-mobile/src/features/feedback/`. Services never import it.

## `useAsyncAction(buildSpec)`

The screen passes a function that builds the spec from its current values. The hook keeps the latest one, so "Réessayer" always uses what is in the form now.

```ts
const save = useAsyncAction(() => ({
  work: () => recordSale(buildTicketFromDraft(...), choice),   // returns a Promise
  loaderLabel: t.savingTicket,                                  // "Enregistrement du ticket…"
  loaderDetail: '4 articles · 13,150 DT',                        // optional
  successToast: { title: t.ticketSavedToast, detail: formatMessage(t.amountCollected, …), view?: () => … },
  onSuccess: (ticket) => router.replace(...),                   // navigation, also after a retry
  onFailure: (cause) => …,                                      // optional: inline error inside a sheet
  failureThing: t.thingTicket,                                  // fills "{thing} non enregistré"
  keepValuesMessage: t.valuesKept,                              // or t.nothingChanged
}));

<BusyButton busy={save.busy} onPress={() => void save.run()} … />
```

One write runs at a time in the whole app (`feedback.actionRunning`); the back guard blocks leaving a screen while it is true.

| Rule | Spec |
|---|---|
| A second `run` while any write runs returns `undefined` and does nothing; the check is synchronous. | FR-003, SC-004 |
| The loader appears only after 300 ms, stays at least 500 ms, and turns "slow" at 2 s. | FR-004 |
| On success: the loader closes, `onSuccess` runs, then the success toast shows. `run` resolves with the work's result. | FR-007 |
| On failure: the loader closes, then the error toast shows with "Réessayer" (calls the screen's latest save handler, so the current form values are used) and ×. `run` resolves `undefined`. The error is mapped with `getLedgerErrorMessage`. | FR-008 |
| Retry from the toast re-enters the same busy/loader flow. | US2-AS4 |

## `useLedgerQuery(load, deps)`

```ts
const { data, status, error, retry } = useLedgerQuery(() => listDebtorSummaries(), []);
```

| Rule | Spec |
|---|---|
| First load: `status = 'loading'`; screens render skeletons. | FR-009 |
| Refresh on focus and on `refreshVersion`: `status = 'refreshing'`, `data` kept; screens show `RefreshingPill` next to the figures. | FR-009 |
| Failure: `status = 'error'`; screens show the 003 inline error state with `retry`. No toast. | FR-009 |
| A late result from an older request or an unmounted screen is dropped. | — |

## Toast functions (thin wrappers over the slice)

```ts
showToast({ kind: 'success' | 'info' | 'error', title, detail?, action?: { label, onPress }, dedupeKey? })
const id = showPendingToast({ title, detail: t.youCanContinue })
resolvePendingToast(id, { kind: 'success' | 'error', title, detail?, action? })
```

## Components (`src/components/ui/`)

| Component | Contract |
|---|---|
| `ToastHost` | Rendered once in `_layout.tsx`, inside the content area under the top bar (`absolute top-3 inset-x-4`). Shows the head of the queue. Swipe right or × dismisses; the action button calls the registered callback. `accessibilityLiveRegion` is `polite` (`assertive` for errors). Icon and text mirror in Arabic. |
| `Toast` | Kind-styled card: radius 16, min height 64, 32 icon circle, title 15/700, detail 13, action text button with a 44 dp target. |
| `BlockingLoader` | Rendered once at the root; reads `feedback.loader`. Transparent `Modal` over the `scrim`; `onRequestClose` does nothing. Card: radius 20, 44 spinner, title 17/700, detail 13.5, note 12.5 (`dataStaysOnPhone`). In the slow state: amber spinner, `takingLonger` + `doNotCloseApp`. |
| `BusyButton` | Wraps the existing button classes. Takes `busy`, `label` and `busyLabel` (default `t.saving`). Busy: disabled, opacity 0.85, small spinner + `busyLabel`, `accessibilityState={{ busy: true, disabled: true }}`. |
| `SkeletonRows` | `count`, `variant: 'avatar' \| 'tile' \| 'stat'`. Bars in `skeleton` / `muted`, with `animate-pulse` unless reduce motion is on. Hidden from accessibility, with one "Chargement…" label for the group. |
| `RefreshingPill` | Small pill: 12 spinner + `t.updating`. |

## Mapping from the spec's action table

| Screen action | `loaderLabel` | Success toast | `failureThing` / keep message |
|---|---|---|---|
| Save ticket (new) | `savingTicket` | `ticketSavedToast` + `amountCollected` when money was received | `ticket` / `valuesKept` |
| Save ticket (edit) | `savingTicket` | `ticketSavedToast` | `ticket` / `valuesKept` |
| Save payment | `savingPayment` | `paymentSavedToast` + `amountCollected` | `payment` / `valuesKept` |
| Save product | `savingProduct` | `productSavedToast` | `product` / `valuesKept` |
| Save customer (screen or inline) | `savingCustomer` | `customerSavedToast` | `customer` / `valuesKept` |
| Void ticket | `voidingTicket` | `ticketVoidedToast` | `ticket` / `nothingChanged` |
| Void payment | `voidingPayment` | `paymentVoidedToast` | `payment` / `nothingChanged` |
| Remove product | `removingProduct` | `productRemovedToast` | `product` / `nothingChanged` |
| Remove customer | `removingCustomer` | `customerRemovedToast` | `customer` / `nothingChanged` |
| Inline product creation (ticket form) | `savingProduct` | `productSavedToast` | `product` / `valuesKept` |
