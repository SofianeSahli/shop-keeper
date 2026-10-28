# Data Model: Async Services and App-wide Feedback (004)

**No stored data changes.** No table, column or migration is added. The entities below are client state in Redux or in hooks, never written to SQLite.

## 1. Toast (Redux slice `feedback.toasts`)

| Field | Type | Rule |
|---|---|---|
| `id` | string | Unique per toast. |
| `kind` | `'success' \| 'info' \| 'error' \| 'pending'` | |
| `title` | string | Already translated: what happened ("Ticket enregistré"). |
| `detail` | string \| null | Figure or consequence ("13,150 DT encaissés", "Vos saisies sont gardées."). |
| `actionId` | string \| null | Key into the action registry (callbacks are not stored in Redux). |
| `actionLabel` | string \| null | "Voir" or "Réessayer". |
| `dedupeKey` | string \| null | A new success with the same key replaces a visible one instead of queueing (spec edge case). |

**Durations**: success 3 s · info 4 s · error until action or × · pending until resolved.

**Queue rules**:
- The head of the queue is visible and the rest wait.
- `resolvePending(id, outcome)` changes a pending toast in place.
- Dismissing a toast removes it and frees its action from the registry.

**State transitions**: `queued → visible → dismissed`; pending: `visible → (success | error)` in place.

## 2. Blocking loader (Redux slice `feedback.loader`)

| Field | Type | Rule |
|---|---|---|
| `label` | string | Action name ("Enregistrement du ticket…"). |
| `detail` | string \| null | Object ("4 articles · 13,150 DT"). |
| `shownAt` | number | Epoch ms when it became visible. |
| `slow` | boolean | True after 2 s visible. |

There is at most one loader. `null` means it is hidden.

## 3. Loader timeline (pure, `loaderTimeline.ts`)

| Constant | Value |
|---|---|
| `SHOW_AFTER_MS` | 300 |
| `MIN_VISIBLE_MS` | 500 |
| `SLOW_AFTER_MS` | 2000 |

Events and results:
- `start(t0)` → schedule show at `t0 + 300`.
- `finish(t)`: if not shown, the outcome is immediate and the loader never appears. If shown, the outcome is at `max(t, shownAt + 500)`.
- `tick(t ≥ shownAt + 2000)` → `slow = true`.

## 4. Async action (hook state, `useAsyncAction`)

| Field | Type | Rule |
|---|---|---|
| `busy` | boolean | True from tap to outcome; blocks a second `run`. |
| spec | `AsyncActionSpec` | `work`, `loaderLabel`, `loaderDetail?`, `successToast?` (`title`, `detail?`, `view?` callback), `failureThing`, `keepValuesMessage` ("Vos saisies sont gardées." or "Rien n'a été modifié."). |

`status` goes `idle → running → (slow) → succeeded | failed`.

## 5. Query state (hook state, `useLedgerQuery`)

| Field | Type | Rule |
|---|---|---|
| `data` | T \| undefined | Kept during a refresh. |
| `status` | `'loading' \| 'refreshing' \| 'ready' \| 'error'` | `loading` only when there is no data yet. |
| `error` | string | Mapped with `getLedgerErrorMessage`. |
| `retry` | () => void | Re-runs the load. |

Stale results, from an older request or an unmounted screen, are dropped.
