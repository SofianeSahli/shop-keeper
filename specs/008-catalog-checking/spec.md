# Feature Specification: Catalog Checking

**Feature Branch**: `008-catalog-checking`

**Created**: 2026-11-13

**Status**: Draft

**Input**: User description: "Second part of roadmap step 1.3, after 007. Check the catalog products waiting to be checked: the platform operator works through a review queue of pending products (decision of the owner, 2026-11-13), gives each its key and its names in French, Arabic and English, merges duplicates, and adds brands and formats; bulk import of catalog products. Backend only."

## Context

Spec 007 let shops add products by barcode. A barcode the catalog did not know became a catalog product **waiting to be checked**: usable at once by every shop that scans it, but with no name shown to other shops, because the name a merchant typed may be wrong or offensive. Until now the only way to check such a product was the operator's catalog file.

The owner decided on 2026-11-13 that the platform operator checks pending products through a **review queue**. No product becomes checked by agreement between shops or from an outside source in this feature.

Checking a product gives it a stable translation key and its names in French, Arabic and English, so every shop that scans it from then on sees a proper name in its language. The queue also shows the operator what shops called the product, so the work is mostly confirming and translating.

The same feature adds what the constitution expects of a shared catalog product beyond its name: a **brand** and a **format** (for example 500 g, 1 L, pack of 6), and the means to keep the catalog clean: merging two catalog products that turn out to be the same, and importing many products at once.

This feature ships no screen. The operator uses it with an API testing tool; an operator app may come later as its own project.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See what is waiting to be checked (Priority: P1)

The operator opens the review queue and sees the products waiting to be checked, the most used first: each with its barcode, the name and language the first shop typed, the names every linked shop gave its own product, how many shops use it, and when it was first added.

**Why this priority**: Without the queue the operator cannot find the products to check, and the shops keep seeing unnamed products.

**Independent Test**: Have three shops add the same unknown barcode with different names and one shop add another; open the queue and see the first barcode on top with 3 shops and the three names.

**Acceptance Scenarios**:

1. **Given** pending products, **When** the operator lists the queue, **Then** they get them in pages, ordered by the number of shops using them (most first), then by the date they were first added (oldest first).
2. **Given** a pending product, **When** it appears in the queue, **Then** it shows its barcode, the name and language the first shop typed, the number of shops linked to it, and the names those shops gave their own products, each once.
3. **Given** a checked product, **When** the operator lists the queue, **Then** it is not in the queue.
4. **Given** a merchant account, **When** it asks for the queue or any operator action, **Then** it is refused with a stable code.

---

### User Story 2 - Check a pending product (Priority: P1)

The operator picks a pending product and gives it a translation key, its names in French, Arabic and English and, if they want, other spellings, a brand and a format. The product becomes checked: every shop that scans its barcode from then on sees its name in their language.

**Why this priority**: This is the purpose of the feature: turning shop-typed barcodes into a shared, named catalog.

**Independent Test**: Check a pending product from the queue; look its barcode up from a shop that never had it and see the three names; check that the shops already linked keep their own product names.

**Acceptance Scenarios**:

1. **Given** a pending product, **When** the operator checks it with a valid key and three names, **Then** it becomes checked, keeps its id and barcode, and its names are returned to every shop that looks it up.
2. **Given** shops already linked to the pending product, **When** it is checked, **Then** they stay linked and their own product names and prices do not change.
3. **Given** a key not of the form `products.` followed by lowercase letters, digits and underscores, a key already used by another catalog product, or a missing name, **When** the operator checks a product, **Then** it is refused with a stable code and nothing changes.
4. **Given** a checked product, **When** the operator corrects its names, spellings, brand or format, **Then** the change is saved and shops' own names stay as they were.
5. **Given** a check or a correction, **When** it is saved, **Then** the system records which operator did it and when.
6. **Given** two operators checking the same pending product at the same moment, **When** both send their check, **Then** exactly one check applies and the other is told the product is no longer pending.

---

### User Story 3 - Merge two catalog products that are the same (Priority: P1)

The operator finds two catalog products that are the same product, for example two barcodes of one biscuit (a manufacturer's new code), or a pending barcode that is a product already in the catalog. They merge one into the other: the barcodes and the shops' links move to the product that stays, and the other one stops being offered.

**Why this priority**: Duplicates split shops' products across two catalog entries and make every later report on the catalog wrong.

**Independent Test**: Have shop A link to barcode X (pending) and shop B to barcode Y (checked) of the same product; merge X into Y; look up X and get Y with its names; see shop A now linked to Y.

**Acceptance Scenarios**:

1. **Given** two catalog products, **When** the operator merges the first into the second, **Then** the barcodes of the first now find the second, and every shop linked to the first is linked to the second.
2. **Given** a shop linked to both products, **When** they are merged, **Then** the shop's product linked to the second keeps its link, and its product linked to the first loses its catalog link and becomes a product of its own, with its name, prices and history unchanged.
3. **Given** a merged product, **When** an app asks for it by its id, **Then** the answer says it was merged and gives the product it was merged into.
4. **Given** a product merged into itself, into a product already merged, or a checked product merged into a pending one, **When** the operator asks for it, **Then** it is refused with a stable code.
5. **Given** a merge, **When** it is saved, **Then** the system records which operator did it and when, and the merge cannot be undone in this feature.

---

### User Story 4 - Brands and formats (Priority: P2)

A catalog product can have a brand and a format. Brands are a shared list the operator keeps; a format is a quantity and a unit (grams, kilograms, millilitres, litres, pieces). Shops see the brand and the format with the catalog product, and suggestions find a product by its brand.

**Why this priority**: Brand and format tell apart products that share a name (Délice milk 1 L and ½ L), but shops can work without them.

**Independent Test**: Create the brand "Délice", give two milk products the brand and the formats 1 L and 500 ml; search "délice" from a shop and see both, each with its format.

**Acceptance Scenarios**:

1. **Given** the operator, **When** they create a brand with a name and optional other spellings, **Then** it can be given to catalog products; a brand name already used, once compared as in spec 007, is refused.
2. **Given** a catalog product, **When** the operator sets its format, **Then** it holds a positive quantity and one of the units grams, kilograms, millilitres, litres or pieces; anything else is refused.
3. **Given** a catalog product with a brand and a format, **When** a shop reads it, looks it up or gets it as a suggestion, **Then** the brand and the format come with it.
4. **Given** a search with a brand's name or spelling, **When** a shop asks for suggestions, **Then** checked products of that brand are among them.
5. **Given** a brand, **When** the operator renames it, **Then** every product of that brand shows the new name.

---

### User Story 5 - Import many catalog products at once (Priority: P3)

The operator imports a list of products with their barcodes, names, brands and formats, for example a supplier's or a distributor's product list, as a spreadsheet file. New barcodes become checked products, barcodes waiting to be checked become checked, and the whole import is refused if one line is wrong.

**Why this priority**: It fills the catalog with packaged products faster than checking them one by one, but the queue alone already makes the catalog grow.

**Independent Test**: Import a file of 20 lines, 2 of them barcodes already pending; see 18 new checked products, the 2 pending ones checked in place, and an identical second import change nothing.

**Acceptance Scenarios**:

1. **Given** a file with one product per line (barcode, key, French, Arabic and English names, and optional brand, format and other spellings), **When** the operator imports it, **Then** new products are added as checked, existing keys are updated in place, and pending barcodes become checked in place, as with the catalog file of spec 007.
2. **Given** a line with a wrong key, barcode, name, brand or format, **When** the file is imported, **Then** the import stops, says which line and why, and changes nothing.
3. **Given** the same file imported twice, **When** the second import ends, **Then** the catalog is identical to after the first.
4. **Given** a brand named in the file that does not exist, **When** the file is imported, **Then** the brand is created.

---

### User Story 6 - Operator accounts (Priority: P2)

The owner makes an existing account an operator, or stops it being one, with a server command. Operators reach the operator actions; merchants never do.

**Why this priority**: Every operator action needs it, but it is a small piece.

**Independent Test**: Make an account an operator; call the queue with it and with a merchant account; revoke it and call again.

**Acceptance Scenarios**:

1. **Given** an existing account, **When** the owner runs the grant command with its email, **Then** the account can use every operator action from its next request.
2. **Given** an operator, **When** the owner revokes it, **Then** its next operator request is refused with a stable code.
3. **Given** an unknown email, **When** the grant command runs, **Then** it fails and changes nothing.
4. **Given** an operator who also owns a shop, **When** they use the merchant actions, **Then** those work exactly as for any merchant.

---

### User Story 7 - Try it with standard tools (Priority: P3)

A developer or the owner imports the updated API collection and runs the operator journey after the merchant journeys: list the queue, check a product, create a brand, merge two products.

**Why this priority**: There is no operator screen, so the collection is how the feature is checked.

**Independent Test**: Import the collection, run it against a local backend with an operator account, without editing any request.

**Acceptance Scenarios**:

1. **Given** the collection and a local backend, **When** the requests are run in order, **Then** each succeeds and values carry over between requests by themselves.
2. **Given** a change to an endpoint, **When** it is merged, **Then** the collection and the contract file change with it.

### Edge Cases

- **Pending product that nobody uses any more**: cannot happen in this feature, since shop products are never deleted; a pending product always has at least the shop that created it.
- **Offensive or meaningless typed name**: the operator sees it in the queue; it is never shown to shops, before or after the check, and the check replaces it with proper names.
- **Barcode that is not a real product** (a test code, a shop's own label code that passed the check digit): the operator can check it with a plain name such as "Unknown product"; removing or hiding catalog products is out of scope.
- **Key chosen by the operator is the generated key of a pending product** (`products.barcode_…`): refused, so generated keys stay reserved for pending products.
- **Catalog file of spec 007 loaded after a check**: same rules as 007; the file can update a product checked through the queue, and a file entry for a merged product's barcode updates the product it was merged into.
- **Merge of a product that has a barcode into one that has another**: the product that stays now has both barcodes; looking up either finds it.
- **Brand renamed to the name of another brand**: refused; merging brands is out of scope.
- **A shop scans a barcode while it is being checked or merged**: the shop gets either the state before or the state after, never a mix.

## Requirements *(mandatory)*

### Functional Requirements

**Operators**

- **FR-001**: The system MUST let the owner grant or revoke the operator role on an existing account with a server command, and MUST refuse every operator action to accounts without it with a stable code.
- **FR-002**: An account MAY be both an operator and a shop owner; its merchant actions MUST behave as for any merchant.
- **FR-003**: Every check, correction, merge, brand change and import MUST record the operator who made it and when.

**Review queue**

- **FR-004**: The system MUST list pending catalog products in pages, ordered by the number of shops linked to them, most first, then by the date they were first added, oldest first.
- **FR-005**: Each queue entry MUST show the barcode, the name and language typed by the first shop, the number of linked shops, the distinct names those shops gave their own products, and the date it was first added.
- **FR-006**: Names typed by shops MUST be shown only to operators, never to other shops (as in spec 007).

**Checking and correcting**

- **FR-007**: The operator MUST be able to check a pending product by giving it a key of the form `products.<lowercase letters, digits, underscores>` not used by another catalog product and not starting with the reserved pending prefix, names in French, Arabic and English (each at most 120 characters), and optionally other spellings, a brand and a format.
- **FR-008**: Checking MUST keep the product's id, barcodes and shop links, and MUST NOT change any shop's own product name or prices.
- **FR-009**: The operator MUST be able to correct the key, names, spellings, brand and format of a checked product under the same rules.
- **FR-010**: Two checks of the same pending product at the same moment MUST result in exactly one check; the other MUST be refused with a stable code.

**Merging**

- **FR-011**: The operator MUST be able to merge one catalog product into another. Afterwards the barcodes of the first MUST find the second, every shop linked to the first MUST be linked to the second, and the first MUST no longer be suggested, looked up or offered for adding.
- **FR-012**: When a shop is linked to both products, its product linked to the second MUST keep the link, and its product linked to the first MUST lose its catalog link and keep its name, prices and id.
- **FR-013**: Reading a merged product by its id MUST say it was merged and give the id of the product it was merged into.
- **FR-014**: The system MUST refuse merging a product into itself, into or from a product already merged, and a checked product into a pending one, with stable codes.
- **FR-015**: A catalog product MUST be able to hold several barcodes, each unique across the catalog; adding a product by any of them MUST link to that product.

**Brands and formats**

- **FR-016**: The operator MUST be able to create and rename brands. A brand has a name (at most 80 characters) and optional other spellings; two brands MUST NOT have the same name once compared as in spec 007 FR-015.
- **FR-017**: A catalog product MAY have one brand and one format. A format is a positive whole quantity with a unit among grams, kilograms, millilitres, litres and pieces.
- **FR-018**: Catalog products returned to shops (lookups, suggestions, shop products, close matches) MUST carry their brand name and format.
- **FR-019**: Suggestions (spec 007 FR-014) MUST also match brand names and their other spellings, with the same comparison rules.

**Import**

- **FR-020**: The operator MUST be able to import a spreadsheet file of catalog products, one per line, with barcode, key, three names, and optional brand, format and other spellings. The import MUST follow the rules of spec 007's catalog file (FR-003, FR-003a): all or nothing, repeatable without duplicates, pending barcodes checked in place.
- **FR-021**: A wrong line MUST stop the import with its line number and a reason code, and change nothing.
- **FR-022**: The spec 007 catalog file MUST accept brand and format too.

**API conventions** (as in 006 and 007)

- **FR-023**: Every endpoint MUST sit under the versioned API path, use JSON and the common error shape with stable codes, and be covered by automated tests for success, validation errors, unauthenticated access and access by a non-operator.
- **FR-024**: The feature MUST update the importable API collection and the contract file with every endpoint.

### Key Entities

- **Operator**: an account allowed to check and maintain the shared catalog. Not tied to a shop.
- **Catalog product** (from 007): gains several barcodes, an optional brand and format, a merged state with the product it was merged into, and who checked it and when.
- **Catalog barcode**: one barcode of a catalog product, unique across the catalog.
- **Brand**: a shared name with other spellings for search, given to catalog products.
- **Format**: a quantity and a unit of a catalog product.
- **Catalog change record**: who changed what in the catalog (check, correction, merge, brand change, import) and when.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The operator checks a pending product from the queue in 2 requests (list, check).
- **SC-002**: After a check, 100% of lookups of that barcode by any shop return the three names, in tests.
- **SC-003**: After a check, a correction or a merge, 100% of shops' own product names and prices are unchanged, in tests.
- **SC-004**: After merging A into B, 100% of lookups of A's barcodes return B, and no shop is linked to A, in tests.
- **SC-005**: 100% of operator actions are refused to merchant accounts and to signed-out requests, in tests.
- **SC-006**: Importing the same file twice leaves the catalog identical to importing it once.
- **SC-007**: A person who has never seen the project runs the operator journey of the collection in under 10 minutes without editing a request.

## Assumptions

- **Who checks**: the platform operator, through the review queue (owner decision, 2026-11-13). Agreement between shops and outside product databases are out of scope; the queue's order by number of shops is the only use of shop agreement.
- **Backend only**: no operator screen; an operator app, if any, is a separate project later (constitution I).
- **Operator role**: granted and revoked by a server command run by the owner; no sign-up for operators and no operator management endpoint.
- **Removing or hiding catalog products**, undoing a merge, merging brands and categories are out of scope.
- **Units of sale** (selling by weight) stay with roadmap step 1.9; a format describes the packaged product, not how a shop sells it.
- **Spreadsheet format**: a plain comma-separated text file in UTF-8 with a header line; the exact columns are fixed in the plan.
- **The catalog download to phones** still comes with the connected-app spec 013; this feature only changes what the API returns.
- **Shop-side changes** are limited to returning brand, format and merge information, and finding products by brand; adding products works as in 007.
