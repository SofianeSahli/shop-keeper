# Feature Specification: Catalog Checking

**Feature Branch**: `008-catalog-checking`

**Created**: 2026-11-13

**Status**: On hold since 2026-11-19

> **On hold (owner decision, 2026-11-19).** The owner postpones catalog checking. For now, a pending catalog product is checked by adding it, with its barcode, key and French, Arabic and English names, to the catalog file and running `php artisan catalog:load`, which already turns a pending product with that barcode into a verified one (spec 007 FR-003a). An admin interface will be designed later, through the API or inside the backend, never by connecting an app straight to the database (constitution I). The rest of this spec is kept as it was written and is not being implemented.

**Input**: User description: "Second part of roadmap step 1.3, after 007. Check the catalog products waiting to be checked: the platform operator works through a review queue of pending products (decision of the owner, 2026-11-13), gives each its key and its names in French, Arabic and English, merges duplicates, and adds brands and formats; bulk import of catalog products. Backend only." Added on 2026-11-16 by the owner: "a merchant can voluntarily choose not to sell a specific product at any time, and it needs to be removed from their shop products." And: "a merchant cannot rename a product that has a barcode. A product with a barcode will always have its name and the merchant will have to use that name." Revised on 2026-11-19 by the owner: "no operator role and no admin API endpoints. Catalog checking is done with artisan commands run on the server, like the existing catalog:load. Whoever can run commands on the server is trusted; no account flag is needed."

## Context

Spec 007 let shops add products by barcode. A barcode the catalog did not know became a catalog product **waiting to be checked**: usable at once by every shop that scans it, but with no name shown to other shops, because the name a merchant typed may be wrong or offensive. Spec 007's catalog file, loaded with a server command, could already check such a product, but nothing showed which products were waiting.

The owner decided on 2026-11-19 that catalog checking happens **on the server only**, with no account role and no API endpoint. In this spec, **the platform team** means whoever can run commands on the server; it is trusted because it has that access. A server command lists the products waiting to be checked, the most used first, with the names shops gave them. The platform team checks a product by adding its entry to the catalog file (its barcode, a stable translation key and its names in French, Arabic and English) and loading the file, so every check and correction is reviewed in git. A second server command merges two catalog products that turn out to be the same.

Checking a product gives it its key and its three names, so every shop that scans it from then on sees a proper name in its language. The same feature adds what the constitution expects of a shared catalog product beyond its name: a **brand** and a **format** (for example 500 g or 1 l), kept in the catalog files as well.

On the shop side, two rules change. A product with a barcode now **always carries the catalog's name** once it is checked: the merchant can no longer rename it, and corrections in the catalog file reach every shop. Products without a barcode keep the name the merchant chose. And a merchant can now **remove a product from sale** when they stop selling it, and put it back later. The product leaves their lists, but it is never deleted, because later sales and stock records will point to it.

This feature ships no screen.

## Clarifications

### Session 2026-11-16

- Q: Can one catalog product hold several barcodes, so that scanning any of them finds the same product? → A: No, one at most. A merge of two products that both have a barcode is refused; when only the merged-away product has one, its barcode moves to the product that stays.
- Q: When A is merged into B and a shop has products linked to both, what happens to its product linked to A? → A: It loses its catalog link and stays the shop's own product, with its name, prices and id unchanged (FR-014).
- Q: How does the operator import a product list? → A: Both ways, with one set of rules: a server command for large files, and an operator action through the API that takes the file's content, for operators without server access. *Superseded on 2026-11-19: there is no separate import; many products are added through the catalog file.*
- Q: What may the operator see in the queue about the shops that use a pending product? → A: Each typed name comes with the shop's name and city, so the operator can contact the shop about an odd product; no owner name, email or phone. *Superseded on 2026-11-19: the queue shows no shop data at all (FR-003, FR-006).*
- Q: Which units may a catalog product's format use? → A: Whole quantities in grams, kilograms, millilitres, litres or pieces; 1.5 l is written 1500 ml; packs of several units are out of scope (FR-019).
- Q: Can a merchant stop selling one of their products? → A: Yes, at any time (owner, PR review). The product leaves the shop's list, suggestions and duplicate checks but is never physically deleted, so later sales and stock records keep pointing to it; the merchant can put it back on sale with the same id, name and prices (User Story 6, FR-029 to FR-035).
- Q: When a merchant adds a barcoded product the catalog has already checked, what is its name in the shop? → A: The catalog name in the language the app sends (French, Arabic or English); the merchant cannot send or change a name (owner, PR review; FR-023).
- Q: A barcode the catalog does not know yet (pending): what name does the merchant's product carry? → A: The name the merchant types, which they can rename while the product is pending; once it is checked, the shop product takes the catalog name and can no longer be renamed (FR-024).
- Q: When a checked barcoded product's names are corrected, or a pending barcode is merged into a checked product, do linked shops' names follow? → A: Yes; a shop product with a barcode always shows the current catalog name in its language (FR-025).
- Q: Can a merchant still name a product without a barcode that is linked to the catalog (tomatoes, bread)? → A: Yes; the catalog-name rule applies only to products with a barcode (FR-026).
- Q: When a merge gives a barcode to a loose catalog product, do the names merchants chose for it get replaced by the catalog name? → A: No. Only the shop products that came with the barcode take the catalog name; products merchants named themselves keep their names and stay renamable (FR-025, FR-026).

### Session 2026-11-19

- Q: Who checks pending products, and through what? → A: No operator role and no admin API endpoints (owner). Checking is done on the server: the platform team lists the queue and merges products with server commands, and checks and corrects products, brands and formats through the catalog files loaded by `catalog:load`, so every change of names is reviewed in git. Whoever can run commands on the server is trusted; no account flag is needed (FR-001).
- Q: What about platform and shop roles in general? → A: They will be designed before spec 014 (employees); this feature adds no role.
- Q: What may a command print? → A: Nothing personal and no shop data: no merchant name, email or phone, no shop name, city or id. The names shops gave a pending product may be printed, without saying which shop gave them, since confirming those names is most of the checking work (FR-003, FR-006). This supersedes the 2026-11-16 answer on shop names and cities; constitution 3.3.0 removes the operator exception of 3.2.0.
- Q: Is there still a separate import of product lists? → A: No. Many products are added as entries of the catalog file, with the same rules and the same review in git (FR-008, FR-022).
- Q: Who is recorded as having made a change? → A: No account makes changes any more. Each change records the command that made it and when (FR-002); the git history of the catalog files records who changed names.
- Q: How are brands kept? → A: In a brands file next to the catalog file, loaded by the same command; each brand has a stable key, so renaming it is a change of its name in the file (FR-018).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - See what is waiting to be checked (Priority: P1)

The platform team runs a server command and sees the products waiting to be checked, the most used first: each with its id, its barcode, the name and language the first shop typed, the different names shops gave their own product, how many shops use it, and when it was first added. It never shows which shop gave which name.

**Why this priority**: Without the queue the platform team cannot find the products to check, and the shops keep seeing unnamed products.

**Independent Test**: Have three shops add the same unknown barcode with different names and one shop add another; run the command and see the first barcode on top with 3 shops and the three names, and no shop name or city.

**Acceptance Scenarios**:

1. **Given** pending products, **When** the queue command runs, **Then** it prints them in pages, ordered by the number of shops using them (most first), then by the date they were first added (oldest first), and prints how to ask for the next page.
2. **Given** a pending product, **When** it appears in the queue, **Then** it shows its id, its barcode, the name and language the first shop typed, the number of shops using it, the different names the shops gave their own product, and the date it was first added.
3. **Given** a checked or merged product, **When** the queue command runs, **Then** it is not in the queue.
4. **Given** any queue, **When** the command prints it, **Then** the output holds no shop name, city or id and no merchant name, email or phone.
5. **Given** a page size or a page position that is not valid, **When** the command runs, **Then** it stops with a clear reason and prints no queue.

---

### User Story 2 - Check a pending product (Priority: P1)

The platform team adds an entry to the catalog file with the pending product's barcode, a translation key, its names in French, Arabic and English and, if they want, other spellings, a brand and a format, has the change reviewed in git, and loads the file on the server. The product becomes checked: every shop that scans its barcode from then on sees its name in their language.

**Why this priority**: This is the purpose of the feature: turning shop-typed barcodes into a shared, named catalog.

**Independent Test**: Add a catalog file entry for a pending product from the queue and load the file; look the barcode up from a shop that never had it and see the three names; check that the shops already linked keep their prices and now carry the catalog name in their language.

**Acceptance Scenarios**:

1. **Given** a pending product, **When** the catalog file is loaded with an entry for its barcode with a valid key and three names, **Then** it becomes checked, keeps its id and barcode, and its names are returned to every shop that looks it up.
2. **Given** shops already linked to the pending product, **When** it is checked, **Then** they stay linked, their prices do not change, and their products take the catalog name in the language each was added with (User Story 4).
3. **Given** an entry with a key not of the form `products.` followed by lowercase letters, digits and underscores, a key already used by another catalog product, a key starting with the reserved pending prefix, the key of a merged product, a missing name, or an unknown brand, **When** the file is loaded, **Then** the load stops, says which entry and why, and nothing changes.
4. **Given** a checked product, **When** its entry's names, spellings, brand or format change and the file is loaded again, **Then** the change is saved, linked shop products with a barcode show the corrected name, and names merchants chose for products without a barcode stay as they were.
5. **Given** a check or a correction, **When** it is saved, **Then** the system records that the catalog load made it, and when.
6. **Given** a load and a merge, or two loads, touching the same product at the same moment, **When** both run, **Then** each applies completely or is refused and changes nothing, never a mix of both.

---

### User Story 3 - Merge two catalog products that are the same (Priority: P1)

The platform team finds two catalog products that are the same product, for example a pending barcode that is a product already in the catalog without a barcode, or two catalog entries for the same loose product. They run the merge command with both ids: the barcode, if any, and the shops' links move to the product that stays, and the other one stops being offered.

**Why this priority**: Duplicates split shops' products across two catalog entries and make every later report on the catalog wrong.

**Independent Test**: Have shop A link to barcode X (pending) and shop B to checked product Y, which has no barcode; merge X into Y; look up X and get Y with its names; see shop A now linked to Y.

**Acceptance Scenarios**:

1. **Given** two catalog products, at most one of them with a barcode, **When** the platform team merges the first into the second and confirms, **Then** the barcode of the first, if any, now finds the second, and every shop linked to the first is linked to the second.
2. **Given** a shop linked to both products, **When** they are merged, **Then** the shop's product linked to the second keeps its link, and its product linked to the first loses its catalog link and becomes a product of its own, with its name, prices and history unchanged.
3. **Given** a merged product, **When** an app asks for it by its id, **Then** the answer says it was merged and gives the product it was merged into.
4. **Given** a product merged into itself, into a product already merged, a checked product merged into a pending one, two products that both have a barcode, an unknown id, or a merge that is not confirmed, **When** the command runs, **Then** it stops with a clear reason and nothing changes.
5. **Given** a merge, **When** it is saved, **Then** the system records that the merge command made it, and when; the merge cannot be undone in this feature.
6. **Given** a merged product, **When** a catalog file entry uses its key, **Then** the load is refused and nothing changes.

---

### User Story 4 - A product with a barcode carries the catalog's name (Priority: P1)

When a merchant adds a product whose barcode is checked, its name in the shop is the catalog name in the merchant's language, and the merchant cannot change it. While a barcode is still waiting to be checked, the merchant names it as before; once it is checked, every shop that has it shows the catalog name. When that name is corrected, every shop follows. Products without a barcode keep the name the merchant chose.

**Why this priority**: The owner wants every shop to sell a packaged product under one checked name, so the catalog, and later reports across shops, speak about the same product the same way. Checking (User Story 2) is what gives shops those names, so the two go together.

**Independent Test**: Add a checked barcode with `language: ar` and see the Arabic catalog name; try to rename it and be refused; add a pending barcode with a typed name, rename it, check it through the catalog file, and see the shop product now carries the catalog name; correct the catalog name and see the shop follow.

**Acceptance Scenarios**:

1. **Given** a checked catalog product with a barcode, **When** a merchant adds it with a language, **Then** the shop product's name is the catalog name in that language; a request that also sends a name is refused with a stable code.
2. **Given** a shop product linked to a checked barcoded catalog product, **When** the merchant tries to rename it, **Then** it is refused with a stable code; changing its prices still works.
3. **Given** a pending barcode, **When** a merchant adds it, **Then** they must type a name and a language, as in spec 007, and may rename it while it stays pending.
4. **Given** shop products linked to a pending barcode, **When** it is checked, **Then** each takes the catalog name in the language it was added with, and can no longer be renamed.
5. **Given** a checked barcoded catalog product, **When** its names are corrected in the catalog file, or it receives a merged pending barcode, **Then** every linked shop product that carries the catalog name shows the new name in its language.
6. **Given** a product without a barcode linked to the catalog (tomatoes), **When** the merchant names or renames it, **Then** it works as in spec 007, and catalog corrections never change it.
7. **Given** a shop that has its own product named like a catalog product, **When** it adds that barcoded catalog product, **Then** the product is added with the catalog name anyway: the barcode already makes them different products.

---

### User Story 5 - Brands and formats (Priority: P2)

A catalog product can have a brand and a format. Brands are a shared list kept in a brands file next to the catalog file; a format is a quantity and a unit (grams, kilograms, millilitres, litres, pieces). Shops see the brand and the format with the catalog product, and suggestions find a product by its brand.

**Why this priority**: Brand and format tell apart products that share a name (Délice milk 1 L and ½ L), but shops can work without them.

**Independent Test**: Add the brand "Délice" to the brands file, give two milk entries of the catalog file that brand and the formats 1 l and 500 ml, and load; search "délice" from a shop and see both, each with its format.

**Acceptance Scenarios**:

1. **Given** a brands file entry with a key, a name and optional other spellings, **When** the files are loaded, **Then** the brand exists and catalog entries can name it by its key; a brand name already used by another brand, once compared as in spec 007, stops the load and nothing changes.
2. **Given** a catalog entry with a format, **When** the file is loaded, **Then** the product holds a positive whole quantity and one of the units grams, kilograms, millilitres, litres or pieces; anything else stops the load and nothing changes.
3. **Given** a catalog product with a brand and a format, **When** a shop reads it, looks it up or gets it as a suggestion, **Then** the brand and the format come with it.
4. **Given** a search with a brand's name or spelling, **When** a shop asks for suggestions, **Then** checked products of that brand are among them.
5. **Given** a brand, **When** its name changes in the brands file and the files are loaded, **Then** every product of that brand shows the new name, and suggestions find it by the new name only.

---

### User Story 6 - Stop selling a product (Priority: P2)

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
7. **Given** a removed product linked to a pending catalog product, **When** the queue is listed, **Then** that shop no longer counts as using it and its name is not shown, and both come back once the product is put back.
8. **Given** another shop's product, **When** the merchant removes it or puts it back, **Then** it is refused with the same answer as for a product that does not exist.

---

### User Story 7 - Try it with standard tools (Priority: P3)

A developer or the owner runs the merchant journeys of the updated API collection, including removing a product from sale and the catalog-name rules, and runs the catalog commands of the quickstart on a local backend.

**Why this priority**: There is no screen, so the collection and the quickstart are how the feature is checked.

**Independent Test**: Import the collection and run it against a local backend without editing any request; run the quickstart's commands in order.

**Acceptance Scenarios**:

1. **Given** the collection and a local backend, **When** the requests are run in order, including removing a product from sale and putting it back, **Then** each succeeds and values carry over between requests by themselves.
2. **Given** a change to an endpoint, **When** it is merged, **Then** the collection and the contract file change with it.
3. **Given** the quickstart, **When** its commands are run in order on a local backend, **Then** each prints what the quickstart says.

### Edge Cases

- **Pending product that nobody uses any more**: cannot happen in this feature, since shop products are never deleted; a pending product always has at least the shop that created it, possibly as a removed product.
- **Offensive or meaningless typed name**: only the queue command shows it, on the server; it is never shown to shops, before or after the check, never written to the catalog files, and the check replaces it with proper names.
- **Barcode that is not a real product** (a test code, a shop's own label code that passed the check digit): it can be checked with a plain name such as "Unknown product"; removing or hiding catalog products is out of scope.
- **Key chosen for an entry is the generated key of a pending product** (`products.barcode_…`): refused, so generated keys stay reserved for pending products.
- **Catalog file entry for a merged-away product**: its key is refused (FR-012); its barcode now belongs to the product it was merged into, so the entry for that barcode is the entry of that product, with that product's key.
- **Correction made outside the file**: not possible; names, spellings, brands and formats change only through the catalog files, so a later load never undoes an earlier change.
- **Two barcodes for the same product** (for example a manufacturer's new code): they stay two catalog products in this feature; a merge of two barcoded products is refused, since a catalog product holds one barcode at most.
- **Brand renamed to the name of another brand**: refused; merging brands is out of scope.
- **Brand left out of the brands file**: it is not deleted, as catalog products are not; a catalog entry can still name it only if it is in the file.
- **A merchant's own product named exactly like a barcoded catalog product**: both stay; the barcoded one carries the catalog name (FR-028).
- **A product added from a pending barcode in Arabic, checked with an Arabic name**: the shop shows the Arabic catalog name; a shop that added it in French shows the French one.
- **Merge of a catalog product some shops removed from sale**: their removed products are relinked or unlinked like any other; they stay removed. A shop linked to both products whose product on the one that stays is removed keeps that link and that removed state, and its other product loses its link (FR-014).
- **Removing a product that later sales or stock movements point to**: allowed; those records keep pointing to it, since a removed product is never deleted.
- **A shop scans a barcode while it is being checked or merged**: the shop gets either the state before or the state after, never a mix.

## Requirements *(mandatory)*

### Functional Requirements

**Checking on the server**

- **FR-001**: The shared catalog (catalog products, their names, spellings, brands and formats, and merges) MUST change only through server commands and the catalog files they load. No API endpoint and no account role may change it; whoever can run commands on the server is trusted.
- **FR-002**: Every check, correction, merge and brand change MUST be recorded with the command that made it and when, with ids and counts only, never typed names.
- **FR-003**: Command output MUST NOT contain a merchant's name, email or phone, or a shop's name, city or id, as spec 006 FR-030 asks of logs. Only the queue command MAY print the names shops gave a pending product, without saying which shop gave them.
- **FR-004**: Every command MUST validate all its input before writing anything, stop with a clear reason code and change nothing on any error, and apply each change in one database transaction.

**Review queue**

- **FR-005**: A server command MUST list pending catalog products in pages, ordered by the number of shops using them, most first, then by the date they were first added, oldest first.
- **FR-006**: Each queue entry MUST show the product's id, its barcode, the name and language typed by the first shop, the number of shops using it, the different names (once compared as in spec 007) that the shops using it gave their own product, at most 20 in the order they were first given, and the date it was first added. It MUST NOT say which shop gave which name.
- **FR-007**: Names typed by shops, and which shop uses which pending product, MUST never be shown to other shops or through the API (as in spec 007).

**Checking and correcting**

- **FR-008**: A pending product MUST be checked by loading a catalog file entry with its barcode, a key of the form `products.<lowercase letters, digits, underscores>` not used by another catalog product and not starting with the reserved pending prefix, names in French, Arabic and English (each at most 120 characters), and optionally other spellings, a brand and a format.
- **FR-009**: Checking MUST keep the product's id, barcode and shop links, and MUST NOT change any shop's prices or any name a merchant chose; linked shop products with a barcode take the catalog name (FR-025).
- **FR-010**: A checked product MUST be corrected by changing its catalog file entry and loading the file again, under the same rules.
- **FR-011**: Two changes of the same catalog product at the same moment (two loads, or a load and a merge) MUST each apply completely or be refused and change nothing; neither may leave a mix of both.
- **FR-012**: A catalog file entry whose key is that of a merged product MUST stop the load with a stable reason code.

**Merging**

- **FR-013**: A server command MUST merge one catalog product into another after a confirmation. Afterwards the barcode of the first, if any, MUST find the second, every shop linked to the first MUST be linked to the second, and the first MUST no longer be suggested, looked up or offered for adding.
- **FR-014**: When a shop is linked to both products, its product linked to the second MUST keep the link, and its product linked to the first MUST lose its catalog link and keep its name, prices and id.
- **FR-015**: Reading a merged product by its id MUST say it was merged and give the id of the product it was merged into.
- **FR-016**: The system MUST refuse merging a product into itself, into or from a product already merged, a checked product into a pending one, and two products that both have a barcode, with stable reason codes.
- **FR-017**: A catalog product MUST hold one barcode at most, unique across the catalog, as in spec 007.

**Brands and formats**

- **FR-018**: Brands MUST be kept in a brands file loaded with the catalog file. A brand has a stable key, a name (at most 80 characters) and optional other spellings; two brands MUST NOT have the same name once compared as in spec 007 FR-015. Renaming a brand is changing its name in the file.
- **FR-019**: A catalog product MAY have one brand and one format. A format is a positive whole quantity with a unit among grams, kilograms, millilitres, litres and pieces; a fraction is written in the smaller unit (1500 ml, not 1.5 l). Pack formats such as 6 x 330 ml are out of scope.
- **FR-020**: Catalog products returned to shops (lookups, suggestions, shop products, close matches) MUST carry their brand name and format.
- **FR-021**: Suggestions (spec 007 FR-014) MUST also match brand names and their other spellings, with the same comparison rules.
- **FR-022**: The spec 007 catalog file MUST accept a brand (by its key) and a format per entry, and keep its rules: all or nothing, repeatable without duplicates, pending barcodes checked in place (spec 007 FR-003, FR-003a).

**Names of products with a barcode (shops)**

- **FR-023**: A shop product added from a checked catalog product that has a barcode MUST carry the catalog name in the language it was added with (French, Arabic or English). Adding such a product MUST require that language and MUST refuse a name sent by the merchant, with a stable code. This replaces spec 007 FR-010 for barcoded products.
- **FR-024**: A shop product linked to a pending catalog product MUST carry the name the merchant typed, which the merchant MAY rename while the product is pending. The language it was added with MUST be kept.
- **FR-025**: When a catalog product with a barcode is checked, corrected, merged into, or changed by a catalog file load, every linked shop product that carries the catalog name MUST take its current catalog name in its language, in the same change. Shop products carry the catalog name when they were added from a checked barcode (FR-023), when the pending product they were linked to is checked, or when a merge brings them, with their barcode, to a checked product. When a merge gives the barcode to a product that had none, the shop products already linked to that product keep the names their merchants chose.
- **FR-026**: Shop products whose name the merchant chose (no catalog link, a catalog product without a barcode, or a catalog product that gained its barcode through a merge after they were linked) MUST keep that name; spec 007's naming and renaming rules apply to them unchanged.
- **FR-027**: The merchant MUST NOT be able to rename a shop product that carries a catalog name; the refusal MUST use a stable code. Prices stay editable.
- **FR-028**: The "same name twice in one shop" refusal of spec 007 (FR-017) MUST apply only to names a merchant chooses; a product carrying a catalog name MAY share its name with another product of the shop, since its barcode already makes it a different product.

**Removing products from sale (shops)**

- **FR-029**: A merchant MUST be able to remove any of their products from sale at any time, and put it back on sale.
- **FR-030**: A removed product MUST NOT appear in the shop's product list, in suggestions or in close-name matches as a product of the shop; reading it by id and the barcode lookup MUST still return it, marked with the date it was removed. When a suggestion shows a catalog product whose shop product is removed, it MUST carry that removed shop product, so the app offers to put it back rather than to add it.
- **FR-031**: A removed product MUST never be physically deleted: its id, name, prices and catalog link MUST stay, so later records can point to it.
- **FR-032**: Adding a product whose barcode or catalog product is that of a removed product of the same shop, or whose merchant-chosen name is the compared name of a removed product's merchant-chosen name, MUST be refused with a stable code that points to the removed product. Names that come from the catalog are not compared (FR-028).
- **FR-033**: A removed product MUST NOT be changed by the merchant until it is put back on sale; catalog name updates (FR-025) still reach it.
- **FR-034**: Removing or putting back a product already in that state MUST change nothing and answer with the product.
- **FR-035**: A removed product MUST NOT count as a shop using its catalog product, and its name MUST NOT be shown in the queue (FR-005, FR-006); putting it back MUST count it again.

**API conventions** (as in 006 and 007)

- **FR-036**: Every endpoint MUST sit under the versioned API path, use JSON and the common error shape with stable codes, and be covered by automated tests for success, validation errors, unauthenticated access, a merchant with no shop and another shop's data. Every command MUST be covered by automated tests for success, every refusal, and no change on failure.
- **FR-037**: The feature MUST update the importable API collection and the contract file with every endpoint, and the quickstart with every command.

### Key Entities

- **Platform team**: whoever can run commands on the server. Not an account and not a role.
- **Catalog product** (from 007): gains an optional brand and format, a merged state with the product it was merged into, and the time it was checked.
- **Brand**: a shared name with a stable key and other spellings for search, given to catalog products, kept in the brands file.
- **Format**: a quantity and a unit of a catalog product.
- **Shop product** (from 007): gains the date it was removed from sale, empty while on sale, the language it was added with, and whether its name comes from the catalog (FR-023 to FR-026) or from the merchant.
- **Catalog change record**: which command changed what in the catalog (check, correction, merge, brand change) and when.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Checking a pending product takes one queue listing, one catalog file entry and one load.
- **SC-002**: After a check, 100% of lookups of that barcode by any shop return the three names, in tests.
- **SC-003**: After a check, a correction or a merge, 100% of shops' prices and of names merchants chose are unchanged, and 100% of linked shop products that carry the catalog name show the current one in their language, in tests (FR-025).
- **SC-004**: After merging A into B, 100% of lookups of A's barcode return B, and no shop is linked to A, in tests.
- **SC-005**: No API request can change the shared catalog: in tests, no route of the API writes catalog products, brands or catalog change records.
- **SC-006**: Loading the same catalog and brands files twice leaves the catalog identical to loading them once.
- **SC-007**: A person who has never seen the project runs the collection's merchant journey and the quickstart's catalog commands in under 10 minutes without editing a request.
- **SC-008**: In tests, 100% of removed products are absent from the shop's list, suggestions and close-name matches, and 100% of products put back keep their id, name and prices.
- **SC-009**: In tests, 100% of command refusals change nothing and print a reason code, and no command output contains a shop's name, city or id or a merchant's name, email or phone.

## Assumptions

- **Who checks**: the platform team, on the server (owner decision, 2026-11-19, replacing the operator review queue of 2026-11-13). Agreement between shops and outside product databases are out of scope; the queue's order by number of shops is the only use of shop agreement.
- **Backend only**: no screen and no admin API; a back-office, if any, is a separate project later (constitution I).
- **Roles**: platform and shop roles will be designed before spec 014 (employees); this feature adds none.
- **Catalog files in git**: the catalog and brands files live in the backend repository, so every check and correction goes through a pull request before it is loaded on a server.
- **Removing or hiding catalog products** (the shared catalog), undoing a merge, merging brands and categories are out of scope. Removing a shop's own product from sale is in scope (User Story 6): it hides the product for that shop only and deletes nothing.
- **Units of sale** (selling by weight) stay with roadmap step 1.9; a format describes the packaged product, not how a shop sells it.
- **The catalog download to phones** still comes with the connected-app spec 013; this feature only changes what the API returns.
- **Changes to spec 007**: products with a checked barcode carry the catalog name and cannot be renamed (User Story 4), where 007 let every shop keep its own name; 007's "same name twice" refusal now applies only to names merchants choose.
- **Shop-side changes** are returning brand, format and merge information, finding products by brand, reading a catalog product by id, and removing products from sale and putting them back; adding products works as in 007, except that a removed product is pointed to instead of being created again.
