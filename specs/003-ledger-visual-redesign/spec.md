# Feature Specification: Ledger Visual Redesign

**Feature Branch**: `003-ledger-visual-redesign`

**Created**: 2026-10-24

**Status**: In progress (implementation started 2026-10-24)

**Input**: User description: "Apply the approved mobile design (Carnet Kridi design canvas) to the existing ledger screens built in 001 and 002. Introduce a small set of design tokens and reusable components, redesign the dashboard, ticket form, payment form, customer list, customer ledger, product form, ticket list and product catalog, and close the open 002 tasks. Behaviour, data and storage stay as they are: this is a presentation and usability feature, 100% local." Amendment 2026-10-24: "A ticket is set to paid, partially paid or unpaid when it is recorded, so a ticket covers any sale, not only kridi."

Design reference: [design-reference.md](./design-reference.md) (tokens, components, per-screen layout, new translation keys). The interactive mock-ups live in the "Carnet Kridi — Mobile UI" design canvas owned by the product owner; this spec and the reference file are the source of truth for implementation.

## Clarifications

### Session 2026-10-24

- Q: Does this feature change data, services or storage? → A: Mostly no. Presentation components, styles, translations and navigation change. Two exceptions: (1) read helpers a screen needs (for example "customers sorted by balance with open-ticket count") may be added to existing services, with unit tests; (2) User Story 8 (payment status at sale) changes ticket creation and adds a walk-in customer, see below. The operation log rules of 001 still apply to every write.
- Q: Does this feature require a constitution amendment? → A: No. The constitution stays unchanged (owner decision, 2026-10-24). 003 follows Principle VI (offline-first): every write is saved to SQLite first with an operation-log entry, and the data is synchronized with the server in the background whenever the network is available. The synchronization itself is not built in 003 (there is no backend yet); until it exists, records and pending operations stay on the device (FR-021).
- Q: Which font? → A: IBM Plex Sans Arabic (covers Latin and Arabic) loaded with Expo's font tooling, with the system font as fallback while loading or if loading fails.
- Q: Are prototype interactions in the canvas (quick payment amounts, quantity steppers, filters) in scope? → A: Yes, they are part of this feature (see user stories 3–5).
- Q: Is a ticket only for kridi? → A: No. A ticket records any sale. When saving, the owner sets its payment status: Paid, Partially paid (with the amount received) or Unpaid (kridi). Today's sales on the dashboard therefore become the shop's real daily sales.
- Q: How is the chosen status stored? → A: Status stays derived from payments, as in 001. "Paid" and "Partially paid" create a payment for the received amount, linked to the ticket, in the same local transaction as the ticket, dated at the ticket's creation and marked as received at sale. "Unpaid" creates no payment.
- Q: Does a paid sale need a customer? → A: No. A sale paid in full may be recorded without choosing a customer; it is attached to a built-in "Walk-in customer" (Client de passage / حريف عابر). Partially paid and unpaid tickets require a real customer, because the remaining debt must belong to someone. The walk-in customer never appears in customer lists, balances or "who owes the most". (Default chosen on 2026-10-24; confirm with the owner.)
- Q: What is the default status on a new ticket? → A: Paid, because most sales are cash. If the owner picks a customer who already owes money, the default stays Paid; the owner switches to Unpaid in one tap.
- Q: Can the status be changed when editing a saved ticket? → A: Not through the selector. After saving, the status changes only through payments (record, correct, void), with the existing 001 rules and confirmations.

### Session 2026-10-24 (decisions taken during planning and implementation)

- Q: How are numbers written in Arabic? → A: Exactly as in every other language: Latin digits (0–9, never ٠١٢), the same grouping and the same decimal separator (Tunisian convention, e.g. 1 284 500,500). Only the currency label is translated (DT / د.ت). Dates keep translated month and weekday names but use Latin digits (FR-022).
- Q: Which Expo SDK? → A: Expo SDK 58 (React Native 0.88, React 19.3), upgraded from SDK 57 during implementation (FR-023).
- Q: Does the scanner stay open between codes? → A: No. The camera closes after each accepted code, so a two-item cash sale is New ticket → Scan → Scan → Encaisser (SC-007). Search and manual entry stay available (FR-025).
- Q: User Story 4 says a quick chip larger than the remaining amount is clamped; the edge case says only "Whole ticket" is enabled under 10 DT. Which wins? → A: Both: the 10/20/50 DT chips are enabled only when at least 10 DT remains, and a chip never exceeds the remaining amount (FR-026).
- Q: Where do destructive actions go once they leave list rows? → A: Void a payment from its edit screen, remove a customer from the customer form, remove a product from the product form, edit or void a ticket from the ticket detail; always behind the existing confirmation (FR-024).
- Q: How is the walk-in customer stored? → A: A reserved customer row with the fixed id `walk-in`, created once by an idempotent migration. The same id on every device lets the future server sync merge walk-in sales. At-sale payments use a new payment kind `AT_SALE`; no table or column is added.
- Q: Where is Home? → A: At `/`; `/ledger` redirects there (FR-027).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consistent, readable look across the app (Priority: P1)

The owner sees the same visual language on every ledger screen: one top bar style, one tab bar style, the same card, list-row, button, chip and amount styles, large touch targets and a single font that reads well in Arabic and French.

**Why this priority**: Every other story reuses these tokens and components; consistency lowers cognitive load for owners with limited digital experience.

**Independent Test**: Open every route in French and Arabic and verify each uses the shared tokens and components listed in the design reference, that no screen uses ad-hoc colors, and that all touch targets are at least 44 dp.

**Acceptance Scenarios**:

1. **Given** any ledger screen, **When** it is displayed, **Then** it uses only the colors, radii, spacing and type sizes defined as design tokens.
2. **Given** a money amount anywhere in the app, **When** it is displayed, **Then** it uses the shared amount component (TND, three decimals, locale formatting, tabular digits), with the debt color when it is money owed and the paid color when it is settled.
3. **Given** a ticket status anywhere in the app, **When** it is displayed, **Then** it uses the shared status chip with a text label (Impayé/Partiel/Payé — غير مدفوعة/جزئية/مدفوعة), never color alone.
4. **Given** the app language is Arabic, **When** any screen is displayed, **Then** layout, icons with direction (back, chevrons) and the tab order are mirrored.

---

### User Story 2 - Dashboard that answers "how is my day" (Priority: P1)

The home dashboard shows today's date, a "saved on this phone" reassurance, today's sales and the outstanding kridi as two stat cards, a large "New ticket" action, two secondary actions (Collect a payment, Product), and the three customers who owe the most.

**Why this priority**: Home is the most-opened screen and the entry point to the three most common actions.

**Independent Test**: With seeded data, compare the stat cards with `getDashboardSummary`, verify the top-3 list matches the three highest balances, and verify each action opens its flow in one tap.

**Acceptance Scenarios**:

1. **Given** ledger data exists, **When** Home opens, **Then** sales today shows the amount and the number of active tickets created today, and outstanding kridi shows the amount and the number of open tickets.
2. **Given** at least one customer owes money, **When** Home opens, **Then** "Who owes the most" lists up to three customers by descending balance, each opening that customer's ledger in one tap.
3. **Given** no customer owes money, **When** Home opens, **Then** the list is replaced by a short positive empty state.
4. **Given** the owner taps the outstanding kridi card, **When** it opens, **Then** the customer list opens filtered on "Owes".

---

### User Story 3 - Faster ticket entry (Priority: P1)

On the ticket form the selected customer is pinned at the top with their current debt, a "New" button creates a customer inline, two large buttons start a scan or a search, each line has a quantity stepper, and the total with the save button stays fixed at the bottom above the keyboard.

**Why this priority**: Recording a sale on credit is the core action of the app.

**Independent Test**: Create a ticket with three products, change quantities with the steppers, add a customer and a product inline, and verify totals, preserved draft and save behaviour are unchanged from 002.

**Acceptance Scenarios**:

1. **Given** a customer is selected, **When** the form is shown, **Then** the customer card shows the label and the customer's current outstanding balance.
2. **Given** a ticket line, **When** the owner taps + or −, **Then** the quantity changes by one, never goes below one, and the line total and ticket total update immediately.
3. **Given** the form is scrolled or the keyboard is open, **When** the owner looks for the total, **Then** the total and the save button remain visible in the fixed footer.
4. **Given** a draft with lines, **When** the owner creates a customer or product inline, **Then** all 002 draft-preservation rules still hold.

---

### User Story 4 - Collect a payment in seconds (Priority: P1)

The payment form shows the customer with their total debt, the selected ticket (oldest unpaid by default) with its remaining amount and a "Change" action that opens a bottom sheet of unpaid tickets, a large amount field, quick-amount chips ("Whole ticket", 10, 20, 50 DT), and a live line "Remaining after payment" or "Ticket fully paid".

**Why this priority**: Collecting kridi is the second most frequent action and the one that reduces disputes.

**Independent Test**: Pay a partially paid ticket with each quick chip and with a typed amount; verify the chip clamps to the ticket's remaining amount and the 001 validation (no excessive or zero payments) still applies.

**Acceptance Scenarios**:

1. **Given** a customer with unpaid tickets, **When** the payment form opens, **Then** the oldest unpaid ticket is selected and "Whole ticket" pre-fills its remaining amount.
2. **Given** a quick chip larger than the remaining amount, **When** it is selected, **Then** the amount is clamped to the remaining amount.
3. **Given** an amount, **When** it changes, **Then** the remaining-after line updates, switching to a "fully paid" state at zero.
4. **Given** the owner taps "Change", **When** the bottom sheet opens, **Then** it lists unpaid tickets with date, status and remaining amount, the current choice is marked, and confirming returns to the form with the new ticket.

---

### User Story 5 - Find who owes what (Priority: P2)

The customer list has a search field, filter chips "Owes (n)", "Up to date (n)" and "All", is sorted by balance (highest first), shows for each customer initials, label, a short context line (open tickets, age of the oldest debt) and the balance, and has a floating "+ Customer" button. The customer ledger shows a balance card (amount owed, open tickets, oldest date), a call button when a phone number exists, "Ticket" and "Collect" actions, and a history grouped by day with tickets and payments.

**Why this priority**: Knowing who to chase is the reason owners keep a kridi book.

**Independent Test**: Seed customers with mixed balances; verify order, counts on the chips, filter results, search, and that the ledger history groups entries by local day, newest first.

**Acceptance Scenarios**:

1. **Given** customers with and without debt, **When** the list opens, **Then** "Owes" is selected and only customers with a positive balance appear, highest first.
2. **Given** a customer without a phone number, **When** their ledger opens, **Then** no call button is shown.
3. **Given** a customer ledger, **When** it is displayed, **Then** tickets show a status chip and remaining amount, payments show as a negative amount in the paid color, grouped under day headers ("Aujourd'hui", "Hier", then the date).

---

### User Story 6 - Product entry with inline scanning (Priority: P2)

The product form shows the camera viewfinder inline at the top, the code field with a re-scan button, an inline duplicate warning with "Open this product", a new-code confirmation, the name field, and a price field with a "DT" suffix and a one-line hint on millimes. Save is disabled while a duplicate is shown.

**Why this priority**: Faster catalog entry; behaviour already exists in 002, only the presentation changes.

**Independent Test**: Scan or type an existing code, a new code, and deny the camera; verify the three states and that manual entry always remains available.

**Acceptance Scenarios**:

1. **Given** camera permission is denied or unavailable, **When** the form opens, **Then** the viewfinder area shows a short explanation and a button to retry permission, and the code field stays editable.
2. **Given** a duplicate active code, **When** it is detected, **Then** the warning banner is shown, Save is disabled, and "Open this product" opens it.

---

### User Story 7 - Ticket list and product catalog in the new style (Priority: P2)

The ticket list shows filter chips (All, Unpaid, Partial, Paid), tickets grouped by day with customer, item count, status chip and remaining/total amount, and a floating "+ Ticket" button. Edit and void move from inline buttons to the ticket detail screen. The product catalog shows a search field, product rows (name, code, price) and a floating "+ Product" button; remove moves to the product form.

**Why this priority**: These lists exist but use the 001 card style with inline destructive buttons, which is error-prone.

**Independent Test**: Filter tickets by each status, open a ticket from the list, void it from its detail screen with confirmation; search products and remove one from its form.

**Acceptance Scenarios**:

1. **Given** the ticket list, **When** a status filter is selected, **Then** only tickets with that derived status appear; voided tickets never appear.
2. **Given** a list row, **When** it is displayed, **Then** it has no destructive action; destructive actions live on detail/form screens behind the existing confirmation.

---

### User Story 8 - Every sale is a ticket (Priority: P1)

When saving a ticket, the owner chooses how it was paid with a three-option selector: "Payé" (paid in full), "Partiel" (with the amount received) or "Kridi" (unpaid). A paid sale can be saved without choosing a customer. The save button says what will happen: "Encaisser X DT", "Encaisser Y DT · reste Z au kridi", or "Mettre X DT au kridi".

**Why this priority**: Owners record every sale in one place, the daily sales figure becomes true, and the app is useful even to shops that give little credit.

**Independent Test**: Save one ticket of each status (paid without customer, partial with a customer, unpaid with a customer); verify the payments created, derived statuses, customer balances, today's sales and outstanding kridi on the dashboard.

**Acceptance Scenarios**:

1. **Given** a new ticket with lines and no customer, **When** the owner keeps "Payé" and saves, **Then** the ticket is saved to the walk-in customer with a payment equal to its total, its status is Paid, today's sales increase by the total and outstanding kridi does not change.
2. **Given** "Partiel" is selected, **When** the owner enters an amount greater than zero and lower than the total and saves, **Then** a payment of that amount is created, the status is Partially paid and the customer's balance increases by the remainder.
3. **Given** "Partiel" is selected, **When** the amount is zero, empty, equal to or above the total, **Then** saving is blocked with a clear message (suggesting "Payé" when it equals the total).
4. **Given** "Partiel" or "Kridi" is selected and no customer is chosen, **When** the owner tries to save, **Then** saving is blocked and the customer picker is highlighted with a message that credit needs a customer.
5. **Given** "Kridi" is selected with a customer, **When** the owner saves, **Then** no payment is created and the status is Unpaid, as in 001.
6. **Given** the ticket and its at-sale payment are being saved, **When** either write fails, **Then** neither is kept and the draft is preserved.
7. **Given** a paid walk-in ticket, **When** the ticket list or ticket detail shows it, **Then** the customer reads "Client de passage" and the status chip reads Paid.

---

### Edge Cases

- Very long customer labels or product names: truncate to one line in rows, full text on detail screens.
- Amounts of seven or more digits (e.g. 1 284 500,000 DT) must not overflow stat cards; the font size steps down.
- The font fails to load: the system font is used and the app remains fully usable.
- Arabic with Latin product names or codes inside a row: amounts and codes keep left-to-right digit order inside a right-to-left layout.
- System font scaling at 130%: rows grow in height, nothing is clipped, the fixed footer stays reachable.
- Empty states for every list and filter combination use the shared empty-state component with a single clear action.
- Quick-amount chips when the remaining amount is under 10 DT: only "Whole ticket" is enabled.
- The owner switches from "Payé" to "Kridi" after entering lines and no customer is chosen: the draft is kept and the customer picker asks for a customer.
- A paid or partial ticket is later corrected so its total drops below the amount already received: the existing 001 correction rules apply (confirmation; a payment may not exceed the ticket); the owner must correct or void the at-sale payment first.
- A walk-in ticket is voided: its at-sale payment is voided with it, with the usual confirmation, so today's sales and cash stay consistent.
- The walk-in customer cannot be edited, removed or selected for a partial or unpaid ticket.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The app MUST define design tokens (colors, radii, spacing, type scale, touch-target minimum) in the NativeWind/Tailwind configuration and dedicated style files, matching the design reference. Components MUST NOT use hard-coded colors.
- **FR-002**: The app MUST load IBM Plex Sans Arabic (400/500/600/700) and fall back to the system font while loading or on failure.
- **FR-003**: The app MUST provide reusable presentation components listed in the design reference (at minimum: ScreenScroll, StatCard, ActionTile, ListRow, Avatar, AmountText, StatusChip, FilterChips, SearchField, QuantityStepper, StickyFooter, BottomSheet, Fab, InfoBanner, EmptyState, DayHeader).
- **FR-004**: The top bar and bottom tab bar MUST be restyled to the design reference without changing 002 navigation behaviour.
- **FR-005**: Home MUST show the date, the local-save reassurance, the two stat cards with counts, the primary and secondary actions, and the top-3 debtors list (US2).
- **FR-006**: The ticket form MUST provide the pinned customer card with balance, inline "New customer", Scan and Search buttons, quantity steppers, and a fixed total + save footer (US3).
- **FR-007**: The payment form MUST provide the ticket bottom sheet, quick-amount chips clamped to the remaining amount, and the live remaining-after line (US4). Existing payment validation MUST still apply.
- **FR-008**: The customer list MUST provide search, the three filters with counts, balance ordering and the floating add button; the customer ledger MUST provide the balance card, conditional call action, and day-grouped history (US5).
- **FR-009**: The product form MUST show the inline viewfinder and the duplicate / new-code / permission states, and disable Save while a duplicate is shown (US6).
- **FR-010**: The ticket list and product catalog MUST use the new row style, filters/search and floating add buttons; destructive actions MUST be removed from list rows and remain available, with confirmation, on detail or form screens (US7).
- **FR-011**: All new user-visible text MUST be added to `fr.json`, `ar.json` and `en.json`; no hard-coded strings.
- **FR-012**: All interactive elements MUST have accessibility roles, labels and selected/disabled states, and a minimum 44 dp touch target.
- **FR-013**: The four open tasks of 002 MUST be completed: Android system back confirmation on dirty forms, removal of inline style objects in the shell (safe areas through classes or `SafeAreaView` edges), hiding the shell outside registered features, and typed-route verification.
- **FR-014**: The root layout MUST render a single Redux `Provider` and MUST NOT import unused internal Expo Router modules.
- **FR-015**: The ticket form MUST let the owner set the payment status of a new ticket to Paid (default), Partially paid with an amount received, or Unpaid, before saving.
- **FR-016**: Saving a Paid or Partially paid ticket MUST create, in the same local transaction and operation-log batch, a ticket payment for the received amount, dated at the ticket's creation time and identifiable as received at sale. Unpaid creates no payment. Ticket status MUST remain derived from payments.
- **FR-017**: A ticket paid in full MAY be saved without selecting a customer; it MUST then be attached to a built-in walk-in customer that is created once per device, cannot be edited, removed or used for credit, and is excluded from customer lists, balances, debtor rankings and outstanding kridi. Partially paid and unpaid tickets MUST have a real customer.
- **FR-018**: The partial amount MUST be a positive integer number of millimes strictly lower than the ticket total; invalid amounts MUST block saving with a localized message.
- **FR-019**: The save button label MUST state the outcome (amount collected and, when relevant, amount added to kridi).
- **FR-020**: Voiding a ticket MUST void its at-sale payment in the same transaction, after the existing confirmation.
- **FR-021**: Offline-first, sync-ready (constitution Principle VI). Every ledger write in this feature MUST be saved to the local SQLite database first. This includes tickets, at-sale payments, payment voids and the walk-in customer seed. Each write MUST record its operation-log entry (`pending_changes`: client-generated UUID, entity, operation, payload, status `pending`) and, for tickets and payments, its audit event, in the same transaction as the data. Pending entries MUST be kept until a later synchronization marks them applied or rejected. Background synchronization with the server whenever the network is available is delivered with the backend (feature 004), which consumes this log. 003 MUST NOT add network calls, and no data may be lost while the device is offline.
- **FR-022**: Numbers MUST be written identically in every language, including Arabic:
  - Latin digits (0–9), never Arabic-Indic digits;
  - the same digit grouping and decimal separator, following the Tunisian convention (`1 284 500,500`);
  - three decimals for money.
  Only the currency label is translated (`DT` / `د.ت`). Dates keep translated month and weekday names but use Latin digits. Inside right-to-left layouts, amounts, counts and codes keep their left-to-right digit order.
- **FR-023**: The mobile app MUST target Expo SDK 58 (React Native 0.88, React 19.3). Expo packages are installed with `npx expo install`, and `npx expo-doctor` MUST pass.
- **FR-024**: List rows MUST NOT contain destructive actions. Voiding a payment happens on its edit screen, removing a customer on the customer form, removing a product on the product form, and editing or voiding a ticket on the ticket detail. Each one keeps the existing confirmation.
- **FR-025**: The scanner MUST close after each accepted code. Manual code entry and product search MUST remain available when the camera is denied or unavailable.
- **FR-026**: Quick-amount chips (10, 20, 50 DT) MUST be enabled only when the remaining amount is at least 10 DT, and a selected chip MUST never exceed the remaining amount. "Whole ticket" is enabled whenever something remains.
- **FR-027**: Home MUST be the root route `/`. `/ledger` MUST redirect to it, and the top bar and tab bar MUST be hidden on routes that belong to no registered feature.

### Key Entities

- **Design Tokens**: named colors, radii, spacing, type sizes and minimum touch size shared by all components.
- **Debtor Summary** (read model): customer id, label, outstanding balance, open-ticket count, date of oldest open ticket. Derived from existing tickets and payments; not stored.
- **Sale Payment Choice** (form state, not stored): Paid, Partially paid + amount, or Unpaid, chosen on the ticket form; turned into zero or one at-sale payment when the ticket is saved.
- **Walk-in Customer**: a single built-in customer per device ("Client de passage") used for sales paid in full without a named customer; never carries a balance. Stored as a reserved row with the fixed id `walk-in`, identical on every device so the future server sync can merge it.
- **At-sale Payment**: a regular ticket payment created with the ticket and marked as received at sale (for example a new payment kind), so history can show "Payé à la vente" and later reports can separate cash sales from debt collection.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of ledger screens use only design tokens for color (verified by a lint or grep rule rejecting hex colors in `.tsx` files).
- **SC-002**: Recording a 3-item ticket for an existing customer takes 3 taps or fewer to start and no more than one scroll on a 390×844 screen.
- **SC-003**: Recording a full payment of the oldest ticket from the dashboard takes 3 taps (Collect → customer → Save).
- **SC-004**: All screens pass manual checks in French and Arabic at 100% and 130% font scale with no clipped text.
- **SC-005**: `npm run validate` (lint, typecheck, tests) passes; existing service tests are unchanged and green.
- **SC-006**: In a usability check with 3 shop owners, each finds "who owes the most" and records a payment without help.
- **SC-007**: A cash sale of 2 scanned items is recorded in 4 taps or fewer from Home (New ticket → Scan → Scan → Encaisser), with no customer selection.
- **SC-008**: For 100% of tested sales, dashboard sales today equals the sum of all non-voided tickets of the day whatever their status, and outstanding kridi equals only the unpaid remainders.
- **SC-009**: For 100% of writes made in this feature, an operation-log entry with status `pending` exists after the write, created in the same transaction (verified by service tests).
- **SC-010**: Any amount, count or date shows the same digits, grouping and decimal separator in French, Arabic and English (verified by unit tests on the formatters and a manual check of every screen in Arabic).

## Assumptions

- Behaviour, data rules and validation from 001 and 002 remain in force, except the 001 rule "a saved ticket starts unpaid", which User Story 8 replaces. For any other disagreement, 001/002 win and the conflict is raised.
- The walk-in customer is a reserved row with the fixed id `walk-in`, and "received at sale" is the payment kind `AT_SALE` (decided in the plan, research R15–R16). The seed needs an idempotent migration and tests.
- Server synchronization is out of scope for 003. It needs the Laravel backend and is delivered by feature 004. Until then the app is fully usable offline, and pending operations accumulate in the local log.
- Dark mode is out of scope; the light palette is used everywhere.
- Icons stay on `@expo/vector-icons` (Ionicons); the canvas icons are a visual guide, not assets.
- Tablet layouts are out of scope.
- The constitution stays at version 1.5.0 (merge-conflict markers resolved in favour of 1.5.0 on 2026-10-24); no amendment is made for this feature.
