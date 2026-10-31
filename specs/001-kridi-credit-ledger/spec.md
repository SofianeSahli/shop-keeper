# Feature Specification: Kridi Credit Ledger

**Feature Branch**: `001-kridi-credit-ledger`

**Created**: 2026-10-10

**Status**: Closed on 2026-10-31 (all tasks closed before the phase 1 backend)

**Input**: User description: "Build a kridi (credit ledger) feature for small grocery shop owners in Tunisia, with a simple product catalog and barcode scanning. The owner can maintain products, add customers with optional real identity, create customer tickets from scanned or manually selected products, record ticket/customer payments, and view balances and highest debts. It must work offline, support Arabic and French with RTL, use integer millimes, keep customer data private, and keep common actions within three taps. Uploading local changes is out of scope; accounts/login, backend sync, stock, purchase prices, reminders, and dashboards are also out of scope. Include hypotheses to validate about partial payments, ticket/customer tracking, barcode coverage, and pseudonymous customer labels."

## Clarifications

### Session 2026-10-10

- Q: When the owner records a payment against a customer's total balance, how should the payment be assigned to that customer's unpaid tickets? → A: Show a ticket-selection popup with the oldest unpaid ticket selected by default; let the owner choose a different single ticket if needed.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Manage Products and Customers (Priority: P1)

A shop owner can create, view, find, update, and remove products and customers. Products have an owner-facing name, optional barcode, and selling price. Customers use an owner-chosen display label; real name and phone number are optional. Removing products or customers from active use must not erase financial history that refers to them.

**Why this priority**: Products and customers identify the goods and people involved in the credit ledger.

**Independent Test**: With no network connection, create and find a product and pseudonymous customer, edit their details, remove them from active lists, and confirm existing ledger history remains readable after reopening the feature.

**Acceptance Scenarios**:

1. **Given** a new product, **When** the owner saves its name, optional barcode, and positive selling price, **Then** it appears in the active catalog and is available for ticket entry.
2. **Given** a barcode already belongs to an active product, **When** the owner scans or enters that barcode, **Then** the existing product is offered and a duplicate is not silently created.
3. **Given** a customer has only a display label, **When** the owner saves the customer, **Then** a random internal identifier is assigned without requiring a real name or phone number.
4. **Given** an active product or customer, **When** the owner edits its details, **Then** the new details are available for future work without rewriting historical ticket snapshots.
5. **Given** a product or customer is referenced by saved financial records, **When** the owner removes it from active use, **Then** it is unavailable for new work and existing records remain understandable.
6. **Given** there is no internet connection, **When** the owner creates, views, edits, or removes a product or customer, **Then** the change is saved locally and recorded for possible future upload without attempting an upload.

---

### User Story 2 - Manage Customer Tickets (Priority: P1)

A shop owner can create and view customer tickets by scanning barcodes or manually selecting products, adjust item quantities, and see the total before saving. The owner can correct or remove a saved ticket while preserving an understandable history of any financial effects.

**Why this priority**: Tickets record what a customer owes and form the basis for balances and repayments.

**Independent Test**: Create a multi-item ticket offline, adjust quantities and verify its total, save and reopen it, then correct or remove it and confirm ticket history and the customer balance remain consistent.

**Acceptance Scenarios**:

1. **Given** a customer and catalog products exist, **When** the owner scans or manually selects products and changes quantities, **Then** the ticket total updates immediately and remains visible throughout ticket creation.
2. **Given** a scanned barcode is not in the catalog, **When** the owner chooses to add it, **Then** the owner can enter a product name and selling price and continue creating the ticket.
3. **Given** a ticket is ready to save, **When** the owner records it for a customer, **Then** it is stored locally with item name, barcode, price, quantity, total, and creation time snapshots and starts unpaid.
4. **Given** a saved ticket has no payments, **When** the owner corrects or removes it, **Then** the change is saved locally, recorded for possible future upload, and reflected in the customer's balance.
5. **Given** a saved ticket has payments, **When** the owner requests a correction or removal, **Then** the owner must confirm the action and payment history remains traceable while the balance is recalculated.
6. **Given** a scan is empty, unreadable, or unknown and the owner declines to add a product, **When** the owner continues ticket entry, **Then** other items remain available and the incomplete ticket is not saved.

---

### User Story 3 - Manage Payments and Review Balances (Priority: P1)

The owner can create, view, correct, and remove ticket payments, see ticket status and remaining amounts, and review customer balances including who owes the most. A payment started from a customer's total balance is assigned to one selected unpaid ticket.

**Why this priority**: Accurate repayment records and balances complete the essential credit ledger.

**Independent Test**: Record partial and full payments offline, correct and remove a payment, and verify the affected ticket statuses and highest-to-lowest customer balances after each change.

**Acceptance Scenarios**:

1. **Given** a ticket has an outstanding amount, **When** the owner records a positive payment no greater than that amount, **Then** the ticket becomes partially paid or paid as appropriate and its remaining amount is shown.
2. **Given** a customer has one or more unpaid tickets, **When** the owner starts a customer-balance payment, **Then** a ticket-selection popup opens with the oldest unpaid ticket selected by default, the owner can choose a different single ticket, and the payment reduces only that ticket's outstanding amount.
3. **Given** a payment exists, **When** the owner corrects or removes it, **Then** the action is confirmed, the change is recorded locally, and ticket status and customer balance are recalculated without erasing payment history.
4. **Given** the owner enters a zero, negative, fractional-millime, or excessive payment, **When** the owner confirms, **Then** the payment is rejected with a clear explanation and existing financial records remain unchanged.
5. **Given** several customers have outstanding balances, **When** the owner views the customer list, **Then** each balance is shown and customers are ordered from highest to lowest amount owed.
6. **Given** payments fully cover a ticket total, **When** the ledger is viewed, **Then** its status is paid; tickets with no payment are unpaid and tickets with some but not all of their total paid are partially paid.
7. **Given** there is no internet connection, **When** the owner creates, views, edits, or removes a payment, **Then** the change is saved locally and recorded for possible future upload without attempting an upload.

### Edge Cases

- A scan returns an empty, unreadable, or unsupported barcode; the owner can retry or select a product manually.
- A barcode is unknown and the owner declines to add a product; ticket entry remains available for other products and can be cancelled without saving an incomplete ticket.
- A product is renamed or repriced after it was used on a ticket; previously recorded ticket item names and prices remain understandable and ticket totals do not change.
- The owner enters a zero or negative product price, ticket quantity, or payment amount; the value is rejected with a clear explanation and no financial record is changed.
- A payment is greater than the selected ticket's outstanding amount; the owner is prevented from applying an excessive amount and can choose another ticket instead.
- A payment correction would exceed the ticket's remaining amount after excluding the payment being edited; the correction is rejected without changing the ledger.
- The owner tries to pay a ticket that is already paid; no extra payment is recorded against it.
- A ticket or payment is removed after it affected a balance; the owner confirms the change and historical financial activity remains traceable.
- The owner cancels a correction or removal confirmation; no data or balance changes.
- A customer has no tickets or payments; their outstanding balance is zero and they do not appear above customers who owe money.
- A device is restarted while offline; saved customers, products, tickets, payments, and pending change records remain available.
- Arabic is selected; labels and numeric amounts remain legible and the interface follows right-to-left direction. French uses left-to-right direction.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The feature MUST let an owner create, view, find, edit, and remove catalog products with a non-empty name, optional barcode, and positive selling price.
- **FR-002**: Product selling prices, ticket totals, payments, and balances MUST be represented as integer millimes; floating-point values MUST NOT be used for monetary amounts.
- **FR-003**: The feature MUST detect a scanned barcode that is not in the catalog and offer the owner an immediate path to add its product name and selling price.
- **FR-004**: The feature MUST let an owner create, view, find, edit, and remove customers using a display label chosen by the owner.
- **FR-005**: Each customer MUST have a random internal identifier. Providing real identity, including a real name or phone number, MUST be optional.
- **FR-006**: The feature MUST let an owner create a ticket for a customer using scanned barcodes or manual product selection, adjust item quantities, and see the recalculated total throughout ticket creation.
- **FR-007**: Saved tickets MUST retain the product name and selling price as recorded at the time of sale so later catalog edits do not change historical ticket totals.
- **FR-008**: The feature MUST allow an owner to record a positive payment against a ticket or a customer's total balance. For a customer-balance payment, the feature MUST open a ticket-selection popup with the oldest unpaid ticket selected by default; the owner MUST be able to choose a different single ticket before confirming the payment.
- **FR-008a**: The owner MUST be able to view, correct, and remove recorded payments. Corrections and removals MUST be confirmed, recorded locally, and reflected in recalculated ticket statuses and customer balances without silently erasing financial history.
- **FR-009**: Ticket status MUST be derived from recorded payments: unpaid when no amount has been paid, partially paid when some but not all of the total has been paid, and paid when the total has been paid.
- **FR-010**: The feature MUST show each customer's total outstanding balance and provide a customer list ordered from greatest outstanding balance to least.
- **FR-011**: Customer balances MUST equal the sum of the remaining amounts on that customer's tickets; payments MUST NOT reduce a balance below zero.
- **FR-012**: All feature actions and data viewing MUST work without an internet connection. Data MUST be stored on the device and every create, update, and removal MUST be recorded locally for possible later upload. Uploading changes and backend synchronization are outside this feature.
- **FR-013**: Customer names, labels, phone numbers, tickets, debts, and payment data MUST remain private to the shop and MUST NOT be included in shared or aggregated datasets. Any future statistics are limited to products and prices and are outside this feature.
- **FR-014**: The feature MUST support Arabic and French, including right-to-left layout for Arabic and left-to-right layout for French.
- **FR-015**: Common shopkeeper actions MUST be completable in three taps or fewer, not counting text entry or barcode scanning.
- **FR-016**: The feature MUST provide clear feedback when invalid values prevent a product, ticket, or payment from being saved.
- **FR-017**: The owner MUST be able to correct or remove a saved ticket. If the ticket has payments, the owner MUST confirm the action, payment history MUST remain traceable, and the customer balance MUST be recalculated without silently discarding financial activity.
- **FR-018**: Removing a product or customer MUST remove it from active selection while preserving readable historical records that refer to it.

### Key Entities

- **Product**: A catalog item identified by an owner-assigned name and barcode, with a selling price in integer millimes.
- **Customer**: A shop-local ledger identity with a random internal identifier and owner-chosen display label; real identity is optional.
- **Ticket**: A customer's dated credit record containing purchased product lines, quantities, prices at time of sale, total amount, and a payment-derived status.
- **Ticket Line**: A product snapshot and positive quantity within a ticket; contributes to the ticket total.
- **Payment**: A positive amount in integer millimes recorded against a ticket or customer balance, assigned to one ticket.
- **Pending Change**: A local record of each product, customer, ticket, or payment creation, update, or removal, retained for possible future upload; transmission is out of scope.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In offline testing, 100% of successfully saved customers, products, tickets, and payments, including completed corrections and removals, remain consistently represented after closing and reopening the feature.
- **SC-002**: Owners can create a ticket with at least ten product lines and see the correct total after each quantity change.
- **SC-003**: For all tested ticket and customer payment sequences, ticket status and outstanding balances match the recorded amounts exactly in millimes.
- **SC-004**: In usability testing, at least 90% of owners can complete the common flows of adding a product, adding a customer, recording a ticket, recording a payment, and viewing a balance in three taps or fewer, excluding text entry and barcode scanning.
- **SC-005**: Owners can complete each primary flow in Arabic and French, with Arabic screens presented right-to-left and French screens left-to-right.
- **SC-006**: Review of feature outputs finds no customer identity, debt, ticket, or payment data in shared or aggregated datasets.
- **SC-007**: Owners can complete product, customer, ticket, and payment create, view, update, and removal flows without losing the traceable financial history needed to explain current balances.

## Hypotheses to Validate

- Partial payments are common in participating shops.
- Owners track credit both per ticket and per customer.
- Most products sold by target shops have scannable barcodes.
- Owners are willing to use pseudonymous customer labels instead of entering real identity.

## Assumptions

- A single shop owner operates the feature on one device; multi-device sharing is out of scope.
- Customer-balance payments default to the oldest unpaid ticket in the selection popup; the owner may choose a different single ticket.
- Tickets retain an immutable snapshot of each item's name and price at the time of sale.
- Product and customer removal means removal from active use; it does not erase financial history.
- Correcting or removing tickets and payments requires confirmation when it affects an existing balance, and financial history remains traceable after that change.
- The owner can cancel a ticket before saving; catalog changes do not erase saved financial history.
- No account, login, backend sync, inventory quantity, purchase price, reminder, or dashboard functionality is included.
