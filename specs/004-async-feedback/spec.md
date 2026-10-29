# Feature Specification: Async Services and App-wide Feedback

**Feature Branch**: `004-async-feedback`

**Created**: 2026-10-27

**Status**: Draft

**Input**: User description: "Start spec 004: we need loaders; follow the approved design." Design source: [async-feedback.md](../../shop-keeper-mobile/docs/design/async-feedback.md), the approved "Retours d'état — toasts et chargement" row of the "Carnet Kridi — Mobile UI" design canvas (`FeedbackKit`, `FeedbackFlow`).

## Context

Today every ledger read and write runs synchronously: while the phone saves a ticket or loads a list, the whole app freezes and the owner cannot tell whether anything is happening. This feature makes data access asynchronous and gives the app one consistent way to show what is running and how it ended: a busy button, one blocking loader for slow writes, one toast for outcomes, and skeleton rows while lists load.

Behaviour, data and storage rules from 001–003 stay the same: data is still saved on the phone first with its operation-log entry, in the same transactions. Only *when* the screen learns the result changes.

## Clarifications

### Session 2026-10-28

- Q: Should a missing or invalid field in a form show an error toast? → A: No. Form validation is not part of the toast and feedback system. The form handles it itself: the first field in error gets the focus, is outlined in red and shows its message under it. Toasts are only for outcomes of actions that actually ran (FR-015).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Saving never freezes and always shows progress (Priority: P1)

When the owner saves a ticket, a payment, a product or a customer, the button they tapped immediately shows that it is working and cannot be tapped twice. If the save takes noticeably long, a loader names what is being saved ("Enregistrement du ticket…", "4 articles · 13,150 DT") and blocks the screen until it ends. The same applies to voiding a ticket or a payment and removing a product or a customer.

**Why this priority**: Saving is the core action of the app. A frozen screen makes owners tap again (duplicates) or think the sale was lost.

**Independent Test**: Slow the save down artificially (test setting) and save a ticket: the button shows "Enregistrement…" at once, the loader appears after 300 ms naming the ticket, stays at least 500 ms, and a second tap creates nothing.

**Acceptance Scenarios**:

1. **Given** a valid ticket form, **When** the owner taps Save, **Then** within 100 ms the button shows a spinner and "Enregistrement…", is disabled and is announced as busy.
2. **Given** a save that finishes in less than 300 ms, **When** it ends, **Then** no blocking loader ever appears (no flicker).
3. **Given** a save that is still running after 300 ms, **When** the loader appears, **Then** it names the action and its object, blocks touches and the Android back button, offers no cancel, and stays on screen at least 500 ms.
4. **Given** the loader has been visible for 2 seconds, **When** the save is still running, **Then** it shows "Ça prend plus de temps que prévu" and "Ne fermez pas l'application." with a warning-colored spinner.
5. **Given** the owner taps Save twice quickly, **When** both taps are processed, **Then** exactly one record is written.

---

### User Story 2 - Every action ends with a clear outcome (Priority: P1)

After a save or a removal, the owner sees a short message at the top of the screen saying what happened ("Ticket enregistré", "13,150 DT encaissés"). If something fails, an error message stays on screen, says that nothing was saved and that the entered values are kept, and offers "Réessayer".

**Why this priority**: Owners with limited digital experience need confirmation without checking lists. Failures must never lose what was typed.

**Independent Test**: Save each kind of record and check the success message and return to the previous screen; force a failure and check the error message, the kept values and that "Réessayer" saves successfully once the cause is removed.

**Acceptance Scenarios**:

1. **Given** a save that succeeds, **When** it ends, **Then** the app returns to the previous screen and shows a success toast for 3 seconds with what happened and, when useful, a figure (amount collected, number of items) and a "Voir" action.
2. **Given** a void or removal that succeeds, **When** it ends, **Then** a success toast confirms it.
3. **Given** a save that fails, **When** it ends, **Then** the loader closes, an error toast says "{thing} non enregistré" with "Vos saisies sont gardées." and stays until the owner taps "Réessayer" or ×; the form still holds every value.
4. **Given** the error toast, **When** the owner taps "Réessayer", **Then** the same action runs again with the same values, with the same busy and loader behaviour.
5. **Given** two outcomes in a row, **When** the second arrives while the first toast is visible, **Then** toasts show one at a time, in order.
6. **Given** a toast is visible, **When** the keyboard is open or a fixed form footer is shown, **Then** the toast is still fully visible at the top, under the top bar, and can be swiped right to dismiss.
7. **Given** a form with a required field left empty or an invalid value, **When** the owner taps Save, **Then** no toast and no loader are shown, the action does not start, the first field in error is scrolled into view and focused, outlined in red, and shows its message under it; the other values stay as typed.

---

### User Story 3 - Lists and figures load without blank screens (Priority: P2)

When the owner opens the dashboard, a list or a customer ledger, placeholder rows appear immediately and are replaced by the content. When data refreshes after a change, the previous values stay visible with a small "Mise à jour…" indicator instead of disappearing.

**Why this priority**: Reads become asynchronous with this feature; without placeholders, screens would flash empty.

**Independent Test**: Slow reads down (test setting), open each list and the dashboard: skeleton rows show first, then content; after saving a ticket, the dashboard figures stay visible with "Mise à jour…" until refreshed.

**Acceptance Scenarios**:

1. **Given** a list opened for the first time, **When** its data is loading, **Then** skeleton rows matching the list's row shape are shown, with a gentle pulse (no pulse when the system asks to reduce motion).
2. **Given** a screen already showing data, **When** it refreshes, **Then** the previous values stay and a "Mise à jour…" indicator shows next to the figures being refreshed.
3. **Given** a read that fails, **When** the screen has nothing to show, **Then** the existing inline error state with "Réessayer" is shown (no toast for reads).
4. **Given** reads, **When** they succeed, **Then** no toast is shown.

---

### User Story 4 - Long background work keeps the app usable (Priority: P3)

For work that may take long and does not need to block the owner (first users: backup and server sync in a later feature), a "pending" toast says the work is running and "Vous pouvez continuer.", then turns into a success or error toast in place.

**Why this priority**: Approved in the design and needed by the next features; no current action needs it, so it is the lowest priority here.

**Independent Test**: Trigger a test background task: the pending toast stays while the owner keeps using the app, then the same toast becomes success or error.

**Acceptance Scenarios**:

1. **Given** a background task starts, **When** it runs, **Then** a pending toast with a spinner and "Vous pouvez continuer." is shown and the screen stays usable.
2. **Given** the task ends, **When** it succeeds or fails, **Then** the same toast changes in place to success or error (no second toast).

---

### Edge Cases

- A save ends between 300 and 500 ms: the loader appears at 300 ms and stays until 500 ms of visibility, then the outcome is shown.
- The app goes to the background during a save: the save completes on resume; the outcome toast is shown when the app is visible again.
- The owner presses Android back while the loader is visible: nothing happens. While only the button is busy (before 300 ms), back is also ignored so the outcome is not lost.
- A save fails after the loader appeared: the loader closes first, then the error toast is shown; nothing is partially saved (existing transactions).
- "Réessayer" after the owner edited the form: the retry uses the current form values, not the values of the failed attempt.
- Several quick successes (e.g. scanning and saving products one after another): toasts queue and each shows in turn; a new success for the same kind of record (same title, e.g. "Produit enregistré") replaces a still-visible one instead of piling up.
- Arabic: toasts, loader and skeletons mirror (icon on the start side), amounts and counts keep Latin digits and left-to-right order.
- Large text (130%): toast and loader text wrap, nothing is clipped, touch targets stay 44 dp.
- The system "reduce motion" setting: no slide or pulse animations, content still appears.
- Several fields in error when Save is tapped: all of them are outlined in red with their message; the first one, from top to bottom (start to end in Arabic), gets the focus.
- A field in error that is not a text input (customer picker, sale choice, product list): it is scrolled into view, outlined in red and its message is announced; screen-reader focus moves to it.
- The owner corrects a field in error: its red outline and message disappear as soon as the value becomes valid, without tapping Save again.
- The service rejects a value the form could check itself (e.g. a required name): the error is mapped back to its field with the same red outline and focus, not shown as a toast. Only failures that no field can explain (database error, record no longer available) use the error toast.
- Database startup failure: the existing startup error screen is kept; no toast before the database is ready.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: All ledger reads and writes MUST run asynchronously so the interface never freezes while they execute. Transactions, operation-log entries, audit events, validation and money rules from 001–003 MUST be unchanged.
- **FR-002**: Every user-triggered write (save ticket, payment, product, customer; void ticket or payment; remove product or customer) MUST go through one shared async-action mechanism that drives the busy button, the blocking loader, the outcome toast and the error message mapping. Screens MUST NOT implement their own loading logic, and services MUST NOT depend on the interface.
- **FR-003**: While an action runs, the button that started it MUST show a spinner and "Enregistrement…" (or the action's own verb), be disabled, be announced as busy, and further taps MUST NOT start the action again.
- **FR-004**: The app MUST have exactly one blocking loader. It MUST appear only when an action runs longer than 300 ms, stay visible at least 500 ms once shown, name the action and its object, block touches and the Android back button, offer no cancel for writes, show "Vos données restent sur ce téléphone.", and after 2 s switch to "Ça prend plus de temps que prévu" / "Ne fermez pas l'application." with a warning-colored spinner.
- **FR-005**: The app MUST have exactly one toast area, at the top under the top bar, never covered by the keyboard, a fixed form footer or the tab bar. It MUST show one toast at a time and queue the others; toasts MUST be dismissible by swiping right (the physical right, in every language) or with ×.
- **FR-006**: Toasts MUST come in four kinds: success (3 s, optional "Voir"), info (4 s, ×), error (stays until "Réessayer" or ×), pending (stays until the work ends, then turns into success or error in place).
- **FR-007**: A successful save MUST return to the previous screen and show a success toast naming what was saved, with a figure when meaningful (amount collected, number of items). A successful void or removal MUST show a success toast.
- **FR-008**: A failed action (other than a form validation error, see FR-015) MUST close the loader, show an error toast "{thing} non enregistré" with "Vos saisies sont gardées." (or "Rien n'a été modifié." for voids and removals) and a "Réessayer" action that reruns the same action with the current form values. Forms MUST keep every value.
- **FR-009**: Screens that read data MUST show skeleton rows on first load and keep previous values with a "Mise à jour…" indicator while refreshing. Read failures MUST use the existing inline error state with "Réessayer"; reads MUST NOT show toasts.
- **FR-010**: The pending toast (User Story 4) MUST be available to background work and keep the screen usable while it runs.
- **FR-011**: Toasts MUST be announced by screen readers (errors assertively); the loader MUST be announced and keep screen-reader focus while visible; busy buttons MUST expose the busy state; animations MUST be skipped when the system asks to reduce motion; all touch targets MUST be at least 44 dp.
- **FR-012**: Every new text MUST be added in French, Arabic and English, with the French and Arabic wording of the design note; toasts, loader and skeletons MUST mirror in Arabic, and numbers MUST follow the 003 rule (same digits and format in every language).
- **FR-013**: The new error, toast and skeleton colors MUST be added as design tokens (including a red `error` family distinct from the orange used for debt); no hex colors in component files.
- **FR-014**: The interface MUST stay responsive during reads and writes: scrolling and taps on other controls MUST keep working while a read runs. While a write runs, the busy button and the back action are blocked from the tap; once the blocking loader is visible (after 300 ms), everything except the loader is inert.

- **FR-015**: Form validation errors (required field missing, invalid amount, quantity or barcode, credit without a customer, partial amount out of range) MUST NOT go through the toast or loader system. They are owned by the form: tapping Save on an invalid form MUST NOT start the async action, MUST outline every field in error with the red `error` token (not the orange debt color) and show its message under it, and MUST move the focus (keyboard and screen reader) to the first field in error, scrolling it into view. A validation error raised by a service MUST be mapped back to its field the same way. Field messages MUST be announced by screen readers and translated in French, Arabic and English.

### Key Entities

- **Async action**: a named user action (kind, object description such as "4 articles · 13,150 DT", the work to run, success and failure messages). Status: idle → running → slow (after 2 s) → succeeded | failed. Not stored.
- **Toast**: kind (success, info, error, pending), title, optional detail, optional action ("Voir", "Réessayer"), duration rule. Lives in one app-wide queue. Not stored.
- **Field error**: field id, message key. Owned by the form, cleared when the field becomes valid. Never a toast. Not stored.
- **Blocking loader state**: current action label and object, visible since, slow flag. One per app. Not stored.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: With a ledger of 2,000 tickets and 300 customers on a mid-range Android phone, no tap or scroll is blocked for more than 100 ms while a list loads or a save runs.
- **SC-002**: 100% of write actions show a busy state within 100 ms of the tap and end with exactly one outcome toast.
- **SC-003**: Actions shorter than 300 ms never show the blocking loader; longer ones show it for at least 500 ms (verified with a configurable test delay).
- **SC-004**: Zero duplicate records in a test of 50 rapid double taps on each save button.
- **SC-005**: After any failed save, 100% of entered values are still in the form, and "Réessayer" succeeds once the cause is removed.
- **SC-006**: In a check with 3 shop owners, each can tell whether a ticket was saved without opening a list.
- **SC-007**: `npm run validate` passes, and the 001–003 service tests keep passing after the switch to asynchronous data access.
- **SC-008**: On every form, tapping Save with a missing required field shows zero toasts and zero loaders, and the missing field has the focus and a red outline.

## Assumptions

- Feature numbering: the owner chose 004 for this feature. Account, encrypted backup and server sync, previously announced as 004 in `CLAUDE.md`, move to 005.
- The SQLite library already offers asynchronous calls; the schema does not change and no data migration is needed.
- Offline-first (constitution VI) is unchanged: writes still go to SQLite first with their operation-log entry; no network code is added.
- Read failures keep the 003 inline error state (the screen has nothing else to show); toasts are for the outcome of actions.
- The 300 ms / 500 ms / 2 s thresholds and toast durations are the design's values; a test setting may slow actions down to check them, and is off in normal use.
- The design canvas is owned by the product owner; the design note transcribes it and is the source of truth for implementation.
- Dark mode, tablet layouts and haptic feedback are out of scope.
