# Feature Specification: Kridi Credit Ledger

**Feature Branch**: `001-kridi-credit-ledger`

**Created**: 2026-10-10

**Status**: Draft

**Input**: User description: "Build a kridi (credit ledger) feature for small grocery shop owners in Tunisia, with a simple product catalog and barcode scanning. The owner can maintain products, add customers with optional real identity, create customer tickets from scanned or manually selected products, record ticket/customer payments, and view balances and highest debts. It must work offline, support Arabic and French with RTL, use integer millimes, keep customer data private, and keep common actions within three taps. Uploading local changes is out of scope; accounts/login, backend sync, stock, purchase prices, reminders, and dashboards are also out of scope. Include hypotheses to validate about partial payments, ticket/customer tracking, barcode coverage, and pseudonymous customer labels."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Record a Customer's Credit (Priority: P1)

A shop owner can create a customer using a real or pseudonymous display label, add products to the catalog, and record a kridi ticket for that customer by scanning barcodes or selecting products manually. The owner can adjust quantities and always see the ticket total before saving.

**Why this priority**: Recording what a customer owes is the core value of a credit ledger.

**Independent Test**: With no network connection, create a customer, create or select catalog products, record a ticket, and verify its item quantities and total after leaving and reopening the feature.

**Acceptance Scenarios**:

1. **Given** a customer and catalog products exist, **When** the owner scans or manually selects products and changes quantities, **Then** the ticket total updates immediately and is visible throughout ticket creation.
2. **Given** the owner scans a barcode not in the catalog, **When** the owner chooses to add it, **Then** the owner can enter a product name and selling price and continue creating the ticket with that product.
3. **Given** a ticket is ready to save, **When** the owner records it for a customer, **Then** the ticket is stored on the device, appears in that customer's ledger, and starts with an unpaid status.
4. **Given** there is no internet connection, **When** the owner adds or changes a customer, product, or ticket, **Then** the change is saved locally and recorded for possible future upload without attempting an upload.

---

### User Story 2 - Record Payments and Review Balances (Priority: P1)

The owner can record a payment against a specific ticket or a customer's total balance, see the resulting ticket status, and review customer balances including who owes the most.

**Why this priority**: Payments and accurate outstanding balances complete the essential record of credit and repayment.

**Independent Test**: Record full and partial payments against a ticket and a customer balance while offline; verify ticket statuses and customer outstanding balances.

**Acceptance Scenarios**:

1. **Given** a customer has an unpaid ticket, **When** the owner records a payment smaller than its outstanding amount against that ticket, **Then** the ticket becomes partially paid and its remaining amount is shown.
2. **Given** a customer has one or more outstanding tickets, **When** the owner records a payment against the customer's balance, **Then** the payment reduces the customer's outstanding amount and is allocated to the oldest outstanding ticket or tickets first.
3. **Given** payments fully cover a ticket total, **When** the ledger is viewed, **Then** its status is paid; tickets with no payment are unpaid and tickets with some but not all of their total paid are partially paid.
4. **Given** several customers have outstanding balances, **When** the owner views the customer list, **Then** each balance is shown and customers are ordered from highest to lowest amount owed.

---

### User Story 3 - Maintain Products and Customers (Priority: P2)

The owner can maintain product names, barcodes, and selling prices, and can find customers by their chosen display labels without having to collect real-world identity.

**Why this priority**: A lightweight catalog speeds up ticket entry, while flexible labels support the shop's preferred way of recognizing customers.

**Independent Test**: Add, edit, remove, and find products offline; add and find customers; verify product barcode and price values and confirm real identity is optional.

**Acceptance Scenarios**:

1. **Given** the owner adds a product, **When** a name, barcode, and selling price are entered, **Then** the product is available for scanning and manual selection.
2. **Given** the owner adds a customer, **When** a display label is entered, **Then** a unique random internal identifier is assigned and no real name or phone number is required.
3. **Given** a barcode is already assigned to a product, **When** the owner attempts to add another product with that barcode, **Then** the duplicate is identified and the existing product is offered instead of silently creating a duplicate.
4. **Given** a product is in the catalog, **When** the owner changes its name or selling price, **Then** future tickets use the updated details while existing ticket totals remain unchanged.
5. **Given** a product is no longer sold, **When** the owner removes it from the active catalog, **Then** it is unavailable for new tickets and existing ticket records remain unchanged.

### Edge Cases

- A scan returns an empty, unreadable, or unsupported barcode; the owner can retry or select a product manually.
- A barcode is unknown and the owner declines to add a product; ticket entry remains available for other products and can be cancelled without saving an incomplete ticket.
- A product is renamed or repriced after it was used on a ticket; previously recorded ticket item names and prices remain understandable and ticket totals do not change.
- The owner enters a zero or negative product price, ticket quantity, or payment amount; the value is rejected with a clear explanation and no financial record is changed.
- A payment is greater than the selected ticket's or customer's outstanding balance; the owner is prevented from applying an excessive amount.
- The owner tries to pay a ticket that is already paid; no extra payment is recorded against it.
- A customer has no tickets or payments; their outstanding balance is zero and they do not appear above customers who owe money.
- A device is restarted while offline; saved customers, products, tickets, payments, and pending change records remain available.
- Arabic is selected; labels and numeric amounts remain legible and the interface follows right-to-left direction. French uses left-to-right direction.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The feature MUST let an owner create, view, edit, and remove catalog products with a name, barcode, and selling price.
- **FR-002**: Product selling prices, ticket totals, payments, and balances MUST be represented as integer millimes; floating-point values MUST NOT be used for monetary amounts.
- **FR-003**: The feature MUST detect a scanned barcode that is not in the catalog and offer the owner an immediate path to add its product name and selling price.
- **FR-004**: The feature MUST let an owner create and view customers using a display label chosen by the owner.
- **FR-005**: Each customer MUST have a random internal identifier. Providing real identity, including a real name or phone number, MUST be optional.
- **FR-006**: The feature MUST let an owner create a ticket for a customer using scanned barcodes or manual product selection, adjust item quantities, and see the recalculated total throughout ticket creation.
- **FR-007**: Saved tickets MUST retain the product name and selling price as recorded at the time of sale so later catalog edits do not change historical ticket totals.
- **FR-008**: The feature MUST allow an owner to record a positive payment against a ticket or a customer's total balance. A customer-balance payment MUST be applied to the oldest outstanding ticket first, then subsequent oldest tickets as needed.
- **FR-009**: Ticket status MUST be derived from recorded payments: unpaid when no amount has been paid, partially paid when some but not all of the total has been paid, and paid when the total has been paid.
- **FR-010**: The feature MUST show each customer's total outstanding balance and provide a customer list ordered from greatest outstanding balance to least.
- **FR-011**: Customer balances MUST equal the sum of the remaining amounts on that customer's tickets; payments MUST NOT reduce a balance below zero.
- **FR-012**: All feature actions and data viewing MUST work without an internet connection. Data MUST be stored on the device and every change MUST be recorded locally for possible later upload. Uploading changes and backend synchronization are outside this feature.
- **FR-013**: Customer names, labels, phone numbers, tickets, debts, and payment data MUST remain private to the shop and MUST NOT be included in shared or aggregated datasets. Any future statistics are limited to products and prices and are outside this feature.
- **FR-014**: The feature MUST support Arabic and French, including right-to-left layout for Arabic and left-to-right layout for French.
- **FR-015**: Common shopkeeper actions MUST be completable in three taps or fewer, not counting text entry or barcode scanning.
- **FR-016**: The feature MUST provide clear feedback when invalid values prevent a product, ticket, or payment from being saved.

### Key Entities

- **Product**: A catalog item identified by an owner-assigned name and barcode, with a selling price in integer millimes.
- **Customer**: A shop-local ledger identity with a random internal identifier and owner-chosen display label; real identity is optional.
- **Ticket**: A customer's dated credit record containing purchased product lines, quantities, prices at time of sale, total amount, and a payment-derived status.
- **Ticket Line**: A product snapshot and positive quantity within a ticket; contributes to the ticket total.
- **Payment**: A positive amount in integer millimes recorded against a ticket or customer balance, with its allocation to outstanding tickets.
- **Pending Change**: A local record of each create or update action and active-catalog product removal, retained for possible future upload; transmission is out of scope.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In offline testing, 100% of successfully saved customers, products, tickets, and payments remain available after closing and reopening the feature.
- **SC-002**: Owners can create a ticket with at least ten product lines and see the correct total after each quantity change.
- **SC-003**: For all tested ticket and customer payment sequences, ticket status and outstanding balances match the recorded amounts exactly in millimes.
- **SC-004**: In usability testing, at least 90% of owners can complete the common flows of adding a product, adding a customer, recording a ticket, recording a payment, and viewing a balance in three taps or fewer, excluding text entry and barcode scanning.
- **SC-005**: Owners can complete each primary flow in Arabic and French, with Arabic screens presented right-to-left and French screens left-to-right.
- **SC-006**: Review of feature outputs finds no customer identity, debt, ticket, or payment data in shared or aggregated datasets.

## Hypotheses to Validate

- Partial payments are common in participating shops.
- Owners track credit both per ticket and per customer.
- Most products sold by target shops have scannable barcodes.
- Owners are willing to use pseudonymous customer labels instead of entering real identity.

## Assumptions

- A single shop owner operates the feature on one device; multi-device sharing is out of scope.
- Customer-balance payments are allocated to the oldest outstanding ticket first to keep ticket statuses and customer balances consistent.
- Tickets retain an immutable snapshot of each item's name and price at the time of sale.
- The owner can cancel a ticket before saving; catalog changes do not erase saved financial history.
- No account, login, backend sync, inventory quantity, purchase price, reminder, or dashboard functionality is included.
