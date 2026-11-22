# Feature Specification: Contacts, Transactions and Payments (purchases)

**Feature Branch**: `009-suppliers-deliveries`

**Created**: 2026-11-19 · **Reworked**: 2026-11-21

**Status**: Closed on 2026-11-21 (merged)

**Input**: User description: "Second part of roadmap step 1.3. A merchant keeps an address book of contacts, records the goods they buy as transactions that bring stock in, and records the money paid. Transactions are one module for purchases and, later, sales. Payments are only recorded, never processed. Backend only, like 006 and 007."

## Context

Spec 007 gave each shop its products. This feature lets a merchant record what they buy, as the first part of a design that sales (spec 011) will reuse instead of copying.

It is built from three independent parts:

- **Contacts**: the shop's address book. A contact is a person or business the merchant deals with: a supplier, a customer, or both (a neighbouring shop that sells to them and buys from them).
- **Transactions**: an exchange of products for money. A transaction has a kind, lines of products with quantities and unit prices, and a total. This feature delivers the **purchase** kind, which brings stock in; spec 011 adds the **sale** kind, which takes stock out, on the same records.
- **Payments**: money that changed hands, recorded after the fact. The app never moves money, talks to no bank or card terminal, and checks nothing with anyone: the merchant writes down what was paid. A payment belongs to a contact and may be tied to one transaction; a transaction may be paid in several payments.

Money and stock records are never changed after the fact (constitution). A wrong transaction or payment is cancelled by a new record, with an optional reason and the account that cancelled it.

The merchant app works offline: every record carries an id made by the app, and sending it twice records it once.

This feature ships no screen. The merchant app connects to it in spec 013. Each story can be exercised with an API testing tool.

## Clarifications

### Session 2026-11-20 (still valid)

- Q: How is stock held, given that spec 010 is "stock movements and alerts"? → A: This feature creates the stock movement record; a product's stock is the sum of its movements. 010 adds corrections, losses, history views and alerts.
- Q: Does a purchase change a product's purchase price? → A: No. The merchant sets it on the product (spec 007). Each line keeps the unit price paid.
- Q: Can a merchant enter debt from before the app? → A: No, not in this feature. Every balance starts at 0.

### Session 2026-11-21 (owner rework)

- Q: How are suppliers kept? → A: In one address book of contacts, independent of any transaction. A contact can be a supplier, a customer, or both.
- Q: Are purchases and sales separate? → A: No. One Transactions module holds both, with a kind. This feature delivers purchases; sales come with spec 011.
- Q: Does the app handle payments? → A: No. It only records money and products. A payment is a record of money paid or received, nothing more.
- Q: How do payments relate to transactions and contacts? → A: A payment belongs to a contact and may be linked to one transaction. A transaction may have many payments, when it is paid in several times. A payment with no transaction is "on account" with that contact.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Keep my address book of contacts (Priority: P1)

A merchant adds the people and businesses they deal with: a name, and if they want a phone number, an address and a note. They mark each one as a supplier, a customer, or both, and can correct any of it later.

**Why this priority**: Every purchase from a known supplier, and later every sale on credit, names a contact.

**Independent Test**: Create two contacts, try a third with the same name in another case and see it refused and pointed to the first; mark one as both supplier and customer; list suppliers only.

**Acceptance Scenarios**:

1. **Given** a merchant with a shop, **When** they create a contact with a name and, optionally, a phone, an address, a note and its roles, **Then** it is saved for their shop.
2. **Given** a contact named `Grossiste Ben Ali`, **When** the merchant creates `grossiste ben ali`, **Then** it is refused with a stable code pointing to the existing contact (names compared as in spec 007).
3. **Given** contacts, **When** the merchant lists them, optionally only suppliers or only customers, **Then** they get them in pages, ordered by name, each with its balance (User Story 4).
4. **Given** a contact, **When** the merchant changes its name, phone, address, note or roles, **Then** the change is saved under the same rules, and its past transactions and payments show the new name.
5. **Given** a contact created twice with the same id, **When** the second request arrives, **Then** the contact exists once.

---

### User Story 2 - Record a purchase (Priority: P1)

A merchant receives goods. They record a purchase: from which contact if they know it, on which day, the supplier's note number if there is one, and for each product how many units came and at what unit price. The shop's stock of each product grows by the quantity received.

**Why this priority**: This is how stock enters the shop.

**Independent Test**: Create two products, record one purchase with both, read the products back: each has the quantity received in stock and its prices unchanged; the purchase shows its lines and its total.

**Acceptance Scenarios**:

1. **Given** products of the shop, **When** the merchant records a purchase with a line per product (quantity and unit price), **Then** the purchase is saved with its lines, line amounts and total, and each product's stock grows by its line's quantity.
2. **Given** a recorded purchase, **When** the merchant reads one of its products, **Then** its purchase price and sale price are unchanged.
3. **Given** a purchase with no date, **When** it is recorded, **Then** its date is today in the shop's time zone; a past date is kept; a future date is refused with a stable code.
4. **Given** a purchase with no line, a quantity of 0 or below or not a whole number, a negative price, the same product on two lines, or a product the shop does not have, **When** it is recorded, **Then** nothing is recorded and the answer names the line and the field with a stable code.
5. **Given** a purchase with no contact, **When** it is recorded, **Then** it is accepted (goods bought at the market) and counts in no contact's balance.
6. **Given** a purchase sent twice with the same id, **When** the second request arrives, **Then** the purchase exists once and stock changed once.
7. **Given** a kind other than purchase, **When** a transaction is recorded, **Then** it is refused with a stable code (sales come with spec 011).

---

### User Story 3 - Record what I paid (Priority: P1)

A merchant pays a supplier, at once or later, in one or several times, in cash, by cheque, card or transfer. They write the payment down: for one purchase, or on account with the contact. They see what is left to pay on each purchase and what they owe each contact.

**Why this priority**: Most small shops buy on credit; knowing what is owed is half the reason to record purchases.

**Independent Test**: Record a purchase of 54 000 from a contact, pay 20 000 for it, then 10 000 on account; read the purchase (left to pay 34 000) and the contact (owed 24 000).

**Acceptance Scenarios**:

1. **Given** a purchase from a contact, **When** the merchant records a payment linked to it, with an amount, a method and optionally a date and a reference, **Then** it is saved, the purchase's amount left to pay goes down, and the contact's balance goes down.
2. **Given** a purchase, **When** it is paid in several payments, **Then** each is kept, and the purchase shows the total paid, what is left, and whether it is unpaid, partly paid or paid.
3. **Given** a payment linked to a purchase, **When** its amount is larger than what is left to pay on that purchase, **Then** it is refused with a stable code; the merchant records the extra on account instead.
4. **Given** a contact, **When** the merchant records a payment with no purchase, **Then** it is saved on account: the contact's balance goes down, and no purchase's amount left to pay changes. Paying more than owed on account is allowed; the balance goes below zero.
5. **Given** a purchase with no contact, **When** the merchant pays it, **Then** the payment is linked to the purchase and has no contact.
6. **Given** a payment with neither contact nor purchase, a contact different from the purchase's contact, a cancelled purchase, an amount of 0 or below, or an unknown method, **When** it is recorded, **Then** nothing is recorded and the answer names the field with a stable code.
7. **Given** a payment with no date, **When** it is recorded, **Then** its date is today in the shop's time zone; a future date is refused.
8. **Given** a payment sent twice with the same id, **When** the second request arrives, **Then** it exists once and balances changed once.

---

### User Story 4 - Know what I owe each contact (Priority: P1)

For every contact the merchant sees one figure: what they owe that contact, or what the contact owes them when it is below zero.

**Why this priority**: It is the figure a merchant checks before paying a supplier or asking a customer to pay.

**Independent Test**: Record purchases and payments for a contact, cancel one of each, read the contact.

**Acceptance Scenarios**:

1. **Given** a contact, **When** it is read or listed, **Then** its balance is the total of its purchases that are not cancelled, minus the total of the payments made to it that are not cancelled, plus the total of payments received from it that are not cancelled, in millimes. It starts at 0 and may go below zero.
2. **Given** a cancelled purchase or payment, **When** the contact is read, **Then** it no longer counts.

---

### User Story 5 - Correct a mistake (Priority: P1)

A purchase or a payment was recorded wrong. The merchant cancels it, optionally saying why. It stays visible as cancelled, with when and by whom; a purchase's stock is taken back. They record the right one if needed.

**Why this priority**: Mistakes are certain, and money and stock records can never be edited.

**Independent Test**: Record a purchase, pay part of it, try to cancel the purchase and be refused; cancel the payment, then the purchase; see the stock back, both marked cancelled, and a second cancel change nothing.

**Acceptance Scenarios**:

1. **Given** a recorded purchase with no payment still counting, **When** the merchant cancels it with an optional reason, **Then** a cancellation is recorded with the reason and the account that cancelled it, each product's stock goes down by the quantity the purchase brought, and the purchase shows as cancelled with the time, the reason and the account.
2. **Given** a purchase with payments that are not cancelled, **When** the merchant cancels it, **Then** it is refused with a stable code: those payments are cancelled first, or the merchant keeps them as real money paid by recording them again on account.
3. **Given** a recorded payment, **When** the merchant cancels it with an optional reason, **Then** a cancellation is recorded the same way, and it stops counting in the purchase's amount paid and in the contact's balance.
4. **Given** a cancelled purchase or payment, **When** the merchant cancels it again, **Then** nothing changes and the answer is the cancelled record.
5. **Given** any purchase, line or payment, **When** the merchant tries to change or delete it, **Then** there is no way to do so.

---

### User Story 6 - Look back at my purchases and payments (Priority: P2)

A merchant lists their purchases, newest first, all or for one contact, and opens one to see its lines and its payments. They list their payments, all, for one contact or for one purchase.

**Why this priority**: Checking a supplier's invoice or a price paid last month needs it, but recording comes first.

**Independent Test**: Record purchases and payments for two contacts on different dates; list all, then one contact's; open one purchase.

**Acceptance Scenarios**:

1. **Given** transactions, **When** the merchant lists them, **Then** they get them in pages, latest date first, each with its kind, contact, date, reference, total, amount paid, amount left, payment state and whether it was cancelled; optionally only one kind or one contact.
2. **Given** a purchase, **When** the merchant reads it, **Then** they get its lines (product, quantity, unit price, amount) and its payments.
3. **Given** payments, **When** the merchant lists them, **Then** they get them in pages, latest date first, optionally for one contact or one transaction.

---

### User Story 7 - Only my shop's records (Priority: P1)

No merchant can see or use another shop's contacts, transactions, payments or products.

**Why this priority**: Isolation between shops is a core promise of the product (constitution IV).

**Independent Test**: Two shops each create a contact, a purchase and a payment; each merchant is refused the other's and cannot use the other's contact, product or purchase.

**Acceptance Scenarios**:

1. **Given** two merchants, **When** the first reads, changes or cancels a contact, transaction or payment of the second's shop, **Then** it is refused with the same stable code as for one that does not exist.
2. **Given** a transaction or payment naming another shop's contact, product or transaction, **When** it is recorded, **Then** nothing is recorded and the answer is the same as for an unknown one.
3. **Given** an id already used by another shop's record, **When** the merchant creates a record with it, **Then** it is refused with a stable code that reveals nothing about the other record.
4. **Given** a merchant with no shop, **When** they use any of these endpoints, **Then** it is refused with a stable code.

---

### User Story 8 - Try it with standard tools (Priority: P3)

A developer or the owner runs the updated API collection after the 006 and 007 journeys: create a contact, record a purchase, read a product's stock, pay part of the purchase and pay on account, read the contact's balance, cancel a payment then the purchase, list everything.

**Why this priority**: There is no screen until spec 013, so the collection is how the feature is checked.

**Independent Test**: Import the collection and run it in order against a local backend without editing a request.

**Acceptance Scenarios**:

1. **Given** the collection and a local backend, **When** the requests run in order, **Then** each succeeds and values carry over by themselves.
2. **Given** a change to an endpoint, **When** it is merged, **Then** the collection and the contract file change with it.

### Edge Cases

- **Free goods**: a unit price of 0 is allowed; the line adds stock and nothing to the total.
- **Unit price different from the product's purchase price**: allowed and kept on the line; the product's prices never change.
- **Very large purchase**: up to 200 lines; larger is refused and the merchant splits it.
- **Totals**: whole millimes, never rounded; a total above 9 007 199 254 740 991 is refused so the app can hold it exactly.
- **Contact with no role**: allowed; roles only help the merchant filter the address book. Any contact of the shop can be named on a purchase or a payment.
- **Contact both supplier and customer**: one balance, netting what each owes the other. Sales on credit count in it from spec 011.
- **Payment received from a supplier** (a refund in cash): recorded as a payment received, on account; the balance goes up.
- **Payment on account then a purchase paid by it**: the merchant's call; the purchase still shows as unpaid because no payment is linked to it, while the contact's balance is right. Spreading payments on account over purchases is not done in this feature.
- **Contact renamed**: past records show the current name; its id never changes.
- **Debt from before the app**: not entered; balances start at 0.
- **Offline record sent before its contact, product or purchase reached the server**: refused as unknown, nothing recorded; ordering offline operations is spec 013's job.
- **Stock below zero**: cannot happen through purchases and their cancellations alone; sales (011) and losses (010) may cause it later, and this feature does not refuse it.
- **Two identical creations at the same moment**: exactly one record, and stock and balances change once.
- **Same contact name in two shops**: allowed; names are unique per shop.

## Requirements *(mandatory)*

### Functional Requirements

**Contacts**

- **FR-001**: A merchant MUST be able to create contacts for their shop, each with a name (required, at most 120 characters after trimming), an optional phone with its international dialing code as in spec 006, an optional address (at most 255 characters), an optional note (at most 255 characters), and two independent roles, supplier and customer, each on or off.
- **FR-002**: Two contacts of the same shop MUST NOT have the same name once compared as in spec 007 FR-015; the refusal MUST use a stable code and point to the existing contact.
- **FR-003**: A merchant MUST be able to list contacts in pages ordered by name, optionally only suppliers or only customers, read one, and change any of its fields. Contacts MUST NOT be deleted in this feature.

**Transactions**

- **FR-004**: A merchant MUST be able to record a transaction of kind purchase, with an optional contact of their shop, a date, an optional reference (at most 50 characters), and 1 to 200 lines. Each line names one product of the shop, a whole quantity from 1 to 999 999 and a unit price in millimes from 0 to 999 999 999. A product MUST appear at most once. Any other kind MUST be refused with a stable code in this feature.
- **FR-005**: Transaction and payment dates MUST default to today in the shop's time zone, MAY be in the past, and MUST NOT be after today.
- **FR-006**: The system MUST compute each line's amount and the transaction's total in whole millimes; the app does not send them. A total above 9 007 199 254 740 991 MUST be refused with a stable code.
- **FR-007**: A transaction and its lines MUST be recorded all together or not at all; a wrong line MUST stop the whole transaction, and the answer MUST name the line and the field.
- **FR-008**: Every transaction MUST show its amount paid (its linked payments that are not cancelled), its amount left (total minus amount paid) and its payment state: unpaid, partly paid or paid.
- **FR-009**: A merchant MUST be able to list transactions in pages, latest date first, optionally by kind and by contact, and read one with its lines and its payments.

**Stock**

- **FR-010**: Recording a purchase MUST record, in the same change, one stock movement per line adding the line's quantity. A product's stock is the sum of its movements and every shop product MUST show it; a product never bought shows 0.
- **FR-011**: Recording or cancelling a transaction MUST NOT change any product's purchase or sale price. Each line keeps its unit price forever.

**Payments**

- **FR-012**: A merchant MUST be able to record a payment with a direction (paid by the shop, or received by the shop), an amount in millimes from 1 to 999 999 999 999, a method (cash, cheque, card or transfer), a date, an optional reference (at most 50 characters), a contact of their shop and/or one transaction of their shop. At least one of contact and transaction MUST be given. The system records money only; it MUST NOT contact any bank, card terminal or payment service.
- **FR-013**: A payment linked to a transaction MUST match it: same contact (or no contact when the transaction has none), the direction the transaction's kind implies (paid by the shop for a purchase), a transaction that is not cancelled, and an amount no larger than the transaction's amount left. Each mismatch MUST be refused with a stable code.
- **FR-014**: A payment with a contact and no transaction is on account: it counts in the contact's balance and in no transaction's amount paid. It MAY make the balance go below zero.
- **FR-015**: A merchant MUST be able to list payments in pages, latest date first, optionally by contact and by transaction, and read one.

**Balances**

- **FR-016**: Every contact MUST show its balance in millimes: its purchases not cancelled, minus payments paid by the shop to it not cancelled, plus payments received by the shop from it not cancelled. It starts at 0 and may go below zero. Records with no contact count in no balance. Spec 011 adds sales to this formula.

**Corrections**

- **FR-017**: Transactions, lines, payments, cancellations and stock movements MUST never be changed or deleted. A wrong transaction or payment MUST be corrected by cancelling it with a new record and, if needed, recording a new one.
- **FR-018**: A cancellation MUST record an optional reason (at most 255 characters), the account that cancelled and the time. Cancelling a cancelled record MUST change nothing and answer with the record.
- **FR-019**: Cancelling a purchase MUST record, in the same change, one stock movement per line taking back that line's quantity. A transaction is cancelled as a whole. A transaction with payments that are not cancelled MUST NOT be cancelled; the refusal MUST use a stable code.
- **FR-020**: Every transaction and payment MUST keep the account that recorded it.

**Offline, repeats and isolation**

- **FR-021**: Contacts, transactions, lines, payments and cancellations MUST carry ids made by the app. Sending the same creation twice MUST create it once and answer the record as it exists. An id used by another shop's record MUST be refused with a stable code that reveals nothing.
- **FR-022**: Two identical creations at the same moment MUST result in exactly one record.
- **FR-023**: Every request MUST require a signed-in merchant who owns a shop. Another shop's contacts, transactions, payments and products MUST be answered as not existing and MUST never be usable.

**API conventions** (as in 006 and 007)

- **FR-024**: Every endpoint MUST sit under the versioned API path, accept and return JSON only, and use the common error shape with stable codes and no user-facing text.
- **FR-025**: Every endpoint MUST have automated tests for success, validation errors, unauthenticated access, a merchant with no shop and another shop's data. Stock, amounts paid, balances, cancellations and repeated requests MUST have their own tests.
- **FR-026**: The feature MUST update the importable API collection and the contract file with every endpoint.

### Key Entities

- **Contact**: someone a shop deals with. Belongs to one shop. Name, optional phone, address and note, supplier and customer roles, and a balance computed from its transactions and payments. Never deleted.
- **Transaction**: an exchange of products for money on one date: a kind (purchase in this feature; sale from spec 011), an optional contact, an optional reference, a total, the account that recorded it, and its amount paid and left computed from its payments. Never changed or deleted; it may be cancelled.
- **Transaction line**: one product of the shop in a transaction, with a whole quantity, a unit price and an amount. Never changed or deleted.
- **Payment**: money paid or received on one date: a direction, an amount, a method, an optional reference, a contact and/or one transaction, and the account that recorded it. Never changed or deleted; it may be cancelled.
- **Cancellation**: the record that cancels one transaction or one payment, with an optional reason, the account and the time. Never changed or deleted.
- **Stock movement**: one change to a product's stock, with a kind (purchase or purchase cancellation in this feature) and a signed whole quantity, pointing to the line it comes from. Never changed or deleted.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A merchant records a purchase of up to 200 products in one request.
- **SC-002**: In tests, after any series of purchases and cancellations, 100% of products show a stock equal to the quantities bought minus the quantities cancelled.
- **SC-003**: In tests, after any series of purchases, payments and cancellations, 100% of transactions show an amount paid equal to their linked payments not cancelled, and 100% of contacts show the balance of FR-016.
- **SC-004**: In tests, sending any creation or cancellation twice leaves exactly one record, and stock and balances change once, in 100% of cases.
- **SC-005**: In tests, 100% of attempts to read, use, cancel or reuse the id of another shop's record are refused and reveal nothing.
- **SC-006**: In tests, no transaction, line, payment, cancellation or stock movement is ever changed or deleted.
- **SC-007**: A person who has never seen the project runs the collection's contact, purchase and payment journey in under 10 minutes without editing a request.

## Assumptions

- **Kinds**: only purchase in this feature. Sale, returns and their stock effects come with spec 011; the records are built to hold them.
- **Roles**: labels for the address book; they do not restrict which contact a transaction or payment names.
- **Products on a purchase**: every line names an existing product of the shop; a new product is created first with spec 007's rules. A purchase never creates products.
- **Paid at once**: recorded as a second request, a payment linked to the purchase; the app sends both, offline as online.
- **Payments on account** are not spread over transactions; a transaction's amount paid counts only payments linked to it.
- **Correcting**: only by cancelling as a whole and recording again; no partial cancellation, no line correction, no returns in this feature.
- **Quantities** are whole units until roadmap step 1.9. **Money** is integer millimes in the shop's currency; no tax, discount or fee in this feature.
- **Cheques**: due date and bank are not recorded.
- **Opening stock**, corrections and losses come with spec 010; products start at 0.
- **Contact phone and address** are personal data: never written to logs (constitution VIII) and shown only to their shop.
- **Roles of accounts**: the shop owner is the only one; employees come with spec 014. The account that records or cancels is still kept now, for that spec.
- **Backend only**: no screen until spec 013.
