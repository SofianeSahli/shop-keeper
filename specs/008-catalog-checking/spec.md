# Feature Specification: Catalog Checking

**Feature Branch**: `008-catalog-checking`

**Created**: 2026-11-13

**Status**: Draft

**Input**: User description: "Second part of roadmap step 1.3, after 007. Check the catalog products waiting to be checked: the platform operator works through a review queue of pending products (decision of the owner, 2026-11-13), gives each its key and its names in French, Arabic and English, merges duplicates, and adds brands and formats; bulk import of catalog products. Backend only." Added on 2026-11-16 by the owner: "a merchant can voluntarily choose not to sell a specific product at any time, and it needs to be removed from their shop products." And: "a merchant cannot rename a product that has a barcode. A product with a barcode will always have its name and the merchant will have to use that name."

## Context

Spec 007 let shops add products by barcode. A barcode the catalog did not know became a catalog product **waiting to be checked**: usable at once by every shop that scans it, but with no name shown to other shops, because the name a merchant typed may be wrong or offensive. Until now the only way to check such a product was the operator's catalog file.

The owner decided on 2026-11-13 that the platform operator checks pending products through a **review queue**. No product becomes checked by agreement between shops or from an outside source in this feature.

Checking a product gives it a stable translation key and its names in French, Arabic and English, so every shop that scans it from then on sees a proper name in its language. The queue also shows the operator what shops called the product, so the work is mostly confirming and translating.

The same feature adds what the constitution expects of a shared catalog product beyond its name: a **brand** and a **format** (for example 500 g or 1 l), and the means to keep the catalog clean: merging two catalog products that turn out to be the same, and importing many products at once.

On the shop side, two rules change. A product with a barcode now **always carries the catalog's name** once the operator has checked it: the merchant can no longer rename it, and corrections made by the operator reach every shop. Products without a barcode keep the name the merchant chose. And a merchant can now **remove a product from sale** when they stop selling it, and put it back later. The product leaves their lists, but it is never deleted, because later sales and stock records will point to it.

This feature ships no screen. The operator uses it with an API testing tool; an operator app may come later as its own project.

## Clarifications

### Session 2026-11-16

- Q: Can one catalog product hold several barcodes, so that scanning any of them finds the same product? → A: No, one at most. A merge of two products that both have a barcode is refused; when only the merged-away product has one, its barcode moves to the product that stays.
- Q: When A is merged into B and a shop has products linked to both, what happens to its product linked to A? → A: It loses its catalog link and stays the shop's own product, with its name, prices and id unchanged (FR-012).
- Q: How does the operator import a product list? → A: Both ways, with one set of rules: a server command for large files, and an operator action through the API that takes the file's content, for operators without server access (FR-020a).
- Q: What may the operator see in the queue about the shops that use a pending product? → A: Each typed name comes with the shop's name and city, so the operator can contact the shop about an odd product; no owner name, email or phone (FR-005, FR-006).
- Q: Which units may a catalog product's format use? → A: Whole quantities in grams, kilograms, millilitres, litres or pieces; 1.5 l is written 1500 ml; packs of several units are out of scope (FR-017).
- Q: Can a merchant stop selling one of their products? → A: Yes, at any time (owner, PR review). The product leaves the shop's list, suggestions and duplicate checks but is never physically deleted, so later sales and stock records keep pointing to it; the merchant can put it back on sale with the same id, name and prices (User Story 8, FR-025 to FR-031).
- Q: When a merchant adds a barcoded product the catalog has already checked, what is its name in the shop? → A: The catalog name in the language the app sends (French, Arabic or English); the merchant cannot send or change a name (owner, PR review; FR-032).
- Q: A barcode the catalog does not know yet (pending): what name does the merchant's product carry? → A: The name the merchant types, which they can rename while the product is pending; once the operator checks it, the shop product takes the catalog name and can no longer be renamed (FR-033).
- Q: When the operator corrects a checked barcoded product's names, or merges a pending barcode into a checked product, do linked shops' names follow? → A: Yes; a shop product with a barcode always shows the current catalog name in its language (FR-034).
- Q: Can a merchant still name a product without a barcode that is linked to the catalog (tomatoes, bread)? → A: Yes; the catalog-name rule applies only to products with a barcode (FR-035).
- Q: When a merge gives a barcode to a loose catalog product, do the names merchants chose for it get replaced by the catalog name? → A: No. Only the shop products that came with the barcode take the catalog name; products merchants named themselves keep their names and stay renamable (FR-034, FR-035).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See what is waiting to be checked (Priority: P1)

The operator opens the review queue and sees the products waiting to be checked, the most used first: each with its barcode, the name and language the first shop typed, the names every linked shop gave its own product with that shop's name and city, how many shops use it, and when it was first added.

**Why this priority**: Without the queue the operator cannot find the products to check, and the shops keep seeing unnamed products.

**Independent Test**: Have three shops add the same unknown barcode with different names and one shop add another; open the queue and see the first barcode on top with 3 shops and the three names.

**Acceptance Scenarios**:

1. **Given** pending products, **When** the operator lists the queue, **Then** they get them in pages, ordered by the number of shops using them (most first), then by the date they were first added (oldest first).
2. **Given** a pending product, **When** it appears in the queue, **Then** it shows its barcode, the name and language the first shop typed, the number of shops linked to it, and for each linked shop the name it gave its own product, with the shop's name and city.
3. **Given** a checked product, **When** the operator lists the queue, **Then** it is not in the queue.
4. **Given** a merchant account, **When** it asks for the queue or any operator action, **Then** it is refused with a stable code.

---

### User Story 2 - Check a pending product (Priority: P1)

The operator picks a pending product and gives it a translation key, its names in French, Arabic and English and, if they want, other spellings, a brand and a format. The product becomes checked: every shop that scans its barcode from then on sees its name in their language.

**Why this priority**: This is the purpose of the feature: turning shop-typed barcodes into a shared, named catalog.

**Independent Test**: Check a pending product from the queue; look its barcode up from a shop that never had it and see the three names; check that the shops already linked keep their prices and now carry the catalog name in their language.

**Acceptance Scenarios**:

1. **Given** a pending product, **When** the operator checks it with a valid key and three names, **Then** it becomes checked, keeps its id and barcode, and its names are returned to every shop that looks it up.
2. **Given** shops already linked to the pending product, **When** it is checked, **Then** they stay linked, their prices do not change, and their products take the catalog name in the language each was added with (User Story 9).
3. **Given** a key not of the form `products.` followed by lowercase letters, digits and underscores, a key already used by another catalog product, or a missing name, **When** the operator checks a product, **Then** it is refused with a stable code and nothing changes.
4. **Given** a checked product, **When** the operator corrects its names, spellings, brand or format, **Then** the change is saved, linked shop products with a barcode show the corrected name, and names merchants chose for products without a barcode stay as they were.
5. **Given** a check or a correction, **When** it is saved, **Then** the system records which operator did it and when.
6. **Given** two operators checking the same pending product at the same moment, **When** both send their check, **Then** exactly one check applies and the other is told the product is no longer pending.

---

### User Story 3 - Merge two catalog products that are the same (Priority: P1)

The operator finds two catalog products that are the same product, for example a pending barcode that is a product already in the catalog without a barcode, or two catalog entries for the same loose product. They merge one into the other: the barcode, if any, and the shops' links move to the product that stays, and the other one stops being offered.

**Why this priority**: Duplicates split shops' products across two catalog entries and make every later report on the catalog wrong.

**Independent Test**: Have shop A link to barcode X (pending) and shop B to checked product Y, which has no barcode; merge X into Y; look up X and get Y with its names; see shop A now linked to Y.

**Acceptance Scenarios**:

1. **Given** two catalog products, at most one of them with a barcode, **When** the operator merges the first into the second, **Then** the barcode of the first, if any, now finds the second, and every shop linked to the first is linked to the second.
2. **Given** a shop linked to both products, **When** they are merged, **Then** the shop's product linked to the second keeps its link, and its product linked to the first loses its catalog link and becomes a product of its own, with its name, prices and history unchanged.
3. **Given** a merged product, **When** an app asks for it by its id, **Then** the answer says it was merged and gives the product it was merged into.
4. **Given** a product merged into itself, into a product already merged, a checked product merged into a pending one, or two products that both have a barcode, **When** the operator asks for it, **Then** it is refused with a stable code and nothing changes.
5. **Given** a merge, **When** it is saved, **Then** the system records which operator did it and when, and the merge cannot be undone in this feature.

---

### User Story 9 - A product with a barcode carries the catalog's name (Priority: P1)

When a merchant adds a product whose barcode the operator has checked, its name in the shop is the catalog name in the merchant's language, and the merchant cannot change it. While a barcode is still waiting to be checked, the merchant names it as before; once the operator checks it, every shop that has it shows the catalog name. When the operator corrects that name, every shop follows. Products without a barcode keep the name the merchant chose.

**Why this priority**: The owner wants every shop to sell a packaged product under one checked name, so the catalog, and later reports across shops, speak about the same product the same way. Checking (User Story 2) is what gives shops those names, so the two go together.

**Independent Test**: Add a checked barcode with `language: ar` and see the Arabic catalog name; try to rename it and be refused; add a pending barcode with a typed name, rename it, have the operator check it, and see the shop product now carries the catalog name; correct the catalog name and see the shop follow.

**Acceptance Scenarios**:

1. **Given** a checked catalog product with a barcode, **When** a merchant adds it with a language, **Then** the shop product's name is the catalog name in that language; a request that also sends a name is refused with a stable code.
2. **Given** a shop product linked to a checked barcoded catalog product, **When** the merchant tries to rename it, **Then** it is refused with a stable code; changing its prices still works.
3. **Given** a pending barcode, **When** a merchant adds it, **Then** they must type a name and a language, as in spec 007, and may rename it while it stays pending.
4. **Given** shop products linked to a pending barcode, **When** the operator checks it, **Then** each takes the catalog name in the language it was added with, and can no longer be renamed.
5. **Given** a checked barcoded catalog product, **When** the operator corrects its names, or a catalog file or import changes them, **Then** every linked shop product shows the new name in its language.
6. **Given** a product without a barcode linked to the catalog (tomatoes), **When** the merchant names or renames it, **Then** it works as in spec 007, and the operator's corrections never change it.
7. **Given** a shop that has its own product named like a catalog product, **When** it adds that barcoded catalog product, **Then** the product is added with the catalog name anyway: the barcode already makes them different products.

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
5. **Given** the same file, **When** the operator imports it once through the server command and once through the API, **Then** both give the same result and the same errors.

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

### User Story 8 - Stop selling a product (Priority: P2)

A merchant stops selling a product and removes it from their shop. It disappears from their product list, from the suggestions and from the duplicate checks. If they sell it again later, they find it among their removed products, or are pointed to it when they scan its barcode or type its name, and put it back on sale with its name and prices.

**Why this priority**: A shop's range changes all the time; a product list full of things it no longer sells slows every sale and every search. The owner asked for it during the 008 review.

**Independent Test**: Remove a product; it is gone from the list and the suggestions; scan its barcode and be told it was removed, with its id; put it back; it is in the list again with the same id, name and prices.

**Acceptance Scenarios**:

1. **Given** a product of the shop, **When** the merchant removes it from sale, **Then** it no longer appears in the shop's product list, in suggestions or in close-name matches, and reading it by its id still works and says when it was removed.
2. **Given** removed products, **When** the merchant lists their removed products, **Then** they get them in pages, and only those.
3. **Given** a removed product, **When** the merchant adds the same barcode, the same catalog product, or a product with a name they chose that is the same once compared as in spec 007, **Then** it is refused with a stable code that points to the removed product, so the app can offer to put it back.
4. **Given** a removed product, **When** the merchant puts it back on sale, **Then** it is in the list and the suggestions again with the same id, name, prices and catalog link.
5. **Given** a removed product, **When** the merchant tries to change its name or prices, **Then** it is refused with a stable code until it is put back.
6. **Given** a product already removed, or one already on sale, **When** the merchant removes it again or puts it back again, **Then** the answer is the product as it is, and nothing changes.
7. **Given** a removed product linked to a pending catalog product, **When** the operator lists the queue, **Then** that shop no longer counts as using it, and counts again once the product is put back.
8. **Given** another shop's product, **When** the merchant removes it or puts it back, **Then** it is refused with the same answer as for a product that does not exist.

---

### User Story 7 - Try it with standard tools (Priority: P3)

A developer or the owner imports the updated API collection and runs the operator journey after the merchant journeys: list the queue, check a product, create a brand, merge two products.

**Why this priority**: There is no operator screen, so the collection is how the feature is checked.

**Independent Test**: Import the collection, run it against a local backend with an operator account, without editing any request.

**Acceptance Scenarios**:

1. **Given** the collection and a local backend, **When** the requests are run in order, including removing a product from sale and putting it back, **Then** each succeeds and values carry over between requests by themselves.
2. **Given** a change to an endpoint, **When** it is merged, **Then** the collection and the contract file change with it.

### Edge Cases

- **Pending product that nobody uses any more**: cannot happen in this feature, since shop products are never deleted; a pending product always has at least the shop that created it.
- **Offensive or meaningless typed name**: the operator sees it in the queue; it is never shown to shops, before or after the check, and the check replaces it with proper names.
- **Barcode that is not a real product** (a test code, a shop's own label code that passed the check digit): the operator can check it with a plain name such as "Unknown product"; removing or hiding catalog products is out of scope.
- **Key chosen by the operator is the generated key of a pending product** (`products.barcode_…`): refused, so generated keys stay reserved for pending products.
- **Catalog file of spec 007 loaded after a check**: same rules as 007; the file can update a product checked through the queue, and a file entry for a merged product's barcode updates the product it was merged into.
- **Two barcodes for the same product** (for example a manufacturer's new code): they stay two catalog products in this feature; a merge of two barcoded products is refused, since a catalog product holds one barcode at most.
- **Brand renamed to the name of another brand**: refused; merging brands is out of scope.
- **A merchant's own product named exactly like a barcoded catalog product**: both stay; the barcoded one carries the catalog name (FR-037).
- **A product added from a pending barcode in Arabic, checked with an Arabic name**: the shop shows the Arabic catalog name; a shop that added it in French shows the French one.
- **Merge of a catalog product some shops removed from sale**: their removed products are relinked or unlinked like any other; they stay removed. A shop linked to both products whose product on the one that stays is removed keeps that link and that removed state, and its other product loses its link (FR-012).
- **Removing a product that later sales or stock movements point to**: allowed; those records keep pointing to it, since a removed product is never deleted.
- **A shop scans a barcode while it is being checked or merged**: the shop gets either the state before or the state after, never a mix.

## Requirements *(mandatory)*

### Functional Requirements

**Operators**

- **FR-001**: The system MUST let the owner grant or revoke the operator role on an existing account with a server command, and MUST refuse every operator action to accounts without it with a stable code.
- **FR-002**: An account MAY be both an operator and a shop owner; its merchant actions MUST behave as for any merchant.
- **FR-003**: Every check, correction, merge, brand change and import MUST record the operator who made it and when.

**Review queue**

- **FR-004**: The system MUST list pending catalog products in pages, ordered by the number of shops linked to them, most first, then by the date they were first added, oldest first.
- **FR-005**: Each queue entry MUST show the barcode, the name and language typed by the first shop, the number of linked shops, for each linked shop (at most 20, oldest link first) the name it gave its own product with the shop's name and city, and the date it was first added. It MUST NOT show the shop owner's name, email or phone.
- **FR-006**: Names typed by shops, and which shop uses which pending product, MUST be shown only to operators, never to other shops (as in spec 007).

**Checking and correcting**

- **FR-007**: The operator MUST be able to check a pending product by giving it a key of the form `products.<lowercase letters, digits, underscores>` not used by another catalog product and not starting with the reserved pending prefix, names in French, Arabic and English (each at most 120 characters), and optionally other spellings, a brand and a format.
- **FR-008**: Checking MUST keep the product's id, barcode and shop links, and MUST NOT change any shop's prices or any name a merchant chose; linked shop products with a barcode take the catalog name (FR-034).
- **FR-009**: The operator MUST be able to correct the key, names, spellings, brand and format of a checked product under the same rules.
- **FR-010**: Two checks of the same pending product at the same moment MUST result in exactly one check; the other MUST be refused with a stable code.

**Merging**

- **FR-011**: The operator MUST be able to merge one catalog product into another. Afterwards the barcode of the first, if any, MUST find the second, every shop linked to the first MUST be linked to the second, and the first MUST no longer be suggested, looked up or offered for adding.
- **FR-012**: When a shop is linked to both products, its product linked to the second MUST keep the link, and its product linked to the first MUST lose its catalog link and keep its name, prices and id.
- **FR-013**: Reading a merged product by its id MUST say it was merged and give the id of the product it was merged into.
- **FR-014**: The system MUST refuse merging a product into itself, into or from a product already merged, a checked product into a pending one, and two products that both have a barcode, with stable codes.
- **FR-015**: A catalog product MUST hold one barcode at most, unique across the catalog, as in spec 007.

**Brands and formats**

- **FR-016**: The operator MUST be able to create and rename brands. A brand has a name (at most 80 characters) and optional other spellings; two brands MUST NOT have the same name once compared as in spec 007 FR-015.
- **FR-017**: A catalog product MAY have one brand and one format. A format is a positive whole quantity with a unit among grams, kilograms, millilitres, litres and pieces; a fraction is written in the smaller unit (1500 ml, not 1.5 l). Pack formats such as 6 x 330 ml are out of scope.
- **FR-018**: Catalog products returned to shops (lookups, suggestions, shop products, close matches) MUST carry their brand name and format.
- **FR-019**: Suggestions (spec 007 FR-014) MUST also match brand names and their other spellings, with the same comparison rules.

**Import**

- **FR-020**: The operator MUST be able to import a spreadsheet file of catalog products, one per line, with barcode, key, three names, and optional brand, format and other spellings. The import MUST follow the rules of spec 007's catalog file (FR-003, FR-003a): all or nothing, repeatable without duplicates, pending barcodes checked in place.
- **FR-020a**: The import MUST be available both as a server command and as an operator action through the API that receives the file's content; both MUST apply exactly the same rules and record the operator. The API action MUST refuse a file above a size limit with a stable code, so large lists go through the command.
- **FR-021**: A wrong line MUST stop the import with its line number and a reason code, and change nothing.
- **FR-022**: The spec 007 catalog file MUST accept brand and format too.

**Names of products with a barcode (shops)**

- **FR-032**: A shop product added from a checked catalog product that has a barcode MUST carry the catalog name in the language it was added with (French, Arabic or English). Adding such a product MUST require that language and MUST refuse a name sent by the merchant, with a stable code. This replaces spec 007 FR-010 for barcoded products.
- **FR-033**: A shop product linked to a pending catalog product MUST carry the name the merchant typed, which the merchant MAY rename while the product is pending. The language it was added with MUST be kept.
- **FR-034**: When a catalog product with a barcode is checked, corrected, merged into, or changed by a catalog file or an import, every linked shop product that carries the catalog name MUST take its current catalog name in its language, in the same change. Shop products carry the catalog name when they were added from a checked barcode (FR-032), when the pending product they were linked to is checked, or when a merge brings them, with their barcode, to a checked product. When a merge gives the barcode to a product that had none, the shop products already linked to that product keep the names their merchants chose.
- **FR-035**: Shop products whose name the merchant chose (no catalog link, a catalog product without a barcode, or a catalog product that gained its barcode through a merge after they were linked) MUST keep that name; spec 007's naming and renaming rules apply to them unchanged.
- **FR-036**: The merchant MUST NOT be able to rename a shop product that carries a catalog name; the refusal MUST use a stable code. Prices stay editable.
- **FR-037**: The "same name twice in one shop" refusal of spec 007 (FR-017) MUST apply only to names a merchant chooses; a product carrying a catalog name MAY share its name with another product of the shop, since its barcode already makes it a different product.

**Removing products from sale (shops)**

- **FR-025**: A merchant MUST be able to remove any of their products from sale at any time, and put it back on sale.
- **FR-026**: A removed product MUST NOT appear in the shop's product list, in suggestions or in close-name matches as a product of the shop; reading it by id and the barcode lookup MUST still return it, marked with the date it was removed. When a suggestion shows a catalog product whose shop product is removed, it MUST carry that removed shop product, so the app offers to put it back rather than to add it.
- **FR-027**: A removed product MUST never be physically deleted: its id, name, prices and catalog link MUST stay, so later records can point to it.
- **FR-028**: Adding a product whose barcode or catalog product is that of a removed product of the same shop, or whose merchant-chosen name is the compared name of a removed product's merchant-chosen name, MUST be refused with a stable code that points to the removed product. Names that come from the catalog are not compared (FR-037).
- **FR-029**: A removed product MUST NOT be changed by the merchant until it is put back on sale; catalog name updates (FR-034) still reach it.
- **FR-030**: Removing or putting back a product already in that state MUST change nothing and answer with the product.
- **FR-031**: A removed product MUST NOT count as a shop using its catalog product (queue order, FR-004); putting it back MUST count it again.

**API conventions** (as in 006 and 007)

- **FR-023**: Every endpoint MUST sit under the versioned API path, use JSON and the common error shape with stable codes, and be covered by automated tests for success, validation errors and unauthenticated access, plus access by a non-operator for operator endpoints and by a merchant with no shop and another shop for shop endpoints.
- **FR-024**: The feature MUST update the importable API collection and the contract file with every endpoint.

### Key Entities

- **Operator**: an account allowed to check and maintain the shared catalog. Not tied to a shop.
- **Catalog product** (from 007): gains an optional brand and format, a merged state with the product it was merged into, and who checked it and when.
- **Brand**: a shared name with other spellings for search, given to catalog products.
- **Format**: a quantity and a unit of a catalog product.
- **Shop product** (from 007): gains the date it was removed from sale, empty while on sale, the language it was added with, and whether its name comes from the catalog (FR-032 to FR-035) or from the merchant.
- **Catalog change record**: who changed what in the catalog (check, correction, merge, brand change, import) and when.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The operator checks a pending product from the queue in 2 requests (list, check).
- **SC-002**: After a check, 100% of lookups of that barcode by any shop return the three names, in tests.
- **SC-003**: After a check, a correction or a merge, 100% of shops' prices and of names merchants chose are unchanged, and 100% of linked shop products that carry the catalog name show the current one in their language, in tests (FR-034).
- **SC-004**: After merging A into B, 100% of lookups of A's barcode return B, and no shop is linked to A, in tests.
- **SC-005**: 100% of operator actions are refused to merchant accounts and to signed-out requests, in tests.
- **SC-006**: Importing the same file twice leaves the catalog identical to importing it once.
- **SC-007**: A person who has never seen the project runs the operator journey of the collection in under 10 minutes without editing a request.
- **SC-008**: In tests, 100% of removed products are absent from the shop's list, suggestions and close-name matches, and 100% of products put back keep their id, name and prices.

## Assumptions

- **Who checks**: the platform operator, through the review queue (owner decision, 2026-11-13). Agreement between shops and outside product databases are out of scope; the queue's order by number of shops is the only use of shop agreement.
- **Backend only**: no operator screen; an operator app, if any, is a separate project later (constitution I).
- **Operator role**: granted and revoked by a server command run by the owner; no sign-up for operators and no operator management endpoint.
- **Removing or hiding catalog products** (the shared catalog), undoing a merge, merging brands and categories are out of scope. Removing a shop's own product from sale is in scope (User Story 8): it hides the product for that shop only and deletes nothing.
- **Units of sale** (selling by weight) stay with roadmap step 1.9; a format describes the packaged product, not how a shop sells it.
- **Spreadsheet format**: a plain comma-separated text file in UTF-8 with a header line; the exact columns are fixed in the plan.
- **The catalog download to phones** still comes with the connected-app spec 013; this feature only changes what the API returns.
- **Changes to spec 007**: products with a checked barcode carry the catalog name and cannot be renamed (User Story 9), where 007 let every shop keep its own name; 007's "same name twice" refusal now applies only to names merchants choose.
- **Shop-side changes** are returning brand, format and merge information, finding products by brand, and removing products from sale and putting them back; adding products works as in 007, except that a removed product is pointed to instead of being created again.
