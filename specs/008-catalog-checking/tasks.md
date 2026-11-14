---

description: "Task list for spec 008, catalog checking"
---

# Tasks: Catalog Checking

**Input**: Design documents from `specs/008-catalog-checking/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Required. FR-023 asks for success, validation, signed-out and non-operator tests on every endpoint, and the backend guide asks for a test of every business rule.

**Organization**: Tasks are grouped by user story so each story can be built and checked on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: The user story the task belongs to (US1 to US7)
- Every path is relative to `shop-keeper-backend/` unless it starts with `specs/`

## Path Conventions

- Catalog code: `app/Modules/Catalog/`; role code: `app/Modules/Identity/`
- Run every command in the container: `docker compose exec app <command>`
- Follow `shop-keeper-backend/CLAUDE.md`: thin controllers, one public `handle` per action, no comments, named constants, `vendor/bin/pint` before a task is done. Never edit a 007 migration; add new ones

---

## Phase 1: Setup

- [ ] T001 Add an empty operator route group to `app/Modules/Catalog/routes.php`: prefix `operator/catalog`, middleware `auth:sanctum` then `operator` (the alias comes in T004)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The operator role, several barcodes per product, the shared-catalog columns and change records that every story uses

**⚠️ CRITICAL**: No user story starts before this phase is done, and the whole 007 suite passes again at its end

### Operator role

- [ ] T002 Add migration `app/Modules/Identity/database/migrations/2026_11_14_000001_add_operator_since_to_users_table.php`: `operator_since` timestamp, null
- [ ] T003 [P] Add `isOperator(): bool` and the `operator_since` cast to `app/Modules/Identity/Models/User.php`, and an `operator()` state to `app/Modules/Identity/database/factories/UserFactory.php`
- [ ] T004 Create `App\Modules\Identity\Controllers\Middleware\RequireOperator` in `app/Modules/Identity/Controllers/Middleware/RequireOperator.php`, throwing `ApiException('operator.required', 403)` when the signed-in account is not an operator; register the alias `operator` in `app/Modules/Identity/IdentityServiceProvider.php`
- [ ] T005 Write feature tests in `app/Modules/Identity/Tests/RequireOperatorTest.php` on a test-only route: an operator passes; a merchant gets 403 `operator.required`; no token gets `auth.unauthenticated`; an operator who owns a shop still passes the `shop` middleware on merchant routes (FR-002)

### Several barcodes (research R2)

- [ ] T006 Add migration `app/Modules/Catalog/database/migrations/2026_11_14_000001_create_global_product_barcodes_table.php`: `id`; `global_product_id` FK global_products, cascade delete, indexed; `barcode` string(13), unique; timestamps; then copy every non-null `global_products.barcode` into it
- [ ] T007 Add migration `app/Modules/Catalog/database/migrations/2026_11_14_000002_drop_barcode_from_global_products_table.php`, dropping the unique index and the column (its `down` restores them from the barcodes table, first barcode per product)
- [ ] T008 Create model `GlobalProductBarcode` in `app/Modules/Catalog/Models/GlobalProductBarcode.php`; add `barcodes()` and `hasBarcode()` to `GlobalProduct`, remove `barcode` from its fillable; update `GlobalProductFactory::withBarcode()` and `pending()` to create barcode rows
- [ ] T009 Update `LookUpBarcode`, `FindOrCreatePendingProduct` (product and barcode row in one savepoint; a unique violation on the barcode row reads the winner's product), `CreateShopProduct::catalogProductOfBarcode` and `UpdateShopProduct` (never held when the linked product `hasBarcode()`) in `app/Modules/Catalog/Actions/`
- [ ] T010 Update `LoadCatalogFile` in `app/Modules/Catalog/Actions/LoadCatalogFile.php` for barcode rows: an entry barcode not yet on its key's product is added (no more `barcode.changed`); a barcode held by another checked product still stops the load (`barcode.taken`); a pending barcode with a new key is checked in place as before
- [ ] T011 Update `CatalogProductResource` in `app/Modules/Catalog/Controllers/Resources/CatalogProductResource.php` to return `barcodes` (sorted list) instead of `barcode`, and update every 007 test that asserted `barcode` in `app/Modules/Catalog/Tests/`; the full suite must pass

### Shared catalog columns and records

- [ ] T012 Add migration `app/Modules/Catalog/database/migrations/2026_11_14_000003_add_checking_columns_to_global_products_table.php`: `merged_into_id` FK global_products, null; `format_quantity` unsigned int, null; `format_unit` string(5), null; `linked_shops_count` unsigned int, default 0, filled from `shop_products`; `checked_by` FK users, null on delete, null; `checked_at` timestamp, null; index (`status`, `linked_shops_count`, `created_at`, `id`)
- [ ] T013 Add migration `app/Modules/Catalog/database/migrations/2026_11_14_000004_create_brands_table.php`: `id`; `name` string(80); `compared` string(255) `utf8mb4_bin`, unique; `spellings` JSON; timestamps; then add `brand_id` FK brands, restrict delete, null, to `global_products`
- [ ] T014 Add migration `app/Modules/Catalog/database/migrations/2026_11_14_000005_create_catalog_changes_table.php`: `id`; `operator_id` FK users, null on delete, null; `action` string(20); `global_product_id` FK global_products, null; `brand_id` FK brands, null; `details` JSON; `created_at`
- [ ] T015 [P] Add `CatalogStatus::Merged`, `CatalogTermKind::Brand`, enums `FormatUnit` (`g`, `kg`, `ml`, `l`, `piece`) and `CatalogChangeAction` (`check`, `correct`, `merge`, `brand_create`, `brand_rename`, `import`, `file_load`) in `app/Modules/Catalog/Models/`
- [ ] T016 [P] Create models `Brand` (`spellings` cast to array) and `CatalogChange` (no `updated_at`) in `app/Modules/Catalog/Models/`, with a `BrandFactory`; add `brand()`, `mergedInto()`, the format casts and `isMerged()` to `GlobalProduct`
- [ ] T017 Create `App\Modules\Catalog\Actions\RecordCatalogChange` in `app/Modules/Catalog/Actions/RecordCatalogChange.php`, writing one `catalog_changes` row with ids and counts only
- [ ] T018 Make `CreateShopProduct` increment `linked_shops_count` of the linked catalog product in its transaction, and add a test helper `CatalogScenario::assertLinkedShopsCountsAreExact()` in `app/Modules/Catalog/Tests/Support/CatalogScenario.php` that compares every count with a fresh count; call it in `CreateProductByBarcodeTest` and `CreateProductFromCatalogTest`
- [ ] T019 Extend `CatalogProductResource` with `brand` (`{ id, name }` or null), `format` (`{ quantity, unit }` or null) and `merged_into_id`, matching `specs/008-catalog-checking/contracts/api.md`
- [ ] T020 Create `CatalogProductPolicy` and `BrandPolicy` in `app/Modules/Catalog/Policies/` (`manage`: the user is an operator), registered in `app/Modules/Catalog/CatalogServiceProvider.php`
- [ ] T021 Create the shared validation pieces in `app/Modules/Catalog/Controllers/Requests/Rules/`: `CatalogKey` (form, length, `key.reserved` for the `products.barcode_` prefix; `key.taken` checked against other products), `CatalogNames` (fr, ar, en, 1 to 120 characters), `CatalogSpellings`, `CatalogFormat` (quantity 1 to 1 000 000, unit in `FormatUnit`)

**Checkpoint**: migrations run on a copy of the 007 data; the 007 suite passes with `barcodes`; the role and middleware work

---

## Phase 3: User Story 1 - See what is waiting to be checked (Priority: P1) 🎯 MVP

**Goal**: The operator lists pending products, most used first, with what shops typed

**Independent Test**: Three shops add the same unknown barcode with different names, one shop another; the queue shows the first with 3 shops and three names

- [ ] T022 [P] [US1] Write feature tests in `app/Modules/Catalog/Tests/PendingQueueTest.php` for `GET /api/v1/operator/catalog/pending`: order by `linked_shops_count` desc, then `created_at` asc; cursor pages with no overlap; each entry in the PendingEntry shape with `typed_name`, `typed_language`, `linked_shops_count`, distinct `shop_names` at most 20, most common first; checked and merged products absent; `per_page.invalid`; `auth.unauthenticated`; `operator.required` for a merchant
- [ ] T023 [US1] Create `App\Modules\Catalog\Actions\ListPendingProducts` in `app/Modules/Catalog/Actions/ListPendingProducts.php` (cursor page over pending products, then one query for the distinct shop names of the page, `MAX_SHOP_NAMES = 20`)
- [ ] T024 [US1] Create `PendingEntryResource` in `app/Modules/Catalog/Controllers/Resources/PendingEntryResource.php` and `Operator/PendingProductController` in `app/Modules/Catalog/Controllers/Operator/PendingProductController.php`; add `GET pending` to the operator group in `app/Modules/Catalog/routes.php`

**Checkpoint**: The queue works for operators only

---

## Phase 4: User Story 2 - Check a pending product (Priority: P1)

**Goal**: The operator gives a pending product its key and names; corrections of checked products

**Independent Test**: Check a pending product; a shop that never had it sees the three names; linked shops keep their own names

- [ ] T025 [P] [US2] Write feature tests in `app/Modules/Catalog/Tests/CheckCatalogProductTest.php` for `POST /api/v1/operator/catalog/products/{catalog_product}/check`: 200 `verified` with key, names, spellings, brand and format; same id and barcodes; `pending_name` and `pending_language` cleared; `checked_by` and `checked_at` set; a `check` change record with no typed name; a shop lookup afterwards returns the names (SC-002); linked shops' names and prices unchanged (SC-003); `key.invalid`, `key.reserved`, `key.taken`, `names.ar.required`, `names.fr.too_long`, `brand_id.unknown`, `format.unit.invalid` with nothing changed; a checked product gives `catalog_product.not_pending`; a second check after the first, simulated in the same test, gives `catalog_product.not_pending` (FR-010); unknown id `catalog_product.not_found`; `auth.unauthenticated`; `operator.required`
- [ ] T026 [P] [US2] Write feature tests in `app/Modules/Catalog/Tests/CorrectCatalogProductTest.php` for `PATCH /api/v1/operator/catalog/products/{catalog_product}`: partial changes of key, names, spellings, brand (including `null`) and format (including `null`); terms rebuilt so a new spelling is found by suggestions; shops' own names unchanged; a `correct` change record; a pending product gives `catalog_product.not_verified`, a merged one `catalog_product.merged`; the same validation codes as the check; `auth.unauthenticated`; `operator.required`
- [ ] T027 [US2] Create the value object `CatalogProductDetails` in `app/Modules/Catalog/Support/CatalogProductDetails.php` (key, names, spellings, brand id, format, each optional for a correction)
- [ ] T028 [US2] Create `App\Modules\Catalog\Actions\CheckCatalogProduct` in `app/Modules/Catalog/Actions/CheckCatalogProduct.php`: lock the row, refuse unless pending, apply details through `ReplaceCatalogNames`, set status, `checked_by`, `checked_at`, clear pending fields, rebuild brand terms, record the change; a unique violation on the key answers `key.taken`
- [ ] T029 [US2] Create `App\Modules\Catalog\Actions\CorrectCatalogProduct` in `app/Modules/Catalog/Actions/CorrectCatalogProduct.php` with the same steps for a checked product, changing only the fields sent
- [ ] T030 [US2] Create `CheckCatalogProductRequest` and `CorrectCatalogProductRequest` in `app/Modules/Catalog/Controllers/Requests/Operator/` using the T021 rules, and `Operator/CatalogProductController` (`check`, `update`) applying `CatalogProductPolicy::manage`; add the two routes to the operator group

**Checkpoint**: Pending products can be checked and corrected

---

## Phase 5: User Story 3 - Merge two catalog products that are the same (Priority: P1)

**Goal**: Merge one catalog product into another; shops and barcodes follow; the merged one says where it went

**Independent Test**: Shop A on pending X, shop B on checked Y; merge X into Y; X's barcode finds Y; shop A is linked to Y

- [ ] T031 [P] [US3] Write feature tests in `app/Modules/Catalog/Tests/MergeCatalogProductsTest.php` for `POST /api/v1/operator/catalog/products/{catalog_product}/merge`: barcodes move and look up the target (SC-004); shops on the source only are relinked; a shop on both keeps its target product linked and its source product loses the link with name, prices and id unchanged (FR-012); the source becomes `merged` with `merged_into_id`, no terms, never suggested; both `linked_shops_count` exact; pending into pending keeps the target pending; a `merge` change record with the counts; `merge.same_product`; `catalog_product.merged` for a merged source or target; `merge.checked_into_pending`; `into_id.required`, `into_id.unknown`; `auth.unauthenticated`; `operator.required`
- [ ] T032 [P] [US3] Write feature tests in `app/Modules/Catalog/Tests/ReadCatalogProductTest.php` for `GET /api/v1/catalog/products/{catalog_product}`: a checked, a pending (no names) and a merged product (`merged_into_id`); unknown id `catalog_product.not_found`; `auth.unauthenticated`; `shop.required`; and in `CreateProductFromCatalogTest`, adding a merged product gives `catalog_product_id.merged`
- [ ] T033 [US3] Create `App\Modules\Catalog\Actions\MergeCatalogProducts` in `app/Modules/Catalog/Actions/MergeCatalogProducts.php` following research R6: one transaction, both rows locked in id order, the refusals, barcode rows moved, links of shops on both removed from their source product, other links moved, source terms deleted, status and `merged_into_id` set, both counts recomputed, change recorded
- [ ] T034 [US3] Create `MergeCatalogProductsRequest` in `app/Modules/Catalog/Controllers/Requests/Operator/` and add `merge` to `Operator/CatalogProductController`; add the route to the operator group
- [ ] T035 [US3] Create the shop-side `CatalogProductController::show` in `app/Modules/Catalog/Controllers/CatalogProductController.php` and its route `GET catalog/products/{catalog_product}` in the shop group; make `CreateShopProduct` answer `catalog_product_id.merged` for a merged product

**Checkpoint**: Duplicates can be merged; every P1 story works

---

## Phase 6: User Story 6 - Operator accounts (Priority: P2)

**Goal**: The owner grants and revokes the operator role with a command

**Independent Test**: Grant, call the queue, revoke, call again

- [ ] T036 [P] [US6] Write command tests in `app/Modules/Identity/Tests/OperatorCommandsTest.php`: `operator:grant` sets `operator_since` and the next queue request passes; `operator:revoke` clears it and the next request gets `operator.required`; an unknown email exits non-zero and changes nothing; granting twice keeps the first date
- [ ] T037 [US6] Create `App\Modules\Identity\Actions\FindAccountIdByEmail` and the commands `GrantOperatorCommand` (`operator:grant {email}`) and `RevokeOperatorCommand` (`operator:revoke {email}`) in `app/Modules/Identity/Console/`, registered in `app/Modules/Identity/IdentityServiceProvider.php`; emails compared lowercase
- [ ] T038 [US6] Make `database/seeders/DatabaseSeeder.php` grant the operator role to `test@example.com` when the environment is not `production` (research R12)

---

## Phase 7: User Story 4 - Brands and formats (Priority: P2)

**Goal**: Shared brands, formats on catalog products, brand search

**Independent Test**: Create "Délice", give two milk products the brand and formats 1 l and 500 ml; a shop searching "délice" sees both

- [ ] T039 [P] [US4] Write feature tests in `app/Modules/Catalog/Tests/BrandsTest.php` for `GET`, `POST` and `PATCH /api/v1/operator/catalog/brands`: 201 with name and spellings; `brand.name_taken` for `delice` when `Délice` exists, on create and rename; `name.required`, `name.too_long` (over 80); list by name in cursor pages; a rename shows the new name on every product of the brand; `brand_create` and `brand_rename` change records; `brand.not_found`; `auth.unauthenticated`; `operator.required`
- [ ] T040 [P] [US4] Write feature tests in `app/Modules/Catalog/Tests/BrandSearchTest.php`: suggestions for a brand name or spelling return checked products of that brand (FR-019); a renamed brand is found by its new name and no longer by the old one; a shop's own product named like a brand is not held as close to every product of the brand; lookups, suggestions, shop products and close matches carry `brand` and `format` (FR-018)
- [ ] T041 [US4] Create `App\Modules\Catalog\Actions\RebuildBrandTerms` in `app/Modules/Catalog/Actions/RebuildBrandTerms.php` (replace the `brand` term rows of the given products from their brand's name and spellings), and make `FindCloseProducts` ignore terms of kind `brand`
- [ ] T042 [US4] Create `App\Modules\Catalog\Actions\CreateBrand` and `RenameBrand` in `app/Modules/Catalog/Actions/` (compared name unique; rename rebuilds the brand terms of its products in the same transaction; changes recorded), with `BrandResource`, form requests in `app/Modules/Catalog/Controllers/Requests/Operator/` and `Operator/BrandController`; add the three routes to the operator group

**Checkpoint**: Brands and formats show everywhere shops see catalog products

---

## Phase 8: User Story 5 - Import many catalog products at once (Priority: P3)

**Goal**: A comma-separated import through the same rules as the 007 catalog file

**Independent Test**: Import 20 lines with 2 pending barcodes; 18 added, 2 checked in place; a second import changes nothing

- [ ] T043 [P] [US5] Create fixtures in `app/Modules/Catalog/Tests/fixtures/`: `import-valid.csv` (20 lines, 2 barcodes made pending by the test, brands and formats) and one invalid file per refusal: bad barcode, missing Arabic name, bad key, reserved key, unknown unit, duplicate barcode
- [ ] T044 [US5] Write command tests in `app/Modules/Catalog/Tests/ImportCatalogCommandTest.php` for `catalog:import {file} --operator=<email>`: counts printed; pending barcodes checked in place with shops still linked; unknown brands created; a second import leaves the catalog identical (SC-006); each invalid file prints `line <n> (<key>): <reason>`, exits non-zero and changes nothing; an unknown operator email exits non-zero; one `import` change record with the operator; and in `LoadCatalogCommandTest`, the 007 JSON file with `brand` and `format`, `--operator`, and the new "barcode added to its key" rule
- [ ] T045 [US5] Extend `CatalogFileEntry` in `app/Modules/Catalog/Support/CatalogFileEntry.php` with optional brand name and format, and the reserved-key rule; make `LoadCatalogFile` create missing brands, set brand and format, and record a `file_load` or `import` change
- [ ] T046 [US5] Create `CsvCatalogReader` in `app/Modules/Catalog/Support/CsvCatalogReader.php` (UTF-8, header line checked, `|`-separated spellings, line numbers kept for errors) and `ImportCatalogCommand` in `app/Modules/Catalog/Console/ImportCatalogCommand.php`; add `--operator` to `LoadCatalogCommand`; register both in `app/Modules/Catalog/CatalogServiceProvider.php`

---

## Phase 9: User Story 7 - Try it with standard tools (Priority: P3)

- [ ] T047 [US7] Add the folder "6. Operator catalog" to `postman/shop-keeper.postman_collection.json` (research R12): sign in with `operator_email` and `operator_password` and store `operator_token`; add a pending barcode from the folder 2 shop; list the queue; create a brand; check the product; look it up from the shop; add a second pending barcode and merge it into the first; read the merged product; correct the brand of the checked product; the operator requests use `{{operator_token}}`. Update 007's folder 5 tests for `barcodes`. The owner runs it by hand
- [ ] T048 [US7] Make sure `tests/Feature/Shared/ApiCollectionTest.php` passes: every new route, including `{{catalog_product_id}}` and `{{brand_id}}` paths, has its request

---

## Phase 10: Polish & Cross-Cutting Concerns

- [ ] T049 [P] Extend `tests/Feature/Shared/NoSecretsInLogsTest.php` and add `app/Modules/Catalog/Tests/TypedNamePrivacyTest.php`: after a check and a merge, no shop-facing answer and no log or change record contains a name typed by a shop
- [ ] T050 [P] Update `specs/007-catalog-products/contracts/api.md` (CatalogProduct now has `barcodes`, `brand`, `format`, `merged_into_id`; see 008), `shop-keeper-backend/CLAUDE.md` (who verifies pending products is decided: the operator queue, spec 008), `shop-keeper-backend/README.md` (operator commands, import, seeded operator) and the root `CLAUDE.md` focus line
- [ ] T051 Check that `specs/008-catalog-checking/contracts/api.md` matches the delivered routes, shapes and codes, and fix the contract or the code where they differ
- [ ] T052 Run `docker compose exec app vendor/bin/pint` and `docker compose exec app php artisan test`; both must pass
- [ ] T053 Walk through `specs/008-catalog-checking/quickstart.md` scenarios 11 to 14 (commands and change records) by hand, and leave the Postman run to the owner

---

## Dependencies & Execution Order

- **Setup (Phase 1)**: none
- **Foundational (Phase 2)**: blocks every story; T006 to T011 (barcodes) come before T012 onward and must end with the 007 suite green
- **US1, US2, US3 (Phases 3 to 5)**: after Foundational; US1 and US2 are independent; US3 needs US2 only for its "checked product" fixtures, which factories also provide
- **US6 (Phase 6)**: after Foundational; tests in earlier phases use the factory `operator()` state, so it can come at any time
- **US4 (Phase 7)**: after US2 (check and correct set brand and format)
- **US5 (Phase 8)**: after US4 (brands) and the T010 loader change
- **US7 (Phase 9)**: after every endpoint exists
- **Polish (Phase 10)**: last

### Parallel Opportunities

- T003 with T002; T015 and T016 together; each story's test files are marked [P]
- US1, US2 and US6 can be built by different people once Phase 2 is done

## Parallel Example: P1 stories

```bash
Task: "Write feature tests in app/Modules/Catalog/Tests/PendingQueueTest.php"
Task: "Write feature tests in app/Modules/Catalog/Tests/CheckCatalogProductTest.php"
Task: "Write feature tests in app/Modules/Catalog/Tests/MergeCatalogProductsTest.php"
```

## Implementation Strategy

### MVP First

1. Phases 1 and 2 (the 007 suite green on several barcodes)
2. US1 then US2: the queue and the check, which is what the owner decided this spec is for
3. Stop and check: a pending product can be found and checked, and shops see its names

### Incremental Delivery

1. US1 → queue
2. US2 → check and correct
3. US3 → merge
4. US6 → role commands and seeded operator
5. US4 → brands and formats
6. US5 → import
7. US7 → collection, then Polish

The pull request stays one commit (root `CLAUDE.md`).
