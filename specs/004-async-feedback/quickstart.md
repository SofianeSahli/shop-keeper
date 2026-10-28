# Quickstart: Validate Async Services and App-wide Feedback (004)

## Prerequisites

- Branch `004-async-feedback`, run from `shop-keeper-mobile/`.
- A development build or Expo Go for **SDK 58**; start with `npx expo start -c` after Tailwind changes.
- Demo data as in the 003 quickstart (Ali, Sonia, Hédi; Lait, Pain, Huile).
- **Slow mode** for timing checks: `EXPO_PUBLIC_LEDGER_DELAY_MS=1200 npx expo start -c` (dev builds only). Use `3000` for the "slow" message. Leave it unset for normal checks.

## 1. Automated gate

```bash
npm run validate
```

Expected: lint, typecheck and tests all green. New tests cover:
- `loaderTimeline` (300 / 500 / 2000 ms with fake timers);
- the `feedback` slice (queue, dedupe, pending → success/error);
- `useAsyncAction` logic (double run ignored, retry, error mapping);
- `runQueued` (order, no interleaving, delay);
- the async repositories on the real in-memory SQLite (same 003 assertions, now awaited).

## 2. Scenarios

| # | Spec | Steps | Expected |
|---|---|---|---|
| 1 | US1-AS1, AS2 | Normal mode: save a ticket. | Button shows "Enregistrement…" at once; no loader; back to the list with "Ticket enregistré". |
| 2 | US1-AS3 | Slow mode 1200: save a ticket with 4 items. | Loader after ~0.3 s: "Enregistrement du ticket…", "4 articles · …", "Vos données restent sur ce téléphone."; Android back does nothing; then success toast. |
| 3 | US1-AS4 | Slow mode 3000: save. | After the loader has shown for 2 s: amber spinner, "Ça prend plus de temps que prévu", "Ne fermez pas l'application." |
| 4 | US1-AS5, SC-004 | Slow mode: double-tap Save quickly (10 times on each form). | Exactly one record each time. |
| 5 | US2-AS1 | Record a payment of 13,150. | Back to the previous screen; toast "Paiement enregistré" · "13,150 DT encaissés". |
| 6 | US2-AS3, AS4 | Force a failure (e.g. a partial amount the service rejects, or a test hook), then fix it and tap "Réessayer". | Error toast stays with "Réessayer" and ×; the form keeps every value; the retry saves. |
| 7 | US2-AS5 | Create three products quickly. | Toasts one after another; a repeated success of the same kind replaces the visible one. |
| 8 | US2-AS6 | Open the keyboard on the ticket form, trigger a toast. | Toast fully visible under the top bar; swipe right dismisses it. |
| 9 | US3 | Slow mode: open each tab and the dashboard. | Skeleton rows first, then content; after saving a ticket, Home figures stay with "Mise à jour…". |
| 10 | US3-AS3 | Slow mode with a forced read error. | Inline error with "Réessayer"; no toast. |
| 11 | US4 | Dev-only background task trigger. | Pending toast with "Vous pouvez continuer."; the screen stays usable; the toast becomes success. |
| 12 | FR-011 | TalkBack / VoiceOver on scenarios 1, 2 and 6. | Busy button announced busy; loader and toasts announced (errors assertively). |
| 13 | FR-011 | Turn on "Remove animations" / "Reduce motion". | No slide or pulse; everything still appears. |
| 14 | FR-012 | Repeat 2, 5 and 6 in Arabic. | Mirrored layout; Arabic wording from the design note; Latin digits. |
| 15 | SC-001 | Seed 2,000 tickets / 300 customers (dev script), scroll the ticket list while saving in slow mode. | Scrolling stays smooth; no tap blocked > 100 ms outside the loader. |
| 16 | FR-015, SC-008 | On each form (ticket, payment, product, customer), leave a required field empty and tap Save; then fill it. | No toast, no loader; the empty field is focused, scrolled into view, outlined in red with its message; the outline goes away once filled. |
