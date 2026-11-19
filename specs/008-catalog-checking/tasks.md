---

description: "Task list for spec 008, catalog checking"
---

# Tasks: Catalog Checking

**Input**: Design documents from `specs/008-catalog-checking/`

**Prerequisites**: plan.md, spec.md (with the 2026-11-16 clarifications), research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Required. FR-023 asks for success, validation, signed-out and non-operator tests on every endpoint, and the backend guide asks for a test of every business rule.

**Organization**: Tasks are grouped by user story so each story can be built and checked on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: The user story the task belongs to (US1 to US7)
- Every path is relative to `shop-keeper-backend/` unless it starts with `specs/`

## Path Conventions

- Catalog code: `app/Modules/Catalog/`; role code: `app/Modules/Identity/`; shop data: `app/Modules/Shops/`
- Run every command in the container: `docker compose exec app <command>`
- Follow `shop-keeper-backend/CLAUDE.md`: thin controllers, one public `handle` per action, no comments, named constants, `vendor/bin/pint` before a task is done. Never edit a 007 migration; add new ones

---

## Phase 1: Setup

- [ ] T001 Add an empty operator route group to `app/Modules/Catalog/routes.php`: prefix `operator/catalog`, middleware `auth:sanctum` then `operator` (the alias comes in T004)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The operator role, the new catalog columns, brands and change records that every story uses

**⚠️ CRITICAL**: No user story starts before this phase is done, and the whole 007 suite still passes at its end

### Operator role

- [ ] T002 Add migration `app/Modules/Identity/database/migrations/2026_11_16_000001_add_operator_since_to_users_table.php`: `operator_since` timestamp, null
- [ ] T003 [P] Add `isOperator(): bool` and the `operator_since` cast to `app/Modules/Identity/Models/User.php`, and an `operator()` state to `app/Modules/Identity/database/factories/UserFactory.php`
- [ ] T004 Create `App\Modules\Identity\Controllers\Middleware\RequireOperator` in `app/Modules/Identity/Controllers/Middleware/RequireOperator.php`, throwing `ApiException('operator.required', 403)` when the signed-in account is not an operator; register the alias `operator` in `app/Modules/Identity/IdentityServiceProvider.php`
- [ ] T005 Write feature tests in `app/Modules/Identity/Tests/RequireOperatorTest.php` on a test-only route: an operator passes; a merchant gets 403 `operator.required`; no token gets `auth.unauthenticated`; an operator who owns a shop still passes the `shop` middleware on merchant routes (FR-002)

### Catalog columns, brands and records

- [ ] T006 Add migration `app/Modules/Catalog/database/migrations/2026_11_16_000001_add_checking_columns_to_global_products_table.php`: `merged_into_id` FK global_products, null; `format_quantity` unsigned int, null; `format_unit` string(5), null; `linked_shops_count` unsigned int, default 0, filled from `shop_products`; `checked_by` FK users, null on delete, null; `checked_at` timestamp, null; index (`status`, `linked_shops_count`, `created_at`, `id`). `barcode` stays as in 007 (one at most)
- [ ] T007 Add migration `app/Modules/Catalog/database/migrations/2026_11_16_000002_create_brands_table.php`: `id`; `name` string(80); `compared` string(255) `utf8mb4_bin`, unique; `spellings` JSON; timestamps; then add `brand_id` FK brands, restrict delete, null, to `global_products`
- [ ] T008 Add migration `app/Modules/Catalog/database/migrations/2026_11_16_000003_create_catalog_changes_table.php`: `id`; `operator_id` FK users, null on delete, null; `action` string(20); `global_product_id` FK global_products, null; `brand_id` FK brands, null; `details` JSON; `created_at`
- [ ] T009 [P] Add `CatalogStatus::Merged`, `CatalogTermKind::Brand`, and the enums `FormatUnit` (`g`, `kg`, `ml`, `l`, `piece`) and `CatalogChangeAction` (`check`, `correct`, `merge`, `brand_create`, `brand_rename`, `import`, `file_load`) in `app/Modules/Catalog/Models/`
- [ ] T010 [P] Create models `Brand` (`spellings` cast to array) and `CatalogChange` (no `updated_at`) in `app/Modules/Catalog/Models/`, with a `BrandFactory`; add `brand()`, `mergedInto()`, the format casts and `isMerged()` to `GlobalProduct`, and `merged()` and `withFormat()` states to `GlobalProductFactory`
- [ ] T011 Create `App\Modules\Catalog\Actions\RecordCatalogChange` in `app/Modules/Catalog/Actions/RecordCatalogChange.php`, writing one `catalog_changes` row with ids and counts only
- [ ] T012 Make `CreateShopProduct` increment `linked_shops_count` of the linked catalog product in its transaction, and add `CatalogScenario::assertLinkedShopsCountsAreExact()` in `app/Modules/Catalog/Tests/Support/CatalogScenario.php`, comparing every count with a fresh count; call it in `CreateProductByBarcodeTest` and `CreateProductFromCatalogTest`
- [ ] T013 Extend `CatalogProductResource` in `app/Modules/Catalog/Controllers/Resources/CatalogProductResource.php` with `brand` (`{ id, name }` or null), `format` (`{ quantity, unit }` or null) and `merged_into_id`, keeping 007's `barcode`; update the 007 tests that assert the exact CatalogProduct shape
- [ ] T014 Create `CatalogProductPolicy` and `BrandPolicy` in `app/Modules/Catalog/Policies/` (`manage`: the user is an operator), registered in `app/Modules/Catalog/CatalogServiceProvider.php`
- [ ] T015 Create the shared rules in `app/Modules/Catalog/Controllers/Requests/Rules/`: `CatalogKey` (form, at most 100, `key.reserved` for the `products.barcode_` prefix, `key.taken` against other products), `CatalogNames` (fr, ar, en, 1 to 120 characters), `CatalogSpellings`, `CatalogFormat` (whole quantity 1 to 1 000 000, unit in `FormatUnit`)

**Checkpoint**: migrations run on a copy of the 007 data; the 007 suite passes; the role and middleware work

---

## Phase 3: User Story 1 - See what is waiting to be checked (Priority: P1) 🎯 MVP

**Goal**: The operator lists pending products, most used first, with what each shop typed, the shop's name and city

**Independent Test**: Three shops add the same unknown barcode with different names, one shop another; the queue shows the first with 3 shops

- [ ] T016 [P] [US1] Write feature tests in `app/Modules/Shops/Tests/DescribeShopsTest.php`: given shop ids it returns each shop's name and city and nothing else; unknown ids are left out
- [ ] T017 [P] [US1] Write feature tests in `app/Modules/Catalog/Tests/PendingQueueTest.php` for `GET /api/v1/operator/catalog/pending`: order by `linked_shops_count` desc, then `created_at` asc; cursor pages with no overlap; each entry in the PendingEntry shape with `typed_name`, `typed_language`, `linked_shops_count`, `first_added_at` and `shops` (product name, shop name, city), at most 20, oldest link first; no owner name, email or phone anywhere in the body; checked and merged products absent; `per_page.invalid`; `auth.unauthenticated`; `operator.required` for a merchant
- [ ] T018 [US1] Create `App\Modules\Shops\Actions\DescribeShops` in `app/Modules/Shops/Actions/DescribeShops.php`, returning `[id => ['name' => …, 'city' => …]]` for the given ids
- [ ] T019 [US1] Create `App\Modules\Catalog\Actions\ListPendingProducts` in `app/Modules/Catalog/Actions/ListPendingProducts.php`: cursor page over pending products, one query for the linked shop products of the page (at most `MAX_SHOPS_PER_ENTRY = 20` each, oldest first), one `DescribeShops` call for their shops
- [ ] T020 [US1] Create `PendingEntryResource` in `app/Modules/Catalog/Controllers/Resources/PendingEntryResource.php` and `Operator/PendingProductController` in `app/Modules/Catalog/Controllers/Operator/PendingProductController.php`; add `GET pending` to the operator group in `app/Modules/Catalog/routes.php`

**Checkpoint**: The queue works for operators only

---

## Phase 4: User Story 2 - Check a pending product (Priority: P1)

**Goal**: The operator gives a pending product its key and names; corrections of checked products

**Independent Test**: Check a pending product; a shop that never had it sees the three names; linked shops keep their prices and now show the catalog name

- [ ] T021 [P] [US2] Write feature tests in `app/Modules/Catalog/Tests/CheckCatalogProductTest.php` for `POST /api/v1/operator/catalog/products/{catalog_product}/check`: 200 `verified` with key, names, spellings, brand and format; same id and barcode; `pending_name` and `pending_language` cleared; `checked_by` and `checked_at` set; a `check` change record with no typed name; a shop lookup afterwards returns the names (SC-002); linked shops' prices unchanged, and their names follow User Story 9 (covered in `CatalogNameSyncTest`) (SC-003); `key.invalid`, `key.reserved`, `key.taken`, `names.ar.required`, `names.fr.too_long`, `brand_id.unknown`, `format.unit.invalid`, `format.quantity.invalid` (0 and 1.5) with nothing changed; a checked product gives `catalog_product.not_pending`; a second check after the first gives `catalog_product.not_pending` (FR-010); unknown id `catalog_product.not_found`; `auth.unauthenticated`; `operator.required`
- [ ] T022 [P] [US2] Write feature tests in `app/Modules/Catalog/Tests/CorrectCatalogProductTest.php` for `PATCH /api/v1/operator/catalog/products/{catalog_product}`: partial changes of key, names, spellings, brand (including `null`) and format (including `null`); terms rebuilt so a new spelling is found by suggestions; names merchants chose unchanged; a `correct` change record; a pending product gives `catalog_product.not_verified`, a merged one `catalog_product.merged`; the same validation codes as the check; `auth.unauthenticated`; `operator.required`
- [ ] T023 [US2] Create the value object `CatalogProductDetails` in `app/Modules/Catalog/Support/CatalogProductDetails.php` (key, names, spellings, brand id, format, each optional for a correction)
- [ ] T024 [US2] Create `App\Modules\Catalog\Actions\CheckCatalogProduct` in `app/Modules/Catalog/Actions/CheckCatalogProduct.php`: lock the row, refuse unless pending, apply details through `ReplaceCatalogNames`, set status, `checked_by`, `checked_at`, clear pending fields, rebuild brand terms, record the change; a unique violation on the key answers `key.taken`
- [ ] T025 [US2] Create `App\Modules\Catalog\Actions\CorrectCatalogProduct` in `app/Modules/Catalog/Actions/CorrectCatalogProduct.php` with the same steps for a checked product, changing only the fields sent
- [ ] T026 [US2] Create `CheckCatalogProductRequest` and `CorrectCatalogProductRequest` in `app/Modules/Catalog/Controllers/Requests/Operator/` using the T015 rules, and `Operator/CatalogProductController` (`check`, `update`) applying `CatalogProductPolicy::manage`; add the two routes to the operator group

**Checkpoint**: Pending products can be checked and corrected

---

## Phase 5: User Story 3 - Merge two catalog products that are the same (Priority: P1)

**Goal**: Merge one catalog product into another; shops and the barcode follow; the merged one says where it went

**Independent Test**: Shop A on pending X, shop B on checked Y without a barcode; merge X into Y; X's barcode finds Y; shop A is linked to Y

- [ ] T027 [P] [US3] Write feature tests in `app/Modules/Catalog/Tests/MergeCatalogProductsTest.php` for `POST /api/v1/operator/catalog/products/{catalog_product}/merge`: the source's barcode moves and looks up the target (SC-004); shops on the source only are relinked; a shop on both keeps its target product linked and its source product loses the link with name, prices and id unchanged (FR-012); the source becomes `merged` with `merged_into_id`, no barcode, no terms, never suggested; both `linked_shops_count` exact; a `merge` change record with the counts; `merge.same_product`; `catalog_product.merged` for a merged source or target; `merge.checked_into_pending`; `merge.both_have_barcodes` with nothing changed; `into_id.required`, `into_id.unknown`; `auth.unauthenticated`; `operator.required`
- [ ] T028 [P] [US3] Write feature tests in `app/Modules/Catalog/Tests/ReadCatalogProductTest.php` for `GET /api/v1/catalog/products/{catalog_product}`: a checked, a pending (no names) and a merged product (`merged_into_id`); unknown id `catalog_product.not_found`; `auth.unauthenticated`; `shop.required`; and in `CreateProductFromCatalogTest`, adding a merged product gives `catalog_product_id.merged`
- [ ] T029 [US3] Create `App\Modules\Catalog\Actions\MergeCatalogProducts` in `app/Modules/Catalog/Actions/MergeCatalogProducts.php` following research R6: one transaction, both rows locked in id order, the refusals, the barcode cleared on the source then set on the target, links of shops on both removed from their source product, other links moved, source terms deleted, status and `merged_into_id` set, both counts recomputed, change recorded
- [ ] T030 [US3] Create `MergeCatalogProductsRequest` in `app/Modules/Catalog/Controllers/Requests/Operator/` and add `merge` to `Operator/CatalogProductController`; add the route to the operator group
- [ ] T031 [US3] Create the shop-side `CatalogProductController::show` in `app/Modules/Catalog/Controllers/CatalogProductController.php` and its route `GET catalog/products/{catalog_product}` in the shop group; make `CreateShopProduct` answer `catalog_product_id.merged` for a merged product

**Checkpoint**: Duplicates can be merged

---

## Phase 6: User Story 9 - A product with a barcode carries the catalog's name (Priority: P1)

**Goal**: Shop products with a checked barcode carry the catalog name in their language and follow the operator's changes; every other product keeps the merchant's name

**Independent Test**: Add a checked barcode in Arabic and see the Arabic catalog name; a rename is refused; a pending product renamed by the merchant takes the catalog name once checked; a correction reaches the shop

- [ ] T032 [US9] Add migration `app/Modules/Catalog/database/migrations/2026_11_16_000004_add_catalog_name_columns_to_shop_products_table.php`: `name_language` char(2), null, set to `fr` on existing rows; `name_from_catalog` boolean, default false; `identity_key` made nullable (the unique index `(shop_id, identity_key)` stays); then, in plain SQL, set `name_from_catalog`, the French catalog name, its compared form and `identity_key = null` on rows linked to a checked catalog product with a barcode (research R14; no action called from the migration)
- [ ] T033 [P] [US9] Write feature tests in `app/Modules/Catalog/Tests/CatalogNamedProductsTest.php`: a checked barcode with `language: ar` gives the Arabic catalog name and `name_from_catalog: true`; with `name` too gives `name.prohibited`; without `language` gives `language.required`; the same for `catalog_product_id` of a checked barcoded product; a rename gives 409 `product.name_from_catalog` and a price change works; a pending barcode needs `name` and `language`, keeps `name_language`, and can be renamed while pending; a shop with its own product named like the catalog product can still add the barcode (FR-037); a loose catalog product (no barcode) keeps the merchant's name and stays renamable (FR-035)
- [ ] T034 [P] [US9] Write feature tests in `app/Modules/Catalog/Tests/CatalogNameSyncTest.php`: after a check, every linked shop product carries the catalog name in its own `name_language` (fr, ar and en shops in one test) with `identity_key` null; after a correction, after a merge into a checked barcoded product, and after a catalog file load and an import that change the names, linked shops follow; a pending barcode merged into a checked product without a barcode: the moved shop products take the catalog name and the products already linked keep their merchants' names (research R14, finding I2); names merchants chose for loose products never change; prices never change (SC-003)
- [ ] T035 [US9] Add `name_language`, `name_from_catalog` and the nullable `identity_key` handling to `ShopProduct` (the `saving` hook leaves `identity_key` null when `name_from_catalog` is true), and `name_language` and `name_from_catalog` to `ShopProductResource`
- [ ] T036 [US9] Create `App\Modules\Catalog\Actions\SyncCatalogNames` in `app/Modules/Catalog/Actions/SyncCatalogNames.php`: for a checked catalog product with a barcode, one `UPDATE` per language of its linked shop products whose `name_from_catalog` is true, setting `name`, `compared_name` and `identity_key = null`. `CheckCatalogProduct` and `LoadCatalogFile` (a pending product checked in place) first set `name_from_catalog` on every product linked to it; `MergeCatalogProducts` sets it only on the products it moves when the source had the barcode; then each calls `SyncCatalogNames`, inside its transaction
- [ ] T037 [US9] Update `CreateShopProduct` (checked barcoded link: `language` required, `name` refused with `name.prohibited`, `name_from_catalog` true, name from the catalog, no name-taken check; pending: keep `language` as `name_language`), `UpdateShopProduct` (`product.name_from_catalog` on a rename of a product carrying a catalog name) and `ShopProductNameRules::refuseIfNameTaken` (only products whose name the merchant chose); update the 007 tests that expected an own name on a checked barcode

**Checkpoint**: Barcoded products carry the catalog name everywhere; every P1 story works

---

## Phase 7: User Story 6 - Operator accounts (Priority: P2)

**Goal**: The owner grants and revokes the operator role with a command

**Independent Test**: Grant, call the queue, revoke, call again

- [ ] T038 [P] [US6] Write command tests in `app/Modules/Identity/Tests/OperatorCommandsTest.php`: `operator:grant` sets `operator_since` and the next queue request passes; `operator:revoke` clears it and the next request gets `operator.required`; an unknown email exits non-zero and changes nothing; granting twice keeps the first date
- [ ] T039 [US6] Create `App\Modules\Identity\Actions\FindAccountIdByEmail` and the commands `GrantOperatorCommand` (`operator:grant {email}`) and `RevokeOperatorCommand` (`operator:revoke {email}`) in `app/Modules/Identity/Console/`, registered in `app/Modules/Identity/IdentityServiceProvider.php`; emails compared lowercase
- [ ] T040 [US6] Make `database/seeders/DatabaseSeeder.php` grant the operator role to `test@example.com` when the environment is not `production` (research R12)

---

## Phase 8: User Story 8 - Stop selling a product (Priority: P2)

**Goal**: A merchant removes a product from sale and puts it back; it is hidden, never deleted

**Independent Test**: Remove a product; it is gone from the list and suggestions; scanning its barcode points to it; put it back with the same id, name and prices

- [ ] T041 [US8] Add migration `app/Modules/Catalog/database/migrations/2026_11_16_000005_add_removed_at_to_shop_products_table.php`: `removed_at` timestamp, null; index (`shop_id`, `removed_at`, `compared_name`, `identity_key`); the 007 unique indexes stay as they are
- [ ] T042 [P] [US8] Write feature tests in `app/Modules/Catalog/Tests/RemoveProductFromSaleTest.php` for `POST /api/v1/products/{product}/remove` and `/restore`: remove sets `removed_at` and answers the ShopProduct; the product is absent from `GET /products`, present in `GET /products?removed=true` and only there; reading it by id and the barcode lookup show `removed_at`; restore clears it with the same id, name, prices and catalog link (SC-008); removing twice or restoring a product on sale changes nothing (FR-030); `PATCH` on a removed product gives 409 `product.removed`; the row is never deleted; another shop's product gives `product.not_found`; `removed.invalid` for `?removed=maybe`; `auth.unauthenticated`; `shop.required`
- [ ] T043 [P] [US8] Write feature tests in `app/Modules/Catalog/Tests/RemovedProductDuplicatesTest.php`: adding the barcode, the catalog product or the compared name of a removed product gives 409 `product.removed` with `existing_product_id` (FR-028), also with `confirm_different`; a rename of another product to a removed product's name gives the same; close-name matches leave removed products out; suggestions leave out removed products without a catalog link and show a catalog product whose shop product is removed with that product and its `removed_at` (FR-026); a checked barcode whose catalog name matches a removed own product is added normally (FR-028, FR-037); `linked_shops_count` drops on removal and comes back on restore (FR-031), checked with `CatalogScenario::assertLinkedShopsCountsAreExact()`; a merge of a catalog product some shops removed keeps those products removed (spec Edge Cases)
- [ ] T044 [US8] Add `removed_at` and `isRemoved()` to `ShopProduct`, a `removed()` factory state, and `removed_at` to `ShopProductResource` and `ShopProductSummaryResource`
- [ ] T045 [US8] Create `App\Modules\Catalog\Actions\RemoveShopProduct` and `RestoreShopProduct` in `app/Modules/Catalog/Actions/`: lock the row, do nothing if already in that state, set or clear `removed_at`, and decrement or increment the linked catalog product's `linked_shops_count` in the same transaction (research R13)
- [ ] T046 [US8] Make `ShopProductNameRules` answer `product.removed` with `existing_product_id` when the matching product is removed; make `UpdateShopProduct` refuse a removed product; make `FindCloseProducts` skip removed shop products and `SuggestProducts` skip removed products without a catalog link while keeping a removed shop product on its catalog product's suggestion; compare names only between merchant-chosen names; make `MergeCatalogProducts` and the count migration count products on sale only
- [ ] T047 [US8] Add `remove` and `restore` to `ShopProductController` (policy `update`), the `removed` boolean filter to `ListProductsRequest` and the list query, and the routes `POST products/{product}/remove` and `POST products/{product}/restore` to the shop group in `app/Modules/Catalog/routes.php`

---

## Phase 9: User Story 4 - Brands and formats (Priority: P2)

**Goal**: Shared brands, formats on catalog products, brand search

**Independent Test**: Create "Délice", give two milk products the brand and formats 1 l and 500 ml; a shop searching "délice" sees both

- [ ] T048 [P] [US4] Write feature tests in `app/Modules/Catalog/Tests/BrandsTest.php` for `GET`, `POST` and `PATCH /api/v1/operator/catalog/brands`: 201 with name and spellings; `brand.name_taken` for `delice` when `Délice` exists, on create and rename; `name.required`, `name.too_long` (over 80); list by name in cursor pages; a rename shows the new name on every product of the brand; `brand_create` and `brand_rename` change records; `brand.not_found`; `auth.unauthenticated`; `operator.required`
- [ ] T049 [P] [US4] Write feature tests in `app/Modules/Catalog/Tests/BrandSearchTest.php`: suggestions for a brand name or spelling return checked products of that brand (FR-019); a renamed brand is found by its new name and no longer by the old one; a shop's own product named like a brand is not held as close to every product of the brand; lookups, suggestions, shop products and close matches carry `brand` and `format` (FR-018)
- [ ] T050 [US4] Create `App\Modules\Catalog\Actions\RebuildBrandTerms` in `app/Modules/Catalog/Actions/RebuildBrandTerms.php` (replace the `brand` term rows of the given products from their brand's name and spellings), and make `FindCloseProducts` ignore terms of kind `brand`
- [ ] T051 [US4] Create `App\Modules\Catalog\Actions\CreateBrand` and `RenameBrand` in `app/Modules/Catalog/Actions/` (compared name unique; a rename rebuilds the brand terms of its products in the same transaction; changes recorded), with `BrandResource`, form requests in `app/Modules/Catalog/Controllers/Requests/Operator/` and `Operator/BrandController`; add the three routes to the operator group

**Checkpoint**: Brands and formats show everywhere shops see catalog products

---

## Phase 10: User Story 5 - Import many catalog products at once (Priority: P3)

**Goal**: A comma-separated import through one importer, by server command and by API, with the rules of the 007 catalog file

**Independent Test**: Import 20 lines with 2 pending barcodes by command; 18 added, 2 checked in place; the same file through the API changes nothing

- [ ] T052 [P] [US5] Create fixtures in `app/Modules/Catalog/Tests/fixtures/`: `import-valid.csv` (20 lines, 2 barcodes made pending by the test, brands and formats) and one invalid file per refusal: bad header, bad barcode, missing Arabic name, bad key, reserved key, unknown unit, duplicate barcode, a barcode already on another key
- [ ] T053 [US5] Write command tests in `app/Modules/Catalog/Tests/ImportCatalogCommandTest.php` for `catalog:import {file} --operator=<email>`: counts printed; pending barcodes checked in place with shops still linked; unknown brands created; a second import leaves the catalog identical (SC-006); each invalid file prints `line <n> (<key>): <reason>`, exits non-zero and changes nothing; an unknown operator email exits non-zero; one `import` change record with the operator. In `LoadCatalogCommandTest`, add the 007 JSON file with `brand` and `format`, `--operator`, and the reserved-key rule
- [ ] T054 [US5] Write feature tests in `app/Modules/Catalog/Tests/ImportEndpointTest.php` for `POST /api/v1/operator/catalog/imports`: the valid fixture gives 200 with the same counts as the command; each invalid fixture gives 422 `import.invalid_line` with the same `line`, `key` and `reason` as the command (US5 scenario 5); text above 1 MB gives 413 `import.too_large`; `csv.required`; an `import` change record with the operator; `auth.unauthenticated`; `operator.required`
- [ ] T055 [US5] Extend `CatalogFileEntry` in `app/Modules/Catalog/Support/CatalogFileEntry.php` with an optional brand name and format, and the reserved-key rule; make `LoadCatalogFile` create missing brands, set brand and format, and record a `file_load` or `import` change
- [ ] T056 [US5] Create `CsvCatalogReader` in `app/Modules/Catalog/Support/CsvCatalogReader.php` (UTF-8 text, header checked, `|`-separated spellings, line numbers kept for errors) and `App\Modules\Catalog\Actions\ImportCatalogCsv` in `app/Modules/Catalog/Actions/ImportCatalogCsv.php` (reader then `LoadCatalogFile`, returning counts or a line error)
- [ ] T057 [US5] Create `ImportCatalogCommand` in `app/Modules/Catalog/Console/ImportCatalogCommand.php`, add `--operator` to `LoadCatalogCommand`, register both in `app/Modules/Catalog/CatalogServiceProvider.php`; create `ImportCatalogRequest` (`csv` required string, `MAX_IMPORT_BYTES = 1_048_576` giving `import.too_large`) and `Operator/ImportController`, and add `POST imports` to the operator group

---

## Phase 11: User Story 7 - Try it with standard tools (Priority: P3)

- [ ] T058 [US7] Add the folder "6. Operator catalog" to `postman/shop-keeper.postman_collection.json` (research R12): sign in with `operator_email` and `operator_password` and store `operator_token`; add a pending barcode from the folder 2 shop; list the queue; create a brand; check the product; look it up from the shop; add a second pending barcode and merge it into a checked product without a barcode; read the merged product; correct the brand of the checked product; import a two-line CSV through the API; the operator requests use `{{operator_token}}`. In folder 5, add: try to rename a product added by a checked barcode (`product.name_from_catalog`), remove a product from sale, list removed products, try to add it again (`product.removed`), put it back. The owner runs it by hand
- [ ] T059 [US7] Make sure `tests/Feature/Shared/ApiCollectionTest.php` passes: every new route, including `{{catalog_product_id}}`, `{{brand_id}}` and the product remove and restore paths, has its request

---

## Phase 12: Polish & Cross-Cutting Concerns

- [ ] T060 [P] Extend `tests/Feature/Shared/NoSecretsInLogsTest.php` and add `app/Modules/Catalog/Tests/TypedNamePrivacyTest.php`: after a check and a merge, no shop-facing answer, log or change record contains a name typed by another shop, and no answer of any kind contains an owner's name, email or phone; a removed product never appears in another shop's answers
- [ ] T061 [P] Update `specs/007-catalog-products/contracts/api.md` (CatalogProduct gains `brand`, `format`, `merged_into_id` and the `merged` status; ShopProduct gains `removed_at`, `name_language` and `name_from_catalog`; a checked barcode gives the catalog name and refuses `name`; `GET /products` lists products on sale; see 008), `shop-keeper-backend/CLAUDE.md` (who verifies pending products is decided: the operator queue, spec 008), `shop-keeper-backend/README.md` (operator commands, import by command and API, seeded operator) and the root `CLAUDE.md` focus line
- [ ] T062 Check that `specs/008-catalog-checking/contracts/api.md` matches the delivered routes, shapes and codes, and fix the contract or the code where they differ
- [ ] T063 Write `app/Modules/Catalog/Tests/CatalogVolumeTest.php`, in its own Pest group `volume` excluded from the default run: seed 10 000 pending products and a catalog product linked to 1 000 shops; assert the queue's first page answers in under 200 ms and the merge of that product in under 2 s on the Docker setup (plan Performance Goals, finding G1); document `php artisan test --group=volume` in the quickstart
- [ ] T064 Run `docker compose exec app vendor/bin/pint` and `docker compose exec app php artisan test`; both must pass
- [ ] T065 Walk through `specs/008-catalog-checking/quickstart.md` scenarios 11 to 14 (imports, commands, change records) by hand, and leave the Postman run to the owner

---

## Dependencies & Execution Order

- **Setup (Phase 1)**: none
- **Foundational (Phase 2)**: blocks every story; ends with the 007 suite green
- **US1, US2, US3 (Phases 3 to 5)**: after Foundational; independent of each other (factories provide checked, pending and merged products)
- **US9 (Phase 6)**: after US2 and US3 (it hooks the name sync into check, correct and merge); its file-load and import tests are completed in US5
- **US6 (Phase 7)**: after Foundational; earlier tests use the factory `operator()` state, so it can come at any time
- **US8 (Phase 8)**: after Foundational (the counter, T012); its merge test needs US3
- **US4 (Phase 9)**: after US2 (check and correct set brand and format)
- **US5 (Phase 10)**: after US4 (brands) and US9 (imports sync names)
- **US7 (Phase 11)**: after every endpoint exists
- **Polish (Phase 12)**: last

### Parallel Opportunities

- T003 with T002; T009 and T010 together; each story's test files are marked [P]
- US1, US2, US3, US6 and US8 can be built by different people once Phase 2 is done

## Parallel Example: P1 stories

```bash
Task: "Write feature tests in app/Modules/Catalog/Tests/PendingQueueTest.php"
Task: "Write feature tests in app/Modules/Catalog/Tests/CheckCatalogProductTest.php"
Task: "Write feature tests in app/Modules/Catalog/Tests/MergeCatalogProductsTest.php"
```

## Implementation Strategy

### MVP First

1. Phases 1 and 2
2. US1 then US2: the queue and the check, which is what the owner decided this spec is for
3. Stop and check: a pending product can be found and checked, and shops see its names

### Incremental Delivery

1. US1 → queue
2. US2 → check and correct
3. US3 → merge
4. US9 → barcoded products carry the catalog name
5. US6 → role commands and seeded operator
6. US8 → merchants remove products from sale and put them back
7. US4 → brands and formats
8. US5 → import by command and API
9. US7 → collection, then Polish

The pull request stays one commit (root `CLAUDE.md`).
