# Feature Specification: Suppliers and Deliveries

**Feature Branch**: `009-suppliers-deliveries`

**Created**: 2026-11-19

**Status**: Clarified

**Input**: User description: "Second part of roadmap step 1.3. A merchant records their suppliers and the deliveries they receive. A delivery lists shop products with a quantity and a unit purchase price; recording it brings that stock into the shop and updates each product's purchase price. Backend only, like 006 and 007: API under /api/v1, stable error codes, tests including cross-shop isolation, Postman collection and contract updated."

## Context

Spec 007 gave each shop its products, with a sale price and an optional purchase price typed by the merchant. Spec 008 (catalog checking) is on hold, so this feature is the next part of roadmap step 1.3.

A small shop is restocked many times a week: a wholesaler's van, a distributor's salesman, a trip to the wholesale market. Each time, the merchant receives goods, usually with a delivery note, and pays what they cost, at once or later. This feature lets the merchant keep a list of their **suppliers**, record each **delivery** (which products came in, how many, and at what unit purchase price), record each **payment** to a supplier, and see what they still owe each one. Recording a delivery brings that quantity into the shop's stock. A product's purchase price stays the one the merchant set in spec 007; a delivery never changes it.

Money and stock records are never changed after the fact (constitution). A delivery typed wrong is not edited: it is cancelled by a new record, and the right delivery is recorded again.

The merchant app works offline. Suppliers, deliveries and their lines carry ids made by the app, as shop products do since spec 007, so a delivery recorded without a network is sent later, and sending it twice records it once.

This feature ships no screen. The merchant app connects to it in the connected-app spec (013). Each story below can be exercised with an API testing tool.

## Clarifications

### Session 2026-11-20

- Q: How is stock held, given that spec 010 is "stock movements and alerts"? → A: 009 creates the stock movement record, with the "delivery" and "delivery cancellation" kinds only; a product's stock is the sum of its movements. 010 adds corrections, losses, history views and alerts.
- Q: Which purchase price does a delivery set on a product? → A: None. The merchant sets a product's purchase price and changes it when needed (spec 007); a delivery never changes it. Each delivery line keeps the unit price paid.
- Q: Does this feature track what the merchant owes each supplier? → A: Yes: deliveries from a supplier add to what the merchant owes it, payments to it take away from it.
- Q: How do payments to a supplier work? → A: A balance per supplier: total of its deliveries that are not cancelled, minus total of its payments that are not cancelled. A payment goes to the supplier, not to one delivery, with an amount, a date, a method (cash, cheque, card, transfer) and an optional reference. A delivery may carry an amount paid at once and its method, which records a payment in the same change. Paying more than owed is allowed (the balance goes below zero). A wrong payment is cancelled, never edited.
- Q: Can a merchant enter what they already owed a supplier before using the app? → A: No, not in this feature. Every balance starts at 0.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Record a delivery (Priority: P1)

A merchant receives goods. They record the delivery: from which supplier, on which day, the supplier's note number if there is one, and for each product received, how many units came and the unit purchase price. Once recorded, the shop's stock of each product has grown by the quantity received, and what they owe that supplier has grown by the delivery's total, less what they paid at once.

**Why this priority**: This is the purpose of the feature, and the first way stock enters the shop.

**Independent Test**: Create two shop products, record one delivery with both, and read the products back: each has the quantity received in stock and its purchase price unchanged; the delivery shows its lines and its total.

**Acceptance Scenarios**:

1. **Given** products of the shop, **When** the merchant records a delivery with a line per product (quantity and unit purchase price), **Then** the delivery is saved with its lines, its line amounts and its total, and each product's stock grows by its line's quantity.
2. **Given** a recorded delivery, **When** the merchant reads one of its products, **Then** its purchase price and sale price are the ones they had before the delivery (FR-010).
3. **Given** a delivery with no date, **When** it is recorded, **Then** its date is the day it was recorded, in the shop's time zone; a past date is kept as sent, and a future date is refused with a stable code.
4. **Given** a delivery with no line, a quantity of 0 or below, a quantity that is not a whole number, a negative price, the same product on two lines, or a product the shop does not have, **When** it is recorded, **Then** nothing is recorded and the answer says which line and which field is wrong, with a stable code.
5. **Given** a delivery already recorded, **When** the app sends the same delivery again with the same id (a network retry, or an offline device syncing twice), **Then** the delivery exists once, and the stock and the purchase prices changed once.
6. **Given** a delivery with no supplier, **When** it is recorded, **Then** it is accepted (goods bought at the market, for example), and it adds to no supplier's balance.
7. **Given** a delivery from a supplier with an amount paid at once and its method, **When** it is recorded, **Then** a payment to that supplier is recorded in the same change, and the supplier's balance grows by the total less that amount. An amount paid at once on a delivery with no supplier is refused with a stable code.

---

### User Story 2 - Keep a list of my suppliers (Priority: P1)

A merchant adds the people and companies they buy from: a name and, if they want, a phone number. They pick a supplier when recording a delivery, and can correct a supplier's name or phone later.

**Why this priority**: Knowing who delivered what is what makes deliveries useful later (which supplier is cheapest, what came from whom), and it costs a merchant one short form.

**Independent Test**: Create two suppliers, try a third with the same name in another case, see it refused and pointed to the first; rename one; list them.

**Acceptance Scenarios**:

1. **Given** a merchant with a shop, **When** they create a supplier with a name and an optional phone number, **Then** it is saved for their shop.
2. **Given** a supplier named `Grossiste Ben Ali`, **When** the merchant creates `grossiste ben ali`, **Then** it is refused with a stable code pointing to the existing supplier (names are compared as in spec 007).
3. **Given** suppliers, **When** the merchant lists them, **Then** they get them in pages, ordered by name.
4. **Given** a supplier, **When** the merchant changes its name or phone, **Then** the change is saved under the same rules, and its past deliveries show the new name.
5. **Given** a supplier created twice with the same id, **When** the second request arrives, **Then** the supplier exists once.
6. **Given** suppliers, **When** the merchant lists or reads them, **Then** each shows its balance (FR-015).

---

### User Story 3 - Correct a wrong delivery (Priority: P1)

A merchant notices that a delivery was recorded wrong: the wrong quantity, the wrong price, the wrong supplier, or a delivery that never came. They cancel it. The stock it brought in is taken back, the delivery stays visible as cancelled, and they record the right one if needed.

**Why this priority**: Typing mistakes are certain, and records that carry stock and money can never be edited, so cancelling is the only way to fix them.

**Independent Test**: Record a delivery, cancel it, and see the stock back to where it was, the delivery marked cancelled, and a second cancel change nothing.

**Acceptance Scenarios**:

1. **Given** a recorded delivery, **When** the merchant cancels it, **Then** a cancellation is recorded, each of its products' stock goes down by the quantity the delivery brought, and the delivery shows as cancelled with the cancellation date.
2. **Given** a cancelled delivery, **When** the merchant cancels it again, **Then** nothing changes and the answer is the cancelled delivery.
3. **Given** a cancelled delivery from a supplier, **When** the supplier is read, **Then** its balance no longer counts that delivery's total.
4. **Given** a cancelled delivery that was recorded with an amount paid at once, **When** the supplier is read, **Then** that payment still counts, since the money was really paid; the merchant cancels the payment too if it was also wrong.
5. **Given** any delivery, **When** the merchant tries to change or delete it or one of its lines, **Then** there is no way to do so.

---

### User Story 4 - Pay my suppliers and know what I owe (Priority: P1)

A merchant pays a supplier, in cash on the spot or later by cheque or transfer, for one delivery or several at once. They record the payment and see at any time what they still owe each supplier.

**Why this priority**: Most small shops buy on credit from their suppliers; knowing what is owed is half the reason to record deliveries.

**Independent Test**: Record two deliveries from one supplier, pay part of their total, read the supplier's balance; cancel the payment and read it again.

**Acceptance Scenarios**:

1. **Given** a supplier, **When** the merchant records a payment with an amount, a method and optionally a date and a reference, **Then** it is saved, and the supplier's balance goes down by the amount.
2. **Given** a payment with no date, **When** it is recorded, **Then** its date is the day it was recorded, in the shop's time zone; a past date is kept, a future date is refused with a stable code.
3. **Given** a payment of 0 or below, an unknown method, or no supplier, **When** it is recorded, **Then** nothing is recorded and the answer names the field with a stable code.
4. **Given** a payment larger than the balance, **When** it is recorded, **Then** it is accepted and the balance goes below zero.
5. **Given** a recorded payment, **When** the merchant cancels it, **Then** a cancellation is recorded, the balance goes back up by the amount, the payment shows as cancelled with the cancellation date, and cancelling it again changes nothing.
6. **Given** payments, **When** the merchant lists them, **Then** they get them in pages, latest payment date first, optionally for one supplier.
7. **Given** a payment sent twice with the same id, **When** the second request arrives, **Then** the payment exists once and the balance changed once.

---

### User Story 5 - See my past deliveries (Priority: P2)

A merchant looks back at what they received: all deliveries, newest first, or those of one supplier, and opens one to see its lines.

**Why this priority**: Checking a supplier's invoice or a price paid last month needs it, but recording deliveries comes first.

**Independent Test**: Record deliveries from two suppliers on different dates; list all, then one supplier's; open one.

**Acceptance Scenarios**:

1. **Given** deliveries, **When** the merchant lists them, **Then** they get them in pages, latest delivery date first, each with its supplier, date, note number, total, number of lines and whether it was cancelled.
2. **Given** a supplier, **When** the merchant lists deliveries for that supplier, **Then** they get only that supplier's deliveries.
3. **Given** a delivery, **When** the merchant reads it, **Then** they get its lines with product, quantity, unit purchase price and amount.

---

### User Story 6 - Only my shop's suppliers, deliveries and payments (Priority: P1)

No merchant can see or use another shop's suppliers, deliveries, payments or products.

**Why this priority**: Isolation between shops is a core promise of the product (constitution IV).

**Independent Test**: Two shops each create a supplier and a delivery; each merchant is refused the other's, and cannot put the other's supplier or products in a delivery.

**Acceptance Scenarios**:

1. **Given** two merchants, **When** the first reads, changes or cancels a supplier, a delivery or a payment of the second's shop, **Then** it is refused with the same stable code as for one that does not exist.
2. **Given** a delivery or a payment naming another shop's supplier, or a delivery naming another shop's product, **When** it is recorded, **Then** nothing is recorded and the answer is the same as for an unknown supplier or product.
3. **Given** an id already used by another shop's supplier, delivery, line, payment or cancellation, **When** the merchant creates a record with it, **Then** it is refused with a stable code and nothing about the other record is revealed.
4. **Given** a merchant with no shop, **When** they use any supplier, delivery or payment endpoint, **Then** it is refused with a stable code.

---

### User Story 7 - Try it with standard tools (Priority: P3)

A developer or the owner imports the updated API collection and runs the supplier and delivery journey after the 006 and 007 journeys: create a supplier, record a delivery, read a product's stock, read the supplier's balance, pay the supplier, cancel the payment and the delivery, list deliveries and payments.

**Why this priority**: There is no screen until spec 013, so the collection is how the feature is checked.

**Independent Test**: Import the collection, run it in order against a local backend, without editing any request.

**Acceptance Scenarios**:

1. **Given** the collection and a local backend, **When** the requests are run in order, **Then** each succeeds and values carry over between requests by themselves.
2. **Given** a change to an endpoint, **When** it is merged, **Then** the collection and the contract file change with it.

### Edge Cases

- **Free goods** (a supplier's bonus units): a unit price of 0 is allowed; the line adds stock and nothing to the total.
- **Unit price different from the product's purchase price**: allowed and kept on the line; the product's purchase price does not change (FR-010). The app may suggest the product's purchase price as the unit price.
- **Very large delivery**: up to 200 lines; a larger one is refused with a stable code and the merchant splits it.
- **Amounts**: a line amount is at most 999 999 × 999 999 999 millimes; amounts and totals are whole millimes and never rounded.
- **Delivery dated before the shop was created in the app**: allowed, since the merchant may enter older deliveries; only future dates are refused.
- **Supplier renamed**: past deliveries and payments show the current name; the supplier's id never changes.
- **Paying more than owed**: allowed; the balance goes below zero, meaning the supplier owes the merchant goods or money.
- **Debt from before the app**: not entered in this feature; every balance starts at 0.
- **Cancelling a delivery recorded with a payment**: only the delivery is cancelled; the payment stays until the merchant cancels it.
- **Payment sent offline before its supplier reached the server**: refused as an unknown supplier, like a delivery.
- **Same supplier name in two shops**: allowed; names are unique per shop only.
- **Delivery sent offline before its supplier reached the server**: refused as an unknown supplier, and nothing is recorded; the order of offline operations is handled by the sync spec (013).
- **Product whose catalog product is still waiting to be checked**: can be delivered like any other shop product.
- **Stock below zero**: cannot happen through deliveries and cancellations alone, since a cancellation takes back only what its delivery brought. Sales (011) and losses (010) may later bring it below zero; this feature does not refuse it.
- **Cancelling a delivery whose products were delivered again since**: allowed; only that delivery's quantities are taken back.
- **Two identical deliveries sent at the same moment**: exactly one is recorded, and stock grows once.

## Requirements *(mandatory)*

### Functional Requirements

**Suppliers**

- **FR-001**: A merchant MUST be able to create suppliers for their shop, each with a name (required, at most 120 characters after trimming spaces) and an optional phone number, given with its international dialing code as in spec 006.
- **FR-002**: Two suppliers of the same shop MUST NOT have the same name once compared as in spec 007 FR-015; the refusal MUST use a stable code and point to the existing supplier.
- **FR-003**: A merchant MUST be able to list their suppliers in pages ordered by name, read one, and change its name and phone. Suppliers MUST NOT be deleted in this feature, so their deliveries always point to them.

**Deliveries**

- **FR-004**: A merchant MUST be able to record a delivery with an optional supplier of their shop, a delivery date, an optional supplier document reference (at most 50 characters), and 1 to 200 lines. Each line names one product of the shop, a whole quantity from 1 to 999 999 and a unit purchase price in millimes from 0 to 999 999 999. A product MUST appear at most once in a delivery.
- **FR-005**: The delivery date MUST default to the day the delivery is recorded, in the shop's time zone. It MAY be a past date and MUST NOT be after that day.
- **FR-006**: The system MUST compute each line's amount (quantity × unit price) and the delivery's total in whole millimes; the app does not send them.
- **FR-007**: A delivery and its lines MUST be recorded all together or not at all. A wrong line MUST stop the whole delivery, and the answer MUST name the line and the field with a stable code.
- **FR-008**: A merchant MUST be able to list their deliveries in pages, latest delivery date first, optionally for one supplier, and read one with its lines.

**Stock and purchase prices**

- **FR-009**: Recording a delivery MUST record one stock movement of kind "delivery" per line, adding the line's quantity, in the same change as the delivery. A product's stock is the sum of its stock movements, and every shop product MUST show its current stock quantity. Stock movements of other kinds come with spec 010.
- **FR-010**: Recording or cancelling a delivery MUST NOT change any product's purchase price or sale price; the merchant sets them as in spec 007. Each line MUST keep its own unit price forever.
- **FR-011**: A product that has never been delivered MUST show a stock of 0.

**Corrections**

- **FR-012**: Deliveries, their lines, cancellations and stock records MUST never be changed or deleted. A wrong delivery MUST be corrected by cancelling it with a new record and, if needed, recording a new delivery.
- **FR-013**: Cancelling a delivery MUST record, in the same change, one stock movement of kind "delivery cancellation" per line, taking back the quantity that line added. A delivery is cancelled as a whole. A cancelled delivery stays readable, marked cancelled with the date of its cancellation.
- **FR-014**: Cancelling a delivery that is already cancelled MUST change nothing and answer with the delivery.

**Supplier debt and payments**

- **FR-015**: Every supplier MUST show its balance: the total of its deliveries that are not cancelled, minus the total of its payments that are not cancelled, in millimes. It starts at 0 and MAY go below zero. A delivery with no supplier counts in no balance.
- **FR-022**: A merchant MUST be able to record a payment to one supplier of their shop with an amount in millimes from 1 to 999 999 999 999, a method (cash, cheque, card or transfer), a payment date and an optional reference (at most 50 characters, for a cheque or transfer number). The payment date follows the rules of FR-005.
- **FR-023**: A delivery from a supplier MAY carry an amount paid at once (same range) and its method. It MUST then record a payment to that supplier, dated the delivery date, in the same change as the delivery. An amount paid at once on a delivery with no supplier MUST be refused with a stable code.
- **FR-024**: A payment is never changed or deleted. Cancelling it MUST record a cancellation, after which it no longer counts in the balance; cancelling a cancelled payment MUST change nothing and answer with the payment. Cancelling a delivery MUST NOT cancel a payment recorded with it.
- **FR-025**: A merchant MUST be able to list their payments in pages, latest payment date first, optionally for one supplier, and read one.

**Offline and repeated requests**

- **FR-016**: Suppliers, deliveries, delivery lines, payments and cancellations MUST carry ids generated by the app. Sending the same creation twice MUST create it once, change stock and prices once, and answer with the record as it exists. An id used by another shop's record MUST be refused with a stable code that reveals nothing about that record.
- **FR-017**: Two identical creations arriving at the same moment MUST result in exactly one record.

**Isolation**

- **FR-018**: Every supplier, delivery and payment request MUST require a signed-in merchant who owns a shop. Another shop's suppliers, deliveries, payments and products MUST be answered as not existing, and MUST never be usable in a delivery or a payment.

**API conventions** (as in 006 and 007)

- **FR-019**: Every endpoint MUST sit under the versioned API path, accept and return JSON only, and use the common error shape with stable codes and no user-facing text.
- **FR-020**: Every endpoint MUST have automated tests for success, validation errors, unauthenticated access, a merchant with no shop, and access to another shop's data. The rules of stock, balance, cancellation and repeated requests MUST have their own tests.
- **FR-021**: The feature MUST update the importable API collection and the contract file with every endpoint.

### Key Entities

- **Supplier**: someone a shop buys from. Belongs to one shop. Name, optional phone, and a balance computed from its deliveries and payments. Never deleted.
- **Delivery**: goods received by a shop on one date, optionally from one supplier, with an optional supplier document reference and a total. Never changed or deleted; it may be cancelled.
- **Delivery line**: one product of the shop in a delivery, with a whole quantity, a unit purchase price and an amount. Never changed or deleted.
- **Delivery cancellation**: the record that cancels one delivery as a whole and takes back its stock. Never changed or deleted.
- **Stock movement**: one change to a shop product's stock, with a kind ("delivery" or "delivery cancellation" in this feature) and a signed whole quantity, pointing to the delivery line it comes from. Never changed or deleted. A product's stock is the sum of its movements.
- **Supplier payment**: money paid to one supplier on one date, with an amount, a method and an optional reference; optionally recorded with a delivery. Never changed or deleted; it may be cancelled.
- **Payment cancellation**: the record that cancels one payment. Never changed or deleted.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A merchant records a delivery of up to 200 products in one request.
- **SC-002**: In tests, after any series of deliveries and cancellations, 100% of products show a stock equal to the quantities delivered minus the quantities cancelled.
- **SC-003**: In tests, sending the same supplier, delivery, payment or cancellation twice leaves exactly one record, and stock and balances change once, in 100% of cases.
- **SC-004**: In tests, 100% of attempts by one merchant to read, use, cancel or reuse the id of another shop's supplier, delivery, payment or product are refused and reveal nothing about it.
- **SC-005**: In tests, no delivery, line, payment, cancellation or stock movement is ever changed or deleted after it is created.
- **SC-006**: In tests, 100% of deliveries with one wrong line record nothing and name that line.
- **SC-007**: A person who has never seen the project imports the collection and runs the supplier, delivery and payment journey in under 10 minutes without editing a request.
- **SC-008**: In tests, after any series of deliveries, payments and cancellations, 100% of suppliers show a balance equal to their non-cancelled delivery totals minus their non-cancelled payments.

## Assumptions

- **Supplier on a delivery**: optional. Many small shops buy at the wholesale market from people they do not record; such a delivery has no supplier.
- **Products on a delivery**: every line names an existing product of the shop. A product received for the first time is created first with spec 007's rules (barcode, catalog or own name, duplicate checks), then delivered; the app does both, one after the other, offline as online. A delivery never creates products.
- **Delivery date**: today in the shop's time zone by default; any past date is accepted; a future date is refused.
- **Correcting a delivery**: only by cancelling it as a whole and recording it again; there is no partial cancellation and no line correction in this feature.
- **Quantities** are whole units until roadmap step 1.9 (decimal quantities and units of sale). A product sold by weight is delivered in the merchant's unit of sale, as in spec 007.
- **Money**: integer millimes in the shop's currency (Tunisian dinar, spec 006). No tax, discount or delivery fee per line or per delivery in this feature; the unit price is what the merchant paid per unit.
- **Payment methods**: cash, cheque, card and transfer, as for customer payments (constitution VII). A cheque's due date and bank are not recorded in this feature.
- **Supplier debt**: a balance per supplier, not per delivery: a payment is not tied to the deliveries it pays. Debt owed before the merchant started using the app is not entered in this feature.
- **Purchase price**: set and changed by the merchant on the product (spec 007); deliveries never change it.
- **Opening stock**: products start at 0. Setting the stock a shop already has on the day it starts, manual corrections and losses come with spec 010.
- **Sale price**: a delivery never changes a product's sale price.
- **Supplier phone**: personal data; it is never written to logs (constitution VIII) and is shown only to its shop.
- **Supplier catalog**: suppliers do not share a list across shops in this feature; wholesalers as actors come with phase 3 of the roadmap.
- **Roles**: the shop owner is the only role; employees come with spec 014.
- **Backend only**: no screen; the merchant app records deliveries offline and sends them in the connected-app spec (013).
