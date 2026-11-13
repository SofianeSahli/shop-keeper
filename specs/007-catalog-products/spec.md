# Feature Specification: Shared Catalog and Shop Products

**Feature Branch**: `007-catalog-products`

**Created**: 2026-11-06

**Status**: Closed on 2026-11-13 (all tasks done and merged in #11; catalog list accepted by the owner)

**Input**: User description: "First part of roadmap step 1.3 (stock, prices and shared catalog), split by the owner because 1.3 is too big for one spec. A shared catalog of products, with and without barcode, whose names are translation keys translated in the backend; each shop's own products with their own name and their prices; adding a product by scanning a barcode or by typing its name with suggestions; duplicates blocked up to the last moment. Backend only."

## Context

Spec 006 gave each merchant an account and one shop. The shop now needs its products, with the prices the merchant charges and pays, before deliveries, stock and sales can be built on them.

Products come from two places. The shared catalog lists products once for every shop: packaged products with their barcode, and the most common products with no barcode (tomatoes, bread, eggs and similar). Each shop then has its own products, each with its own name and prices, linked to a shared product or not. A merchant adds a product either by scanning its barcode or by typing its name and picking a suggestion, and the system works hard to keep the same product from being added twice.

Shared product names are not stored as text in one language. Each shared product has a stable translation key, such as `products.bread` or `products.biscuits_choco_tom`, and its names in French, Arabic and English are kept with the catalog, so a new catalog product never needs a new app release.

This feature ships no screen. The merchant app connects to it in the connected-app spec. The stories below describe what the app will be able to do, and each one can be exercised with an API testing tool.

## Clarifications

### Session 2026-11-06

- Q: Does the shop product keep its own name? → A: Yes, always, whether it is linked to a shared product or not.
- Q: Does the shared catalog only hold barcoded products? → A: No. It also holds the most common products without a barcode (vegetables, fruit, bread, eggs and similar), seeded with the feature.
- Q: How are shared names stored? → A: As a translation key (`products.bread`), with the names in French, Arabic and English stored in the backend, not in the app's translation files.
- Q: How is a product added? → A: By barcode, or by typing a name with suggestions. Before a new product is created, the merchant must see the closest existing products and confirm that theirs is different.
- Q: Does this feature handle stock or units of sale? → A: No. Stock comes with the stock-movements spec, and units such as the kilogram come with roadmap step 1.9. A price here is the price of one unit of sale, whatever the merchant sells it by.
- Q: When is a new name close to an existing product? → A: Once compared as in FR-015, when one name contains the other, when they share a word of at least 3 letters, or when they differ by at most 2 letters.
- Q: Can the operator's catalog file hold barcoded products? → A: Yes. An entry may carry a valid, unique barcode; loading creates or updates it as checked, and an entry whose barcode is waiting to be checked turns that product into a checked one with the file's key and names.
- Q: Does the close-name hold apply to a product added with a barcode? → A: No. A different barcode already proves a different product, so it is never held as close; a name identical to another product of the shop is still refused (FR-017).
- Q: Which language gives the default name of a catalog product added without a name? → A: The request must name the language (French, Arabic or English) whenever it sends no name; without it, the request is refused as a validation error.
- Q: Are a singular and a plural the same name? → A: Yes. Comparison drops a final `s` or `x` from each Latin-script word, so `tomate` and `Tomates` are the same name and the second is refused.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Add a product by scanning its barcode (Priority: P1)

A merchant scans a product's barcode. If the shared catalog knows it, the merchant sees its name in their language, enters their sale price and, if they know it, their purchase price, and the product is in their shop. If the catalog does not know the barcode, the merchant types the product's name, and the barcode joins the catalog as a product waiting to be checked.

**Why this priority**: Scanning is the fastest way to build a shop's product list, and it is how most packaged products will be added.

**Independent Test**: With a seeded catalog, look up a known barcode and add it to a shop with a price; then look up an unknown barcode, add it with a typed name, and see it in the catalog as waiting to be checked.

**Acceptance Scenarios**:

1. **Given** a barcode of a checked catalog product, **When** the merchant looks it up, **Then** the answer gives the product's key and its names in French, Arabic and English, and says whether the shop already has it.
2. **Given** a checked catalog product not yet in the shop, **When** the merchant adds it with a sale price, **Then** a shop product is created, linked to the catalog product, with the merchant's own name (by default the catalog name in the language they send), the sale price and the optional purchase price.
3. **Given** a barcode the catalog does not know, **When** the merchant adds it with a name and a sale price, **Then** the barcode joins the catalog as waiting to be checked, with the name the merchant typed, and the shop product is linked to it.
4. **Given** a barcode another shop added and that is still waiting to be checked, **When** a second merchant looks it up, **Then** they are told the barcode is known but unchecked, are shown no name, and add it with a name of their own; both shops are linked to the same catalog product.
5. **Given** a barcode the shop already has, **When** the merchant adds it again, **Then** it is refused with a stable code and the answer points to the existing shop product.
6. **Given** a barcode with spaces, or a 12-digit code that is the same as a 13-digit one with a leading zero, **When** it is looked up or added, **Then** it matches the same catalog product.
7. **Given** a barcode with a wrong check digit, an unsupported length, or an in-store code (a 13-digit code starting with `2`, printed by a shop's own scale or labeller), **When** it is looked up or added, **Then** it is refused with a stable code and nothing is created.

---

### User Story 2 - Add a product by typing its name (Priority: P1)

A merchant types the first letters of a product's name, in French, Arabic or Latin-script Tunisian, and gets suggestions from the shared catalog and from their own products. They pick a catalog product and add it to their shop with their price, or, if nothing fits, create a product of their own.

**Why this priority**: Products without a barcode, which are a large part of a small shop's sales, can only be added this way.

**Independent Test**: Type `tom`, `طما` and `tmat`, and see the catalog's tomatoes suggested each time; add them to a shop with a price.

**Acceptance Scenarios**:

1. **Given** at least 2 typed characters, **When** the merchant asks for suggestions, **Then** they get up to 10 matches, checked catalog products and the shop's own products together, each saying whether the shop already has it.
2. **Given** a search, **When** it is compared with names, **Then** case, French accents, a final `s` or `x` and common Arabic spelling variants (أ إ آ ا, ة ه, ى ي, and short vowel marks) are ignored, and a product's other spellings count as well as its names.
3. **Given** a suggested catalog product, **When** the merchant adds it with a sale price, **Then** a shop product linked to it is created, as in User Story 1.
4. **Given** a name that matches nothing, **When** the merchant creates a product of their own with that name and a sale price, **Then** the shop product is created with no catalog link, after the duplicate check of User Story 3.
5. **Given** catalog products waiting to be checked, **When** any merchant searches, **Then** those products are never suggested by the name another shop typed.

---

### User Story 3 - Never add the same product twice (Priority: P1)

Before a product of the merchant's own is created, the system compares its name with the catalog and with the shop's products. If something is close, the creation is held and the merchant is shown the closest matches; they create their product only by confirming that it is different. Some duplicates are refused even with that confirmation.

**Why this priority**: Duplicate products split sales and, later, stock across several rows, and make every report wrong. The owner asked for this check to hold up to the last moment.

**Independent Test**: Create a shop product named `Tomates`; try to create `tomate` and see it refused, even with the confirmation, with the existing product shown; try `Tomates cerises`, see it held, confirm and see it created.

**Acceptance Scenarios**:

1. **Given** a new name close to a catalog product or a shop product, **When** the merchant creates it without confirming, **Then** nothing is created, and the answer has a stable code and up to 5 closest matches.
2. **Given** that answer, **When** the merchant sends the same creation again with an explicit confirmation that their product is different, **Then** it is created.
3. **Given** a new name that is the same as one of the shop's products once compared as in FR-015 (case, accents, a final `s` or `x`, Arabic variants and spaces ignored), **When** the merchant creates it, even with the confirmation, **Then** it is refused with a stable code and the answer points to the existing product.
4. **Given** a catalog product the shop already has, **When** the merchant adds it again, by barcode or by name, **Then** it is refused with a stable code and the answer points to the existing product.
5. **Given** two identical creations sent at the same moment, for example after a network retry, **When** both reach the backend, **Then** exactly one shop product exists.

---

### User Story 4 - A catalog of common products from day one (Priority: P1)

The catalog ships with the most common products sold without a barcode in Tunisian small shops, each with a translation key, its names in French, Arabic and English, and its other common spellings. The operator can add or correct catalog products without a new app release.

**Why this priority**: Without this list, adding a product by name finds nothing on a fresh install, and every shop types the same tomatoes again.

**Independent Test**: On a fresh database, load the catalog and search for 10 common products in each of the three languages.

**Acceptance Scenarios**:

1. **Given** a fresh database, **When** the catalog is loaded, **Then** it holds at least 150 common products without a barcode (vegetables, fruit, bread and pastries, eggs, dairy, dry goods sold loose and similar), each checked, with a key, three names and its other spellings.
2. **Given** a catalog file prepared by the operator, **When** it is loaded again with new or corrected products, **Then** new products are added, existing keys are updated in place, and nothing is duplicated.
3. **Given** a key that is not of the form `products.` followed by lowercase letters, digits and underscores, or a product missing one of the three names, **When** the catalog is loaded, **Then** the load stops, says which entry is wrong, and changes nothing.
4. **Given** a catalog name that changes, **When** shops read their products, **Then** their own names stay as they were.
5. **Given** a catalog file entry with a barcode, **When** it is loaded, **Then** the barcode is a checked catalog product with its key and three names; if that barcode was waiting to be checked, the same catalog product becomes checked and the shops linked to it stay linked with their own names.

---

### User Story 5 - See and update my products, and only mine (Priority: P2)

A merchant lists their products, reads one, and changes its own name or its prices. No merchant can reach another shop's products.

**Why this priority**: Prices change, and a merchant must be able to correct a name. Isolation between shops is a core promise of the product.

**Independent Test**: Create products in two shops; each merchant lists and edits their own and is refused the other's.

**Acceptance Scenarios**:

1. **Given** a shop with products, **When** the merchant lists them, **Then** they get them in pages, each with its own name, prices and, when linked, the catalog product's key and names.
2. **Given** a shop product, **When** the merchant changes its name or prices, **Then** the change is saved, under the same duplicate rules as a creation.
3. **Given** two merchants, **When** the first reads or changes a product of the second's shop, **Then** it is refused with the same stable code as for a product that does not exist, and nothing about it is revealed.
4. **Given** a merchant with no shop, **When** they use any product endpoint, **Then** it is refused with a stable code.

---

### User Story 6 - Try the API with standard tools (Priority: P3)

A developer or the owner imports the updated API collection into Postman and runs the product journey after the 006 journey: look up a barcode, add it, search by name, be held as a duplicate, confirm, list and edit.

**Why this priority**: The merchant app connects later, so the collection is how the feature is checked until then.

**Independent Test**: Import the collection, run it in order against a local backend with a loaded catalog, without editing any request.

**Acceptance Scenarios**:

1. **Given** the collection and a local backend with the catalog loaded, **When** the requests are run in order, **Then** each succeeds and values carry over between requests by themselves.
2. **Given** a change to an endpoint, **When** it is merged, **Then** the collection and the contract file change with it.

### Edge Cases

- **Barcode known in the catalog but waiting to be checked**: usable at once by every shop that scans it, all linked to the same catalog product; only checked products fill in a name for other shops.
- **Catalog product name typed by a merchant is wrong or offensive**: it is never shown to another shop, because only checked products show their names.
- **Same product, different words**: `tomate`, `tomates`, `طماطم`, `tmatem` all find the seeded tomatoes, because names in three languages and other spellings are searched.
- **Names in Arabic or with accents**: kept exactly as typed; only the comparison ignores the variants.
- **Leading and trailing spaces**: removed before saving; a name that is only spaces counts as missing.
- **Prices**: integer millimes. A sale price of zero is allowed (a free bag, for example); a negative price is refused. A purchase price higher than the sale price is allowed: the app can warn, the backend does not refuse.
- **Products sold by weight**: the price is the price of one unit of sale as the merchant understands it (for example per kilogram); units come with step 1.9.
- **Very common short searches** (`la`, `pa`): at most 10 suggestions are returned, best matches first.
- **Shop deletes nothing**: products cannot be deleted in this feature, so later records can always point to them.
- **Two shops add the same unknown barcode at the same moment**: exactly one catalog product exists, and both shops are linked to it.

## Requirements *(mandatory)*

### Functional Requirements

**Shared catalog**

- **FR-001**: The system MUST keep a shared catalog of products that belongs to no shop. Each catalog product MUST have a unique translation key of the form `products.<lowercase letters, digits, underscores>`, a status (checked or waiting to be checked), and an optional barcode, unique when present.
- **FR-002**: Each checked catalog product MUST have a name in French, Arabic and English, and MAY have other spellings per language, used only for search.
- **FR-003**: The catalog MUST ship with at least 150 checked common products without a barcode, loaded by an operator command from a catalog file kept in the repository. Loading MUST be repeatable: it adds new keys, updates existing ones in place, and never duplicates. A wrong entry MUST stop the load with no change.
- **FR-003a**: A catalog file entry MAY carry a barcode, validated as in FR-006 and unique in the file. Loading it creates or updates a checked product with that barcode. When the barcode belongs to a product waiting to be checked, that same product becomes checked with the file's key and names, so every shop already linked to it stays linked. An in-store code, or a barcode already held by a checked product under another key, MUST stop the load with no change.
- **FR-004**: A catalog product created from an unknown barcode MUST be waiting to be checked, and MUST keep the name and language the creating merchant typed, for later checking only. Checking products other than through the operator's catalog file (review queue, agreement between shops, external sources) is out of scope.
- **FR-005**: Shops MUST NOT change catalog products.

**Barcodes**

- **FR-006**: The system MUST accept EAN-13, EAN-8 and UPC-A barcodes, remove spaces, check the check digit, and store a UPC-A code as the EAN-13 code with a leading zero.
- **FR-007**: The system MUST refuse in-store codes (13-digit codes starting with `2`) with a stable code, and MUST NOT add them to the catalog.
- **FR-008**: Looking up a barcode MUST say whether it is unknown, known and checked (with the key and the three names), or known and waiting to be checked (with no name), and whether the shop already has it.

**Shop products**

- **FR-009**: A shop product MUST belong to one shop, have its own name (required, up to 120 characters), a sale price, an optional purchase price, and an optional link to one catalog product. Prices MUST be integer millimes, zero or more.
- **FR-010**: When a shop product is created from a checked catalog product without a name, its name MUST default to the catalog name in the language the request names (French, Arabic or English). A request with no name MUST name that language, or be refused as a validation error with a stable field code.
- **FR-011**: A shop MUST have at most one shop product per catalog product, even under concurrent requests.
- **FR-012**: The system MUST let a merchant list their products in pages, read one, and change its name and prices. It MUST NOT allow deleting a product or changing its catalog link in this feature.
- **FR-013**: Every product request MUST require a signed-in merchant who owns a shop, and MUST refuse any access to another shop's products with the same answer as for a product that does not exist.

**Search and duplicates**

- **FR-014**: The system MUST suggest up to 10 products for a search of at least 2 characters, from checked catalog products and the shop's own products, best matches first, each saying whether the shop already has it.
- **FR-015**: Search and duplicate checks MUST ignore case, French accents, spaces at the ends, a final `s` or `x` on each Latin-script word, and the Arabic variants أ إ آ ا, ة ه, ى ي and short vowel marks, and MUST search names in all three languages and other spellings.
- **FR-016**: Creating a shop product without a catalog link, or renaming a shop product whose catalog product has no barcode, MUST be held with a stable code and up to 5 closest matches when its name is close to a catalog product or a shop product, unless the request explicitly confirms that the product is different. Two names are close when, once compared as in FR-015, one contains the other, they share a word of at least 3 letters, or they differ by at most 2 letters (inserted, removed or replaced). Close matches are ranked closest first.
- **FR-017**: A name that is the same as another product of the same shop, once compared as in FR-015, MUST be refused with a stable code even with the confirmation. This applies to every creation and rename, with or without a barcode or a catalog link. A product added or renamed with a barcode is never held as close: a different barcode already makes it a different product.
- **FR-018**: Every refusal for a duplicate MUST point to the existing product.

**API conventions** (as in 006)

- **FR-019**: Every endpoint MUST sit under the versioned API path, accept and return JSON only, and use the common error shape with stable codes and no user-facing text. Catalog names are data and are returned in all three languages, so the app can show them in the selected language.
- **FR-020**: The feature MUST update the importable API collection and the contract file with every endpoint.
- **FR-021**: Every endpoint MUST have automated tests for success, validation errors, unauthenticated access, a merchant with no shop, and access to another shop's products.

### Key Entities

- **Catalog product**: a product listed once for every shop. Translation key, status (checked or waiting), optional barcode, and, while waiting, the name and language the first merchant typed. Has no price, stock or shop.
- **Catalog name**: a catalog product's name in one language (French, Arabic or English), plus its other spellings in that language for search.
- **Shop product**: a product sold by one shop. Its own name, sale price, optional purchase price, optional link to one catalog product. Stock and unit of sale come in later specs.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A merchant adds a known barcoded product to their shop in 2 requests or fewer (look up, add).
- **SC-002**: For each of the seeded products, searching its first 3 letters in French, in Arabic, or one of its other spellings, puts it in the first 10 suggestions in 95% of cases.
- **SC-003**: In testing, 100% of attempts to create a second product with the same compared name in one shop, or to add the same catalog product twice, are refused.
- **SC-004**: In testing, 100% of attempts by one merchant to reach another shop's products are refused and reveal no product data.
- **SC-005**: A name typed by a merchant for an unchecked barcode is never shown to another shop in any test.
- **SC-006**: Loading the catalog file twice leaves the catalog identical to loading it once.
- **SC-007**: A person who has never seen the project can import the collection and run the product journey in under 10 minutes without editing a request.

## Assumptions

- **Backend only**: no mobile screen in this feature. The suggestion box, the "Did you mean…?" screen and offline search come with the connected-app spec, which will also download the catalog to the phone.
- **Catalog list**: Claude drafts the first list of at least 150 common products with their French, Arabic and English names and other spellings; the owner reviews it before the feature is closed.
- **New names typed by a merchant without a barcode** create a shop product with no catalog link; they do not join the catalog. Adding such products to the catalog is an operator task through the catalog file.
- **Checking catalog products** waiting from unknown barcodes in any way other than the operator's catalog file, merging catalog duplicates, brands, formats and categories belong to the catalog-checking spec.
- **Stock, units of sale, suppliers and deliveries** belong to the next specs of step 1.3 and to step 1.9.
- **Price history**: a price change replaces the current price. Purchase prices recorded at each delivery come with the deliveries spec.
- **Currency**: prices are in the shop's currency, Tunisian dinar, as set in 006.
- **Roles**: the shop owner is the only role; employees come later.
