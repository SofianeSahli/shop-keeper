---

description: "Task list for spec 007, shared catalog and shop products"
---

# Tasks: Shared Catalog and Shop Products

**Input**: Design documents from `specs/007-catalog-products/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Required. FR-021 asks for success, validation, unauthenticated, no-shop and cross-shop tests on every endpoint, and the backend guide asks for a test of every business rule.

**Organization**: Tasks are grouped by user story so each story can be built and checked on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: The user story the task belongs to (US1 to US6)
- Every path is relative to `shop-keeper-backend/` unless it starts with `specs/`

## Path Conventions

- Module code: `app/Modules/Catalog/`, with `Actions/`, `Support/`, `Console/`, `Controllers/` (with `Requests/` and `Resources/`), `Models/`, `Policies/`, `database/`, `Tests/`
- Run every command in the container: `docker compose exec app <command>`
- Follow `shop-keeper-backend/CLAUDE.md`: thin controllers, one public `handle` per action, no comments, named constants, `vendor/bin/pint` before a task is done

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: The empty module, wired into the app

- [X] T001 Create `app/Modules/Catalog/CatalogServiceProvider.php`, which loads `app/Modules/Catalog/database/migrations` and `app/Modules/Catalog/routes.php` under the `api/v1` prefix and `api` middleware, as `app/Modules/Shops/ShopsServiceProvider.php` does, and register it in `bootstrap/providers.php`
- [X] T002 Create an empty `app/Modules/Catalog/routes.php` whose routes all sit in one group with middleware `auth:sanctum` then `shop` (the alias comes in T008)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared helpers, the current shop, name and barcode rules, tables and models that every story uses

**⚠️ CRITICAL**: No user story starts before this phase is done

### Shared and Shops

- [X] T003 [P] Add an optional `details` array to `App\Shared\Errors\ApiException` in `app/Shared/Errors/ApiException.php`, merged into `toErrorBody()` beside `code` (research R12), and add a case to `tests/Feature/Shared/ErrorFormatTest.php` showing `existing_product_id` and `matches` at the top level of `error`
- [X] T004 [P] Create `App\Shared\CurrentShop` in `app/Shared/CurrentShop.php`, a final readonly class holding `shopId` (int)
- [X] T005 [P] Write unit tests in `tests/Unit/BinaryUuidTest.php`: a UUID string is stored as 16 bytes and read back as the same lowercase string; an invalid string is refused
- [X] T006 [P] Create the cast `App\Shared\Database\BinaryUuid` in `app/Shared/Database/BinaryUuid.php`, turning a UUID string into `BINARY(16)` and back with `Ramsey\Uuid\Uuid` (research R3)
- [X] T007 [P] Create `App\Modules\Shops\Actions\FindShopIdOfOwner` in `app/Modules/Shops/Actions/FindShopIdOfOwner.php`, returning the owner's shop id or null
- [X] T008 Create `App\Modules\Shops\Controllers\Middleware\RequireShop` in `app/Modules/Shops/Controllers/Middleware/RequireShop.php`: calls `FindShopIdOfOwner` for the signed-in user, binds `CurrentShop` in the container for the request, or throws `ApiException('shop.required', 403)`; register the alias `shop` in `app/Modules/Shops/ShopsServiceProvider.php` (research R2)
- [X] T009 Write feature tests in `app/Modules/Shops/Tests/RequireShopTest.php` on a test-only route: a merchant with a shop passes and `CurrentShop` holds their shop id; a merchant with no shop gets 403 `shop.required`; no token gets `auth.unauthenticated`

### Name and barcode rules

- [X] T010 [P] Write unit tests in `app/Modules/Catalog/Tests/NameComparisonTest.php`: `Tomates`, `tomate` and ` TOMATE ` give one compared name; `Crème` equals `creme`; `œufs` equals `oeufs`; `أ إ آ` each compare as `ا`, `ة` as `ه`, `ى` as `ي`; short vowels, shadda, sukun and tatweel are ignored; `pomme-de-terre` equals `pomme de terre`; a final `s` or `x` drops from each Latin-script word longer than one letter, and never from an Arabic word; the identity key is the compared name without spaces, so `tomates cerises` and `tomatescerises` share it; a name of only spaces gives an empty form
- [X] T011 [P] Create `App\Modules\Catalog\Support\NameComparison` in `app/Modules/Catalog/Support/NameComparison.php` with `compared(string): string` and `identityKey(string): string`, following the six steps of research R5 (NFKD through `Normalizer`, remove `\p{Mn}`, map `ة → ه`, `ى → ي`, `ٱ → ا`, remove `ـ`, expand `œ → oe` and `æ → ae`, `mb_strtolower`, non-letter non-digit to space and collapse, drop one final `s` or `x` per Latin-script word longer than one letter)
- [X] T012 [P] Write unit tests in `app/Modules/Catalog/Tests/BarcodeTest.php`: valid EAN-13, EAN-8 and UPC-A accepted; spaces removed; a 12-digit UPC-A gives the EAN-13 with a leading `0` (e.g. `036000291452` → `0036000291452`); a wrong check digit and lengths other than 8, 12 and 13 give `barcode.invalid`; a 13-digit code starting with `2` gives `barcode.in_store`; letters give `barcode.invalid`
- [X] T013 [P] Create the value object `App\Modules\Catalog\Support\Barcode` in `app/Modules/Catalog/Support/Barcode.php` with `parse(string): self`, `value(): string` and a failure carrying `barcode.invalid` or `barcode.in_store` (research R4), and a validation rule `App\Modules\Catalog\Controllers\Requests\Rules\ValidBarcode` in `app/Modules/Catalog/Controllers/Requests/Rules/ValidBarcode.php` that reports those field codes
- [X] T014 [P] Write unit tests in `app/Modules/Catalog/Tests/CodePointDistanceTest.php`: `tomate`/`tomat` is 1, `طماطم`/`طماطة` is 1 after comparison (not 2 as byte-wise `levenshtein()` gives), identical strings 0, insert, remove and replace each count 1
- [X] T015 [P] Create `App\Modules\Catalog\Support\CodePointDistance` in `app/Modules/Catalog/Support/CodePointDistance.php`, a Levenshtein distance over `mb_str_split` code points (research R7)

### Tables and models

- [X] T016 Add migration `app/Modules/Catalog/database/migrations/2026_11_07_000001_create_global_products_table.php`: `id` bigint auto; `translation_key` string(100), unique, `^products\.[a-z0-9_]+$`; `status` string(10), `verified` or `pending`; `barcode` string(13), null, unique; `pending_name` string(120), null, set only while `pending`; `pending_language` char(2), null, `fr`, `ar` or `en`, set only while `pending`; timestamps
- [X] T017 Add migration `app/Modules/Catalog/database/migrations/2026_11_07_000002_create_global_product_names_table.php`: `id` bigint auto; `global_product_id` FK global_products, cascade delete; `language` char(2), `fr`, `ar`, `en`; `name` string(120), trimmed, kept exactly as written; timestamps; unique (`global_product_id`, `language`)
- [X] T018 Add migration `app/Modules/Catalog/database/migrations/2026_11_07_000003_create_global_product_terms_table.php`: `id` bigint auto; `global_product_id` FK global_products, cascade delete; `language` char(2); `kind` string(10), `name` or `spelling`; `term` string(120), as written in the file; `compared` string(255) with collation `utf8mb4_bin`; index (`compared`); index (`global_product_id`)
- [X] T019 Add migration `app/Modules/Catalog/database/migrations/2026_11_07_000004_create_shop_products_table.php`: `id` binary(16) primary key, client-generated UUID; `shop_id` FK shops, restrict delete; `global_product_id` FK global_products, restrict delete, null, set at creation, never changed; `name` string(120), required, trimmed, kept as written; `compared_name` string(255), `utf8mb4_bin`; `identity_key` string(255), `utf8mb4_bin`; `sale_price` unsigned bigint, millimes, 0 to 999 999 999; `purchase_price` unsigned bigint, null, millimes, 0 to 999 999 999; timestamps; unique (`shop_id`, `identity_key`); unique (`shop_id`, `global_product_id`); index (`shop_id`, `compared_name`, `id`)
- [X] T020 [P] Create enums `CatalogStatus` (`Verified` = `verified`, `Pending` = `pending`) and `CatalogLanguage` (`French` = `fr`, `Arabic` = `ar`, `English` = `en`) in `app/Modules/Catalog/Models/`
- [X] T021 Create models `GlobalProduct` (hidden `pending_name` and `pending_language`, `status` cast to `CatalogStatus`, `names` and `terms` relations, `nameIn(CatalogLanguage)`), `GlobalProductName` and `GlobalProductTerm` in `app/Modules/Catalog/Models/`, with factories in `app/Modules/Catalog/database/factories/` (states `verified` with three names and terms built through `NameComparison`, `pending`, `withBarcode`)
- [X] T022 Create model `ShopProduct` in `app/Modules/Catalog/Models/ShopProduct.php`: string key, no incrementing, `id` cast with `BinaryUuid`, prices cast to int, a `saving` hook that sets `compared_name` and `identity_key` from `name` through `NameComparison`, `globalProduct` relation, and a scope `ofShop(int)`; factory in `app/Modules/Catalog/database/factories/ShopProductFactory.php`
- [X] T023 [P] Create resources in `app/Modules/Catalog/Controllers/Resources/`: `CatalogProductResource` (`id`, `key`, `status`, `barcode`, `names` as `{ fr, ar, en }` or `null` when pending, never `pending_name`), `ShopProductSummaryResource` (`id`, `name`, `sale_price`), `ShopProductResource` (`id`, `name`, `sale_price`, `purchase_price`, `catalog_product`, `created_at`, `updated_at` in ISO 8601 UTC) and `SuggestionResource` (`catalog_product`, `shop_product`), matching `specs/007-catalog-products/contracts/api.md`
- [X] T024 [P] Create `ShopProductPolicy` in `app/Modules/Catalog/Policies/ShopProductPolicy.php` with `view` and `update`, true when the product's `shop_id` equals `CurrentShop::shopId`, and register it in `app/Modules/Catalog/CatalogServiceProvider.php`
- [X] T025 Define the rate limiter `catalog-lookup`, 120 requests per minute per account, in `app/Modules/Catalog/CatalogServiceProvider.php`, ending in `request.throttled`
- [X] T026 Create the shared price rule in `app/Modules/Catalog/Controllers/Requests/Rules/PriceInMillimes.php`: a JSON integer (strings and fractions refused as `<field>.integer`), `<field>.negative` below 0, `<field>.too_high` above the named constant `MAX_PRICE_MILLIMES = 999_999_999` (research R11)

**Checkpoint**: Module, current shop, rules, tables and models ready; `php artisan migrate:fresh` and the unit tests pass

---

## Phase 3: User Story 1 - Add a product by scanning its barcode (Priority: P1) 🎯 MVP

**Goal**: Look up a barcode and add it to the shop, whether the catalog knows it, holds it as pending, or not at all

**Independent Test**: With a seeded catalog, look up a known barcode and add it with a price; look up an unknown barcode, add it with a typed name, and see it pending in the catalog

### Tests for User Story 1

- [X] T027 [P] [US1] Write feature tests in `app/Modules/Catalog/Tests/BarcodeLookupTest.php` for `GET /api/v1/catalog/barcodes/{barcode}`: `verified` with key and three names and `shop_product` null, then set once the shop has it; `pending` with `names` null and no `pending_name` anywhere in the body; `unknown` with `catalog_product` null; spaces and a UPC-A find the same product (US1-6); `barcode.invalid` and `barcode.in_store` as `validation.failed` with nothing created (US1-7); `auth.unauthenticated`; `shop.required`; another shop's product never appears as `shop_product`; `request.throttled` past 120 per minute
- [X] T028 [P] [US1] Write feature tests in `app/Modules/Catalog/Tests/CreateProductByBarcodeTest.php` for `POST /api/v1/products` with `barcode`: a verified barcode with `sale_price` and `language: ar` and no name gives 201 with the Arabic catalog name and the link (US1-2); `purchase_price` optional; an own `name` is kept; an unknown barcode with `name` and `language` creates a `pending` catalog product keeping `pending_name` and `pending_language`, linked to the shop product (US1-3); an unknown barcode without `name` gives `name.required`, without `language` gives `language.required`; a pending barcode from another shop links the second shop to the same catalog product and needs a name (US1-4); the same barcode twice in a shop gives 409 `product.already_in_shop` with `existing_product_id` (US1-5); a name identical to another product of the shop gives `product.name_taken`; a close name is never held; `barcode` with `catalog_product_id` gives `catalog_product_id.prohibited`; prices: zero accepted, negative, string, fraction and above maximum refused; `id.invalid`; the same `id` again gives 200 and the same product; an `id` of another shop gives `id.taken`; a refused creation leaves no pending catalog product; a concurrent unknown barcode, simulated by creating the pending row first, links to it (research R8); `auth.unauthenticated`; `shop.required`

### Implementation for User Story 1

- [X] T029 [US1] Create `App\Modules\Catalog\Actions\LookUpBarcode` in `app/Modules/Catalog/Actions/LookUpBarcode.php`, returning the stored barcode, `unknown`/`verified`/`pending`, the catalog product and the current shop's product linked to it
- [X] T030 [US1] Create `BarcodeController` in `app/Modules/Catalog/Controllers/BarcodeController.php`, validating the route parameter with `ValidBarcode`, and add `GET catalog/barcodes/{barcode}` with limiter `catalog-lookup` to `app/Modules/Catalog/routes.php`
- [X] T031 [US1] Create `App\Modules\Catalog\Actions\FindOrCreatePendingProduct` in `app/Modules/Catalog/Actions/FindOrCreatePendingProduct.php`: find by barcode; otherwise insert `pending` with key `products.barcode_<13 digits>`, `pending_name` and `pending_language` inside a nested transaction, and on `UniqueConstraintViolationException` read and return the other request's row (research R8, R9)
- [X] T032 [US1] Create `App\Modules\Catalog\Actions\CreateShopProduct` in `app/Modules/Catalog/Actions/CreateShopProduct.php` for the barcode form, in one transaction: return the existing product if the shop already has this `id`, refuse `id.taken` if another shop has it; resolve the catalog product (`FindOrCreatePendingProduct` when unknown); default the name to the catalog name in `language`; refuse `product.already_in_shop` and `product.name_taken` with `existing_product_id`; insert; turn a unique violation on either index into the matching refusal (research R8). Keep the steps as small private methods so US2 and US3 add their forms
- [X] T033 [US1] Create `CreateShopProductRequest` in `app/Modules/Catalog/Controllers/Requests/CreateShopProductRequest.php`: `id` required UUID; `barcode` optional, `ValidBarcode`, prohibited with `catalog_product_id`; `name` at most 120 characters after trimming, only spaces counts as missing; `language` `fr`, `ar`, `en`; `sale_price` required, `PriceInMillimes`; `purchase_price` optional, nullable, `PriceInMillimes`; `confirm_different` optional boolean; the rules on when `name` and `language` are required live in `CreateShopProduct`, which knows whether the barcode is known, and report the same field codes
- [X] T034 [US1] Create `ShopProductController::store` in `app/Modules/Catalog/Controllers/ShopProductController.php`, answering 201, or 200 for an `id` the shop already has, and add `POST products` to `app/Modules/Catalog/routes.php`

**Checkpoint**: Barcode lookup and creation work and their tests pass

---

## Phase 4: User Story 2 - Add a product by typing its name (Priority: P1)

**Goal**: Suggestions from the catalog and the shop's products; add a suggested catalog product, or an own product

**Independent Test**: Type `tom`, `طما` and `tmat` and see the catalog's tomatoes each time; add them with a price

### Tests for User Story 2

- [X] T035 [P] [US2] Write feature tests in `app/Modules/Catalog/Tests/SuggestionsTest.php` for `GET /api/v1/products/suggestions`: `q` of 1 character gives `q.too_short`, missing gives `q.required`, over 120 gives `q.too_long`; at most 10 results, best first (exact, name start, word start, contains; shop products first on a tie); a catalog product the shop has comes once with both sides set; `tom`, `Tomates`, `طما`, `طماطة` and `tmat` find the tomatoes; accents and Arabic variants ignored (US2-2); pending products never suggested, by their `pending_name` or otherwise (US2-5); another shop's products never suggested; `auth.unauthenticated`; `shop.required`; `request.throttled` shared with the lookup
- [X] T036 [P] [US2] Write feature tests in `app/Modules/Catalog/Tests/CreateProductFromCatalogTest.php` for `POST /api/v1/products` with `catalog_product_id`: 201 linked, name defaulting to the catalog name in `language` (US2-3); `language.required` with no name; `catalog_product_id.unknown` for a missing or pending id; `product.already_in_shop` with `existing_product_id`; `product.name_taken`; never held as close
- [X] T037 [P] [US2] Write feature tests in `app/Modules/Catalog/Tests/CreateOwnProductTest.php` for `POST /api/v1/products` with neither `barcode` nor `catalog_product_id`: 201 with no catalog link when nothing is close (US2-4); `name.required`, including only spaces; Arabic and accented names kept exactly; `product.name_taken` for `tomate` when `Tomates` exists

### Implementation for User Story 2

- [X] T038 [US2] Create `App\Modules\Catalog\Actions\SuggestProducts` in `app/Modules/Catalog/Actions/SuggestProducts.php`, following research R6: compare `q` with `NameComparison`; match `global_product_terms.compared` of `verified` products and the shop's `shop_products.compared_name` with prefix, word-start and contains `LIKE`; keep each product's best term; rank; merge a catalog product with the shop product linked to it; at most the named constant `MAX_SUGGESTIONS = 10`
- [X] T039 [US2] Create `SuggestionsRequest` in `app/Modules/Catalog/Controllers/Requests/SuggestionsRequest.php` (`q` required, at least 2 characters after trimming, at most 120) and `SuggestionController` in `app/Modules/Catalog/Controllers/SuggestionController.php`, and add `GET products/suggestions` with limiter `catalog-lookup` to `app/Modules/Catalog/routes.php`, before any `products/{product}` route
- [X] T040 [US2] Extend `CreateShopProduct` in `app/Modules/Catalog/Actions/CreateShopProduct.php` with the `catalog_product_id` form (a `verified` product only, else `catalog_product_id.unknown`) and the own-product form (no link, `name` required), both through the same `already_in_shop` and `name_taken` steps

**Checkpoint**: Suggestions and the three creation forms work; tests of US1 and US2 pass

---

## Phase 5: User Story 3 - Never add the same product twice (Priority: P1)

**Goal**: Hold a close own product until the merchant confirms; refuse the same name and the same catalog product even then, also under concurrency

**Independent Test**: Create `Tomates`; `tomate` is refused even with the confirmation; `Tomates cerises` is held, then created once confirmed

### Tests for User Story 3

- [X] T041 [P] [US3] Write unit-level feature tests in `app/Modules/Catalog/Tests/FindCloseProductsTest.php`: close when one compared name contains the other (shorter at least 3 letters), when they share a word of at least 3 letters, or when they differ by at most 2 letters, in French and Arabic; not close otherwise (`la` shared, distance 3); pending catalog products never returned; the product being renamed and its own catalog product excluded; at most 5, closest first (distance, then containment, then shared word, then alphabetical); a catalog product and the shop's product linked to it come as one match
- [X] T042 [P] [US3] Write feature tests in `app/Modules/Catalog/Tests/DuplicateHoldTest.php` for `POST /api/v1/products`, own-product form: a close name without confirmation gives 409 `product.name_close` with up to 5 `matches` in the Suggestion shape and creates nothing (US3-1); the same request with `confirm_different: true` gives 201 (US3-2); an identical compared name gives `product.name_taken` even with the confirmation, with `existing_product_id` (US3-3); a catalog product added twice by barcode or by id gives `product.already_in_shop` (US3-4); two identical creations with different `id`s, simulated by inserting the first row before the second request, leave exactly one product and answer the second with `product.name_taken` (US3-5); the same `id` sent twice leaves one product and answers 200

### Implementation for User Story 3

- [X] T043 [US3] Create `App\Modules\Catalog\Actions\FindCloseProducts` in `app/Modules/Catalog/Actions/FindCloseProducts.php`, following research R7: SQL prefilter over `verified` terms and the shop's products (length within 2, containment both ways, shared words of at least 3 letters), then `CodePointDistance`, containment and shared-word checks in PHP; exclusions for a rename; ranking; merge; at most the named constant `MAX_CLOSE_MATCHES = 5`
- [X] T044 [US3] Add the hold step to `CreateShopProduct` in `app/Modules/Catalog/Actions/CreateShopProduct.php`: for the own-product form only, when `confirm_different` is not `true` and `FindCloseProducts` returns matches, throw `ApiException('product.name_close', 409)` with `matches` in `details`, rendered through `SuggestionResource`; the `name_taken` step runs before it, so the same name is refused even when confirmed

**Checkpoint**: Every duplicate rule of FR-016 to FR-018 holds; tests of US1 to US3 pass

---

## Phase 6: User Story 4 - A catalog of common products from day one (Priority: P1)

**Goal**: An operator command that loads the catalog file all-or-nothing, and a first list of at least 150 products

**Independent Test**: On a fresh database, load the catalog and search for 10 common products in each language

### Tests for User Story 4

- [X] T045 [P] [US4] Create fixture files in `app/Modules/Catalog/Tests/fixtures/`: `catalog-valid.json` (3 unbarcoded entries with spellings, 1 barcoded), `catalog-updated.json` (one new key, one changed name, one changed spelling), and one invalid file per refusal: bad key form, missing Arabic name, name over 120 characters, duplicate key, duplicate barcode, invalid barcode, in-store barcode
- [X] T046 [US4] Write command tests in `app/Modules/Catalog/Tests/LoadCatalogCommandTest.php` for `catalog:load {file}`: a fresh load creates `verified` products with three names and their terms (US4-1); loading the same file again leaves every row and `updated_at` unchanged and prints 0 added, 0 updated (SC-006); the updated file adds the new key and updates the changed one in place, with rebuilt terms (US4-2); each invalid fixture exits non-zero, prints the entry position, key and reason, and changes nothing (US4-3); a shop's own name stays when a catalog name changes (US4-4); a file entry with the barcode of a `pending` product turns that same row `verified` with the file's key and names, clears `pending_name` and `pending_language`, and keeps the shops linked (US4-5); a barcode held by a `verified` product under another key, a file key already used by another barcode, and a changed barcode on an existing key each stop the load with no change; keys missing from the file are kept
- [X] T047 [P] [US4] Write a test in `app/Modules/Catalog/Tests/SeedCatalogTest.php` against the real `products.json`: at least 150 entries, all valid; for each entry, its first 3 letters in French, in Arabic, and each other spelling put it in the first 10 suggestions for at least 95% of entries (SC-002)

### Implementation for User Story 4

- [X] T048 [P] [US4] Create `App\Modules\Catalog\Support\CatalogFileEntry` in `app/Modules/Catalog/Support/CatalogFileEntry.php`: parses one entry (`key`, optional `barcode`, `names.fr|ar|en`, optional `spellings.fr|ar|en` lists), trims names, and reports a reason code (`key.invalid`, `name.missing`, `name.too_long`, `barcode.invalid`, `barcode.in_store`)
- [X] T049 [US4] Create `App\Modules\Catalog\Actions\LoadCatalogFile` in `app/Modules/Catalog/Actions/LoadCatalogFile.php`, following research R10: validate the whole file, then check it against the database, then in one transaction add, check pending rows, update changed rows and rebuild their terms through `NameComparison`, and skip unchanged rows; return added, updated and unchanged counts, or the first error with its position, key and reason
- [X] T050 [US4] Create `LoadCatalogCommand` (`catalog:load {file?}`, default `app/Modules/Catalog/database/catalog/products.json`) in `app/Modules/Catalog/Console/LoadCatalogCommand.php`, printing the counts or the error and exiting non-zero on error; register it in `app/Modules/Catalog/CatalogServiceProvider.php`
- [X] T051 [US4] Draft `app/Modules/Catalog/database/catalog/products.json` with at least 150 checked products without a barcode common in Tunisian small shops: vegetables, fruit, bread and pastries, eggs, dairy, dry goods sold loose, spices, oils and similar; each with a key `products.<snake_case_english>`, names in French, Arabic and English, and other spellings including Latin-script Tunisian under `ar` (research R10)
- [X] T052 [US4] Make `database/seeders/DatabaseSeeder.php` call `catalog:load` with the default file

**Checkpoint**: The catalog loads and reloads safely, and the seed list passes the SC-002 test

---

## Phase 7: User Story 5 - See and update my products, and only mine (Priority: P2)

**Goal**: List, read and change a shop's products, under the same duplicate rules, with no access to another shop's

**Independent Test**: Products in two shops; each merchant lists and edits their own and is refused the other's

### Tests for User Story 5

- [X] T053 [P] [US5] Write feature tests in `app/Modules/Catalog/Tests/ListProductsTest.php` for `GET /api/v1/products`: cursor pages ordered by compared name then id, `per_page` default 50, 1 to 100 else `per_page.invalid`, `next_cursor` gives the next page with no overlap; each item in the ShopProduct shape with catalog names when linked and `names` null when pending (US5-1); only the shop's own products; `auth.unauthenticated`; `shop.required`
- [X] T054 [P] [US5] Write feature tests in `app/Modules/Catalog/Tests/ShowProductTest.php` for `GET /api/v1/products/{product}`: 200 ShopProduct; an unknown UUID, a non-UUID and another shop's product all give the same 404 `product.not_found` with no product data (US5-3, SC-004); `auth.unauthenticated`; `shop.required`
- [X] T055 [P] [US5] Write feature tests in `app/Modules/Catalog/Tests/UpdateProductTest.php` for `PATCH /api/v1/products/{product}`: change name, `sale_price`, `purchase_price`, and clear `purchase_price` with `null` (US5-2); an empty body gives `fields.required`; `id`, `barcode` and `catalog_product_id` give `<field>.prohibited`; price rules as on creation; a rename to another product's compared name gives `product.name_taken`; renaming to its own name with other case is allowed; a close rename is held for a product with no link or linked to an unbarcoded catalog product, and passes with `confirm_different`; a product linked to a barcoded catalog product is never held; another shop's product gives `product.not_found` and is unchanged; `auth.unauthenticated`; `shop.required`

### Implementation for User Story 5

- [X] T056 [US5] Add `index` and `show` to `ShopProductController` in `app/Modules/Catalog/Controllers/ShopProductController.php`: `index` uses `cursorPaginate` on `ShopProduct::ofShop(...)` ordered by `compared_name` then `id` with `globalProduct.names` eager loaded; `show` finds the product by id within the current shop, answers `product.not_found` (404) when missing or not a UUID, and applies `ShopProductPolicy::view`; add `ListProductsRequest` for `per_page`; add `GET products` and `GET products/{product}` (UUID-constrained) to `app/Modules/Catalog/routes.php`
- [X] T057 [US5] Create `App\Modules\Catalog\Actions\UpdateShopProduct` in `app/Modules/Catalog/Actions/UpdateShopProduct.php`, reusing the `name_taken` step and `FindCloseProducts` (excluding itself and its catalog product, skipped when its catalog product has a barcode) from `CreateShopProduct`; extract the shared steps into a small class in `app/Modules/Catalog/Actions/` rather than duplicating them
- [X] T058 [US5] Create `UpdateShopProductRequest` in `app/Modules/Catalog/Controllers/Requests/UpdateShopProductRequest.php` (`name`, `sale_price`, `purchase_price` nullable, `confirm_different`; at least one of the first three; `id`, `barcode`, `catalog_product_id` prohibited) and `ShopProductController::update` applying `ShopProductPolicy::update`; add `PATCH products/{product}` to `app/Modules/Catalog/routes.php`

**Checkpoint**: Every endpoint of the contract works; all story tests pass

---

## Phase 8: User Story 6 - Try the API with standard tools (Priority: P3)

**Goal**: One Postman collection for the whole API, with the product journey after the 006 journey

**Independent Test**: Import the collection and run it in order against a local backend with the catalog loaded, without editing a request, twice

- [X] T059 [US6] Rename `postman/shop-keeper-006.postman_collection.json` to `postman/shop-keeper.postman_collection.json` with `git mv`
- [X] T060 [US6] Update `tests/Feature/Shared/ApiCollectionTest.php` for the new file name, strip query strings from request URLs, and map `{{barcode}}` to `{barcode}` as `{{x_id}}` maps to `{x}` (research R14); it must fail until T061 adds the product requests
- [X] T061 [US6] Add a "Products" folder to `postman/shop-keeper.postman_collection.json`, after the 006 folders, following the quickstart scenarios 1 to 16: a pre-request script setting `barcode` to a random valid EAN-13 starting with `619` and `product_id` to a new UUID per creation; look up, create by barcode, look up again, suggestions for `tom`, create from the suggested `catalog_product_id`, create `Tomates cerises` held, resend confirmed, list, read and change a price; test scripts store `product_id` and `catalog_product_id` and accept the documented answers. The owner runs it by hand (backend guide)

**Checkpoint**: `ApiCollectionTest` passes; the collection matches every route

---

## Phase 9: Polish & Cross-Cutting Concerns

- [X] T062 [P] Write `app/Modules/Catalog/Tests/PendingNamePrivacyTest.php`: one shop adds an unknown barcode with a distinctive name; a second merchant calls every product and catalog endpoint, including lookups, suggestions with that name, a hold on a similar name, list and errors, and no response body contains that name (SC-005)
- [X] T063 [P] Extend `tests/Feature/Shared/NoSecretsInLogsTest.php` so a run of product requests writes no product name, `pending_name` or token to the log
- [X] T064 [P] Update `specs/006-auth-shop/contracts/api.md` and `specs/006-auth-shop/quickstart.md` to the new collection path
- [X] T065 [P] Update `README.md` in `shop-keeper-backend/` with the `catalog:load` step and the renamed collection
- [X] T066 Check that `specs/007-catalog-products/contracts/api.md` matches the delivered routes, shapes and codes, and fix the contract or the code where they differ
- [X] T067 Run `docker compose exec app vendor/bin/pint` and `docker compose exec app php artisan test`; both must pass
- [X] T068 Walk through `specs/007-catalog-products/quickstart.md` scenarios 17 to 19 (catalog file edits) by hand, and leave the Postman run (scenarios 1 to 16) and the review of `products.json` to the owner

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: none
- **Foundational (Phase 2)**: after Setup; blocks every story
- **US1 (Phase 3)**: after Foundational; creates `CreateShopProduct` and the `POST /products` route
- **US2 (Phase 4)**: after US1 (extends `CreateShopProduct`)
- **US3 (Phase 5)**: after US2 (adds the hold to the own-product form)
- **US4 (Phase 6)**: after Foundational only; can run beside US1 to US3. T047 also needs `SuggestProducts` (T038)
- **US5 (Phase 7)**: after US3 (update reuses the duplicate steps); `index` and `show` (T056) only need Foundational
- **US6 (Phase 8)**: after US1 to US5, since the collection covers every route
- **Polish (Phase 9)**: after every story

### Within Each Story

- Tests first, failing; then actions, then requests and controllers, then routes
- Run the story's tests and Pint before moving on

### Parallel Opportunities

- Phase 2: T003, T004, T005/T006, T007, the three rule pairs T010/T011, T012/T013, T014/T015, T020, T023, T024 touch different files
- US4 can be built beside US1 to US3 by another person; the catalog list (T051) can be drafted at any time
- Each story's test files are marked [P] and can be written together

---

## Parallel Example: Foundational rules

```bash
Task: "Write unit tests in app/Modules/Catalog/Tests/NameComparisonTest.php"
Task: "Write unit tests in app/Modules/Catalog/Tests/BarcodeTest.php"
Task: "Write unit tests in app/Modules/Catalog/Tests/CodePointDistanceTest.php"
```

## Parallel Example: User Story 2

```bash
Task: "Write feature tests in app/Modules/Catalog/Tests/SuggestionsTest.php"
Task: "Write feature tests in app/Modules/Catalog/Tests/CreateProductFromCatalogTest.php"
Task: "Write feature tests in app/Modules/Catalog/Tests/CreateOwnProductTest.php"
```

## Parallel Example: User Story 5

```bash
Task: "Write feature tests in app/Modules/Catalog/Tests/ListProductsTest.php"
Task: "Write feature tests in app/Modules/Catalog/Tests/ShowProductTest.php"
Task: "Write feature tests in app/Modules/Catalog/Tests/UpdateProductTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1 and Phase 2
2. Phase 3 (US1): barcode lookup and creation
3. Stop and check: US1 tests pass; a barcode can be added with factories standing in for the catalog

### Incremental Delivery

1. US1 → barcode products
2. US2 → suggestions and products by name
3. US3 → the duplicate hold
4. US4 → the real catalog and its loader (can come earlier, in parallel)
5. US5 → list, read, update
6. US6 → the collection, then Polish

The pull request stays one commit (root `CLAUDE.md`); every phase checkpoint is a good moment to run the full test suite.
