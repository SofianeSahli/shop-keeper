# Feature Specification: Stock Movements and Low-Stock Warning

**Feature Branch**: `010-stock-movements`

**Created**: 2026-11-21

**Status**: Draft

**Input**: User description: "Last part of roadmap step 1.3. Spec 009 records stock movements for purchases and their cancellations, in the Inventory module. This feature lets a merchant set the stock they already have when they start, correct the stock after counting the shelves, record losses (broken, expired, stolen, used by the shop), see a product's stock history, and be warned when a product runs low. Backend only, like 006 to 009: API under /api/v1, stable error codes, tests including cross-shop isolation, Postman collection and contract updated."

## Context

Spec 009 made a product's stock the sum of its stock movements, and recorded two kinds of movement: a purchase brings units in, a purchase cancellation takes them back. Every shop product shows its stock, and a product never bought shows 0.

A merchant who starts using the app already has goods on the shelves, and the shelves never match the figures for long: things break, expire, disappear or are used by the shop, and counts reveal mistakes. This feature adds the movements that cover those cases, a product's stock history, and a list of products that are running low.

The rules of 009 still hold:

- A product's stock changes only by recording a movement; nothing sets it directly. The purchase movements of 009 stay as they are.
- Movements are never changed or deleted. A wrong correction or loss is fixed by a new movement.
- Quantities are whole units until roadmap step 1.9.
- The merchant app works offline: every record carries an id made by the app, and sending it twice records it once.
- Every record keeps the account that made it, for spec 014 (employees).

This feature ships no screen. The merchant app connects to it in spec 013. Each story can be exercised with an API testing tool.

## Clarifications

### Session 2026-11-21

- Q: Correcting after a count, offline-first: does the app send the quantity counted, or the difference to apply? → A: The app sends the counted quantity and the stock it showed when the merchant counted; the server records counted minus shown, so movements that arrive in between still count.
- Q: Low-stock warning: a level per product, a default for the whole shop, or both? → A: A level per product, set by the merchant. A product with no level is never listed as low. The warning is a list the app reads; push notifications are left to a later spec.
- Q: Loss reasons: a fixed list or free text? → A: A fixed list (broken, expired, stolen, own use, other) with an optional note.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Set the stock I already have (Priority: P1)

A merchant starts using the app with goods already on the shelves. For each product they enter how many units they have. The product's stock grows by that number, and the history shows it as opening stock.

**Why this priority**: Without it every product starts at 0 and every figure that follows is wrong.

**Independent Test**: Create two products, set an opening stock of 30 and 12, read the products back (30 and 12), then try to set the first one's opening stock again and see it refused.

**Acceptance Scenarios**:

1. **Given** products of the shop, **When** the merchant sets an opening stock for one or several of them (up to 200 at once), each with a whole quantity, **Then** one opening-stock movement is recorded per product and each product's stock grows by its quantity.
2. **Given** a product that already has purchases, **When** the merchant sets its opening stock, **Then** it is accepted and adds to what the purchases brought.
3. **Given** a product that already has an opening stock, **When** the merchant sets it again with a new id, **Then** nothing is recorded and the answer names the product with a stable code; the merchant corrects the stock with a count instead (User Story 2).
4. **Given** a quantity of 0 or below or not a whole number, the same product twice, or a product the shop does not have, **When** opening stock is set, **Then** nothing is recorded and the answer names the entry and the field with a stable code.
5. **Given** opening stock sent twice with the same ids, **When** the second request arrives, **Then** it exists once and stock changed once.

---

### User Story 2 - Correct the stock after counting the shelves (Priority: P1)

A merchant counts what is really on a shelf and enters the counted number for each product. The app sends the counted number with the stock it showed at that moment, and the system records the difference between the two as a correction. The count may be made offline, while another device keeps recording: what that device recorded still counts.

**Why this priority**: Counting is the only way a merchant can trust the figure; every other report depends on it.

**Independent Test**: Give a product a stock of 20, count 17, read the product (17) and its history (a correction of −3); count 17 again and see a correction of 0 that leaves 17.

**Acceptance Scenarios**:

1. **Given** a product whose stock is 20, **When** the merchant records a count of 17 with a shown stock of 20, **Then** a correction movement of −3 is recorded, keeping 17 counted and 20 shown, and the product's stock is 17.
2. **Given** a count equal to the shown stock, **When** it is recorded, **Then** a correction of 0 is recorded, so the history shows the product was counted, and the stock does not change.
3. **Given** a count of 17 made offline while the app showed 20, and a purchase of 5 from another device recorded on the server before the count arrives, **When** the count arrives, **Then** a correction of −3 (counted minus shown) is recorded and the product's stock is 22: the purchase still counts.
4. **Given** several products counted at once (up to 200), **When** the count is recorded, **Then** one correction is recorded per product, all together or none.
5. **Given** a counted quantity below 0 or not a whole number, a missing shown stock or one that is not a whole number, the same product twice, or a product the shop does not have, **When** the count is recorded, **Then** nothing is recorded and the answer names the entry and the field with a stable code. A count of 0 is allowed (the shelf is empty).
6. **Given** a count sent twice with the same ids, **When** the second request arrives, **Then** it exists once and stock changed once.

---

### User Story 3 - Record a loss (Priority: P1)

Some units leave the shop without being sold: broken, expired, stolen, or used by the shop itself. The merchant records how many, picks the reason from a short list, and may add a note. The stock goes down.

**Why this priority**: Losses are frequent in a small shop and, unrecorded, they make every count look like a mistake.

**Independent Test**: Give a product a stock of 10, record a loss of 2 as broken, read the product (8) and its history (a loss of −2 with its reason).

**Acceptance Scenarios**:

1. **Given** a product of the shop, **When** the merchant records a loss with a whole quantity, a reason (broken, expired, stolen, own use or other) and optionally a note, **Then** a loss movement taking away that quantity is recorded with its reason and note, and the product's stock goes down.
2. **Given** a loss larger than the product's stock, **When** it is recorded, **Then** it is accepted and the stock goes below zero.
3. **Given** several products lost at once (up to 200), **When** the loss is recorded, **Then** one loss movement is recorded per product, all together or none.
4. **Given** a reason that is missing or not in the list, a note longer than 255 characters, a quantity of 0 or below or not a whole number, the same product twice, or a product the shop does not have, **When** the loss is recorded, **Then** nothing is recorded and the answer names the entry and the field with a stable code.
5. **Given** a loss sent twice with the same ids, **When** the second request arrives, **Then** it exists once and stock changed once.

---

### User Story 4 - See a product's stock history (Priority: P2)

A merchant opens a product and sees every change to its stock, newest first: what kind of change, how many units, the stock right after it, when, and by which account. A purchase or purchase cancellation points to its purchase.

**Why this priority**: It explains a surprising figure and settles a disagreement after a count, but recording comes first.

**Independent Test**: Set an opening stock, record a purchase, a loss and a count for one product; list its history and check each entry's kind, quantity and resulting stock.

**Acceptance Scenarios**:

1. **Given** a product with movements, **When** the merchant lists its history, **Then** they get its movements in pages, newest first, each with its kind, its signed quantity, the product's stock right after it, its date, the account that recorded it, and, depending on the kind, its reason, its counted and shown quantities, or the purchase it comes from.
2. **Given** the history of a product, **When** the stock after its newest movement is read, **Then** it equals the product's current stock.
3. **Given** a product with no movement, **When** its history is listed, **Then** the page is empty.

---

### User Story 5 - Know which products are running low (Priority: P2)

The merchant sets, product by product, the level at which it counts as low. They ask for the products that are at or under their level, to know what to buy.

**Why this priority**: It turns the stock figures into a shopping list, but the figures must be right first.

**Independent Test**: Give a product a threshold of 5 and a stock of 6, record a loss of 1, list low products and see it; record a purchase of 10 and see it leave the list.

**Acceptance Scenarios**:

1. **Given** a product, **When** the merchant sets, changes or removes its low-stock level, **Then** it is saved on that product only; the level is not a stock movement and may be changed at any time. There is no default level for the whole shop.
2. **Given** products with a level, **When** the merchant lists low products, **Then** they get, in pages, the products whose stock is at or under their level, lowest stock first, each with its stock and its level.
3. **Given** a product with no level, **When** low products are listed, **Then** it is not in the list.
4. **Given** a level below 0 or not a whole number, **When** it is set, **Then** it is refused with a stable code.

---

### User Story 6 - Only my shop's stock (Priority: P1)

No merchant can see or change another shop's products, movements, history or levels.

**Why this priority**: Isolation between shops is a core promise of the product (constitution IV).

**Independent Test**: Two shops each set an opening stock and record a loss; each merchant is refused the other's product history and cannot record a count, a loss or an opening stock on the other's product.

**Acceptance Scenarios**:

1. **Given** two merchants, **When** the first reads the history of a product of the second's shop, or sets its level, **Then** it is refused with the same stable code as for a product that does not exist.
2. **Given** an opening stock, count or loss naming another shop's product, **When** it is recorded, **Then** nothing is recorded and the answer is the same as for an unknown product.
3. **Given** an id already used by another shop's record, **When** the merchant creates a record with it, **Then** it is refused with a stable code that reveals nothing about the other record.
4. **Given** a merchant with no shop, **When** they use any of these endpoints, **Then** it is refused with a stable code.

---

### User Story 7 - Try it with standard tools (Priority: P3)

A developer or the owner runs the updated API collection after the 006, 007 and 009 journeys: set opening stock, record a loss, record a count, read a product's history, set a level and list low products.

**Why this priority**: There is no screen until spec 013, so the collection is how the feature is checked.

**Independent Test**: Import the collection and run it in order against a local backend without editing a request.

**Acceptance Scenarios**:

1. **Given** the collection and a local backend, **When** the requests run in order, **Then** each succeeds and values carry over by themselves.
2. **Given** a change to an endpoint, **When** it is merged, **Then** the collection and the contract file change with it.

### Edge Cases

- **Stock below zero**: never refused. A loss or a correction may take it below zero; sales on another device may already have.
- **Count on a product whose stock is below zero**: allowed; the correction brings it back to the shelf.
- **Count with no difference**: recorded as a correction of 0, so the history shows the count.
- **Opening stock after purchases**: allowed once; it adds to the purchases.
- **Opening stock set twice**: refused; a count fixes a wrong opening stock.
- **Wrong correction or loss**: never cancelled or changed; the merchant records a new count or loss.
- **Purchase cancelled after a count**: its cancellation movement still takes back its units; the next count corrects the shelf if needed.
- **Level of 0**: the product is low only when its stock is 0 or below.
- **Very large request**: up to 200 products at once; larger is refused and the merchant splits it.
- **Offline record sent before its product reached the server**: refused as unknown, nothing recorded; ordering offline operations is spec 013's job.
- **Two identical creations at the same moment**: exactly one record, and stock changes once.
- **Long history**: always paged; the stock after each movement stays right on every page.

## Requirements *(mandatory)*

### Functional Requirements

**Opening stock**

- **FR-001**: A merchant MUST be able to set the opening stock of 1 to 200 products of their shop in one request, each with a whole quantity from 1 to 999 999. Each product's entry MUST be recorded as an opening-stock movement adding that quantity.
- **FR-002**: A product MUST have at most one opening-stock movement. A second one with a new id MUST be refused with a stable code naming the product. Opening stock MAY be set after the product's purchases.

**Counts**

- **FR-003**: A merchant MUST be able to record a count of 1 to 200 products of their shop in one request, each with a whole counted quantity from 0 to 999 999 and the whole stock the app showed when the merchant counted (which may be below zero). Each product's entry MUST be recorded as a correction movement that keeps the counted and shown quantities.
- **FR-004**: The correction's quantity MUST be computed by the system as counted minus shown, never sent by the app. Movements recorded between the count and its arrival MUST still count in the stock. A count with no difference MUST still be recorded, with a quantity of 0.

**Losses**

- **FR-005**: A merchant MUST be able to record a loss of 1 to 200 products of their shop in one request, each with a whole quantity from 1 to 999 999, a reason from a fixed list (broken, expired, stolen, own use, other) and an optional note of at most 255 characters. Each product's entry MUST be recorded as a loss movement taking away that quantity, with its reason and note. The reason is sent as a stable code; the app translates it.

**Common to opening stock, counts and losses**

- **FR-006**: A request MUST be recorded all together or not at all; a wrong entry MUST stop the whole request, and the answer MUST name the entry and the field. A product MUST appear at most once per request.
- **FR-007**: Every opening stock, count and loss MUST have a date that defaults to today in the shop's time zone, MAY be in the past and MUST NOT be after today.
- **FR-008**: A product's stock MUST stay the sum of all its movements. A movement MUST NOT be refused because it takes the stock below zero.
- **FR-009**: The purchase and purchase-cancellation movements of 009 MUST keep their meaning and behaviour.

**History**

- **FR-010**: A merchant MUST be able to list a product's stock movements in pages, newest first in the order the server recorded them, each with its kind, signed quantity, the product's stock right after it, its date, the account that recorded it, and its details by kind: the reason and note of a loss, the counted and shown quantities of a correction, the purchase of a purchase or purchase cancellation.
- **FR-011**: The stock right after a product's newest movement MUST equal the product's current stock.

**Low stock**

- **FR-012**: A merchant MUST be able to set, change and remove a product's own low-stock level, as a whole number from 0 to 999 999. There is no shop-wide default level. Changing a level MUST NOT record a stock movement.
- **FR-013**: A merchant MUST be able to list, in pages, the products of their shop whose stock is at or under their level, lowest stock first, each with its stock and level. Products with no level MUST NOT appear.

**Corrections, accounts and isolation**

- **FR-014**: Stock movements MUST never be changed or deleted, and there MUST be no way to cancel a correction or a loss. A mistake MUST be fixed by a new movement.
- **FR-015**: Every movement this feature records MUST keep the account that recorded it.
- **FR-016**: Every opening-stock, count and loss entry MUST carry an id made by the app. Sending the same creation twice MUST create it once and answer the record as it exists. An id used by another shop's record MUST be refused with a stable code that reveals nothing.
- **FR-017**: Two identical creations at the same moment MUST result in exactly one record, and stock MUST change once.
- **FR-018**: Every request MUST require a signed-in merchant who owns a shop. Another shop's products, movements and levels MUST be answered as not existing and MUST never be usable.

**API conventions** (as in 006 to 009)

- **FR-019**: Every endpoint MUST sit under the versioned API path, accept and return JSON only, and use the common error shape with stable codes and no user-facing text.
- **FR-020**: Every endpoint MUST have automated tests for success, validation errors, unauthenticated access, a merchant with no shop and another shop's data. Stock sums, the opening-stock limit, count corrections with movements arriving in between, the stock after each history entry, the low-stock list and repeated requests MUST have their own tests.
- **FR-021**: The feature MUST update the importable API collection and the contract file with every endpoint.

### Key Entities

- **Stock movement** (from 009, extended): one change to a product's stock, with a kind, a signed whole quantity, a date, the account that recorded it, and details by kind. New kinds: opening stock, count correction and loss. Never changed or deleted.
- **Opening stock**: a movement adding the units a product had when the merchant started; at most one per product.
- **Count correction**: a movement of counted minus shown; keeps the counted quantity and the stock the app showed at the count; its quantity may be negative, zero or positive.
- **Loss**: a movement taking away units that left without a sale, with a reason from a fixed list and an optional note.
- **Low-stock level**: a product's own stock level at or under which it is listed as low. A setting, not a record of stock; it may change or be removed.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A merchant sets the opening stock of up to 200 products in one request.
- **SC-002**: In tests, after any series of opening stocks, purchases, cancellations, counts and losses, 100% of products show a stock equal to the sum of their movements.
- **SC-003**: In tests, right after a count, 100% of counted products show their stock before the count plus counted minus shown, including when other movements arrived between the count and its recording.
- **SC-004**: In tests, for 100% of products, every history entry's stock after it equals the sum of the movements up to and including it.
- **SC-005**: In tests, the low-stock list holds exactly the products at or under their level, in 100% of cases.
- **SC-006**: In tests, sending any creation twice leaves exactly one record and changes stock once, in 100% of cases.
- **SC-007**: In tests, 100% of attempts to read, use or reuse the id of another shop's record are refused and reveal nothing.
- **SC-008**: A person who has never seen the project runs the collection's stock journey in under 10 minutes without editing a request.

## Assumptions

- **Opening stock** is a movement kind of its own, recorded at most once per product, at any time, including after purchases. A later change goes through a count. A product with no opening stock simply has none.
- **Stock below zero** is allowed and never refused, for losses, corrections and, later, sales.
- **History** lists a product's movements newest first in the order the server recorded them, with kind, quantity, the stock right after it, date and account. The stock after each movement follows that order, not the movements' dates. Purchase movements of 009 show the account that recorded their purchase.
- **No cancellation** of a correction or a loss: a new count or loss fixes it. Opening stock cannot be cancelled either; a count fixes it.
- **Low-stock warning** is a list the app reads, built from each product's own level. Push notifications and any message sent to the merchant are left to a later spec.
- **Quantities** are whole units until roadmap step 1.9. Levels are whole units too.
- **Sales** (spec 011) are out of scope: how a sale's stock exit appears in a product's history is decided there.
- **Returns to suppliers**, transfers between shops and stock value (quantity × price) are out of scope.
- **Roles of accounts**: the shop owner is the only one; employees come with spec 014. The account that records each movement is still kept now, for that spec.
- **Backend only**: no screen until spec 013.
