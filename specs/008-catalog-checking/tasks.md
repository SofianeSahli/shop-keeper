---

description: "Task list for spec 008, catalog checking"
---

# Tasks: Catalog Checking

**Input**: Design documents from `specs/008-catalog-checking/`

**Prerequisites**: plan.md, spec.md (with the 2026-11-16 and 2026-11-19 clarifications), research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Required. FR-036 asks for tests of every endpoint (success, validation, signed-out access, no shop, another shop) and of every command (success, every refusal, no change on failure), and the backend guide asks for a test of every business rule.

**Organization**: Tasks are grouped by user story so each story can be built and checked on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: The user story the task belongs to (US1 to US7)
- Every path is relative to `shop-keeper-backend/` unless it starts with `specs/`

## Path Conventions

- All code is in the Catalog module: `app/Modules/Catalog/`. No other module changes (no operator role, no shop data in the queue)
- Run every command in the container: `docker compose exec app <command>`
- Follow `shop-keeper-backend/CLAUDE.md` and the root `CLAUDE.md`:
  - thin controllers and thin commands (parse, call one action, print);
  - one public `handle` per action;
  - no call inside an argument;
  - no comments, and named constants instead of magic values;
  - run `vendor/bin/pint` before a task is done;
  - never edit a 007 migration; add new ones.
- Commands follow research R15: every check before the first write, one transaction per change inside the action, `Nothing changed. <reason>` and `FAILURE` on any refusal, and no shop or merchant data in the output

---

## Phase 1: Setup

- [ ] T001 Create the empty brands file `app/Modules/Catalog/database/catalog/brands.json` (`[]`) and the constant `LoadCatalogCommand::DEFAULT_BRANDS_FILE` pointing to it

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The new catalog columns, brands, change records and shared support classes that every story uses

**⚠️ CRITICAL**: No user story starts before this phase is done, and the whole 007 suite still passes at its end

- [ ] T002 Add migration `app/Modules/Catalog/database/migrations/2026_11_19_000001_add_checking_columns_to_global_products_table.php` with these columns and index; `barcode` stays as in 007 (one at most):
  - `merged_into_id`: FK global_products, null;
  - `format_quantity`: unsigned int, null;
  - `format_unit`: string(5), null;
  - `linked_shops_count`: unsigned int, default 0, filled from the shop products linked to each product;
  - `checked_at`: timestamp, null;
  - index (`status`, `linked_shops_count`, `created_at`, `id`).
- [ ] T003 Add migration `app/Modules/Catalog/database/migrations/2026_11_19_000002_create_brands_table.php`, then add `brand_id` (FK brands, restrict delete, null) to `global_products`. The `brands` table has:
  - `id`;
  - `key`: string(100), unique;
  - `name`: string(80);
  - `compared`: string(255) `utf8mb4_bin`, unique;
  - `spellings`: JSON;
  - timestamps.
- [ ] T004 Add migration `app/Modules/Catalog/database/migrations/2026_11_19_000003_create_catalog_changes_table.php` with:
  - `id`;
  - `source`: string(30);
  - `action`: string(20);
  - `global_product_id`: FK global_products, null;
  - `brand_id`: FK brands, null;
  - `details`: JSON;
  - `created_at`.
- [ ] T005 [P] Add `CatalogStatus::Merged`, `CatalogTermKind::Brand`, and the enums `FormatUnit` (`g`, `kg`, `ml`, `l`, `piece`) and `CatalogChangeAction` (`check`, `correct`, `merge`, `brand_create`, `brand_rename`) in `app/Modules/Catalog/Models/`
- [ ] T006 [P] Model changes in `app/Modules/Catalog/Models/` and the factories:
  - Create `Brand` (`spellings` cast to array) and `CatalogChange` (no `updated_at`), with a `BrandFactory`.
  - Add `brand()`, `mergedInto()`, the format casts and `isMerged()` to `GlobalProduct`.
  - Add `merged()` and `withFormat()` states to `GlobalProductFactory`.
- [ ] T007 Create `App\Modules\Catalog\Actions\RecordCatalogChange` in `app/Modules/Catalog/Actions/RecordCatalogChange.php`, writing one `catalog_changes` row with its source command, action, ids and counts only
- [ ] T008 Make `CreateShopProduct` increment `linked_shops_count` of the linked catalog product in its transaction. Add `CatalogScenario::assertLinkedShopsCountsAreExact()` in `app/Modules/Catalog/Tests/Support/CatalogScenario.php`, which compares every count with a fresh count, and call it in `CreateProductByBarcodeTest` and `CreateProductFromCatalogTest`
- [ ] T009 Extend `CatalogProductResource` in `app/Modules/Catalog/Controllers/Resources/CatalogProductResource.php` with `brand` (`{ id, name }` or null), `format` (`{ quantity, unit }` or null) and `merged_into_id`, keeping 007's `barcode`; update the 007 tests that assert the exact CatalogProduct shape
- [ ] T010 [P] Create the support classes in `app/Modules/Catalog/Support/`:
  - `Format`: built from `{ quantity, unit }`; a whole quantity from 1 to 1 000 000 and a `FormatUnit`; otherwise `format.invalid`.
  - `InvalidCommandInput`: a reason code for a bad command option or argument.
  - `CatalogMergeError`: a reason code for a refused merge.

**Checkpoint**: migrations run on a copy of the 007 data; the 007 suite passes

---

## Phase 3: User Story 1 - See what is waiting to be checked (Priority: P1) 🎯 MVP

**Goal**: A server command lists pending products, most used first, with the names shops gave them and no shop data

**Independent Test**: Three shops add the same unknown barcode with different names, one shop another; `catalog:pending` shows the first with 3 shops and the three names

- [ ] T011 [P] [US1] Write command tests in `app/Modules/Catalog/Tests/PendingCatalogCommandTest.php` for `catalog:pending`:
  - **Success**:
    - order by `linked_shops_count` desc, then `created_at` asc;
    - `--limit` and `--cursor` pages with no overlap, the last page printing no next-page line;
    - each block shows id, barcode, shop count, first added date, typed name and language, and the different names shops gave (compared as in 007), at most 20, oldest link first;
    - checked and merged products are absent;
    - an empty queue prints `No product waiting to be checked.`;
    - the output contains no shop name, city or id and no owner name, email or phone (FR-003).
  - **Refusals**: `--limit=0`, `--limit=101` and `--limit=abc` give `limit.invalid`, and `--cursor=nonsense` gives `cursor.invalid`; each exits non-zero and prints no queue.
- [ ] T012 [US1] Create `PendingEntry` in `app/Modules/Catalog/Support/PendingEntry.php` and `App\Modules\Catalog\Actions\ListPendingProducts` in `app/Modules/Catalog/Actions/ListPendingProducts.php`. The action returns a cursor page over pending products, using one query for the names of the page's linked shop products, at most `MAX_NAMES_PER_ENTRY = 20` each, oldest first
- [ ] T013 [US1] Create `PendingCatalogCommand` (`catalog:pending {--limit=50} {--cursor=}`) in `app/Modules/Catalog/Console/PendingCatalogCommand.php`, which validates its options with `InvalidCommandInput` and prints the page as in research R4; register it in `app/Modules/Catalog/CatalogServiceProvider.php`

**Checkpoint**: The queue can be read on the server

---

## Phase 4: User Story 2 - Check a pending product (Priority: P1)

**Goal**: A catalog file entry with a pending barcode checks the product; a changed entry corrects it

**Independent Test**: Add an entry for a pending barcode and load the file; a shop that never had it sees the three names; linked shops keep their prices

- [ ] T014 [P] [US2] Write command tests in `app/Modules/Catalog/Tests/CheckThroughCatalogFileTest.php` for `catalog:load`:
  - **Check**:
    - an entry with a pending barcode makes the product `verified` with the same id, barcode and shop links;
    - `pending_name` and `pending_language` are cleared and `checked_at` is set;
    - one `check` change record with source `catalog:load` and no typed name;
    - a shop lookup afterwards returns the names (SC-002);
    - linked shops' prices are unchanged (SC-003).
  - **Correction**: changing an entry's names, spellings or format and loading again gives a `correct` record, and terms are rebuilt so a new spelling is found by suggestions.
  - **Repeat load**: loading the same file again changes nothing and adds no record (SC-006).
  - **Refusals**: each of `key.invalid`, `key.reserved`, `key.taken`, `key.merged`, `name.missing`, `name.too_long`, `barcode.taken`, `format.invalid` (quantity 0, 1.5, unknown unit, one field missing) and `brand.unknown`:
    - prints `Catalog not loaded, nothing changed. entry <n> (<key>): <reason>`;
    - exits non-zero;
    - leaves every catalog table and every shop product as before.
  - **Output**: the counts, and no typed or shop names.
- [ ] T015 [US2] Extend `CatalogFileEntry` in `app/Modules/Catalog/Support/CatalogFileEntry.php` with an optional `brand` key and an optional `Format`, and refuse keys starting with `products.barcode_` (`key.reserved`)
- [ ] T016 [US2] Update `LoadCatalogFile`:
  - read and lock its targets inside its transaction (`lockForUpdate`, id order), so a load and a merge never mix (FR-011);
  - refuse an entry whose key is that of a merged product (`key.merged`);
  - resolve brand keys against stored brands (`brand.unknown`);
  - set `brand_id` and the format, and set `checked_at` when a pending product becomes `verified`;
  - record a `check` or `correct` change per changed product through `RecordCatalogChange`;
  - return the counts in `CatalogLoadResult`.
- [ ] T017 [US2] Make `LoadCatalogCommand` print the new summary (research R5), keeping 007's refusal message

**Checkpoint**: Pending products can be checked and corrected through the catalog file

---

## Phase 5: User Story 3 - Merge two catalog products that are the same (Priority: P1)

**Goal**: A server command merges one catalog product into another; shops and the barcode follow; the merged one says where it went

**Independent Test**: Shop A on pending X, shop B on checked Y without a barcode; `catalog:merge X Y --yes`; X's barcode finds Y; shop A is linked to Y

- [ ] T018 [P] [US3] Write command tests in `app/Modules/Catalog/Tests/MergeCatalogCommandTest.php` for `catalog:merge`:
  - **Success**:
    - the source's barcode moves, and a lookup of it returns the target (SC-004);
    - shops on the source only are relinked;
    - a shop on both keeps its target product linked, while its source product loses the link and keeps its name, prices and id (FR-014);
    - the source becomes `merged` with `merged_into_id`, no barcode and no terms, and is never suggested;
    - both `linked_shops_count` are exact;
    - one `merge` change record with source `catalog:merge` and the counts;
    - the printed summary has counts only;
    - answering yes to the confirmation merges.
  - **Refusals**: each of `merge.same_product`, `catalog_product.merged` (merged source, merged target), `merge.checked_into_pending`, `merge.both_have_barcodes`, `catalog_product.not_found` (either id), `id.invalid` (`abc`, `0`), and `merge.cancelled` (answer no, or no `--yes` in a non-interactive run):
    - prints `Nothing changed. <reason>`;
    - exits non-zero;
    - leaves both products, their terms and every shop product as before.
- [ ] T019 [P] [US3] Write feature tests in `app/Modules/Catalog/Tests/ReadCatalogProductTest.php` for `GET /api/v1/catalog/products/{catalog_product}`:
  - a checked product;
  - a pending product (no names);
  - a merged product (`merged_into_id`);
  - an unknown id gives `catalog_product.not_found`;
  - `auth.unauthenticated`;
  - `shop.required`.

  In `CreateProductFromCatalogTest`, adding a merged product gives `catalog_product_id.merged`.
- [ ] T020 [US3] Create `MergeResult` in `app/Modules/Catalog/Support/MergeResult.php` and `App\Modules\Catalog\Actions\MergeCatalogProducts` in `app/Modules/Catalog/Actions/MergeCatalogProducts.php`, following research R6, all in one transaction:
  - lock both rows in id order;
  - apply the refusals as `CatalogMergeError`;
  - clear the barcode on the source, then set it on the target;
  - remove the links of shops on both from their source product, and move the other links;
  - delete the source's terms, and set the status and `merged_into_id`;
  - recompute both counts and record the change.
- [ ] T021 [US3] Create `MergeCatalogCommand` (`catalog:merge {from} {into} {--yes}`) in `app/Modules/Catalog/Console/MergeCatalogCommand.php`: validate the ids, show both products (id, key, barcode, status, linked shops), ask for confirmation unless `--yes`, call the action and print the summary. Register it in `app/Modules/Catalog/CatalogServiceProvider.php`
- [ ] T022 [US3] Create the shop-side `CatalogProductController::show` in `app/Modules/Catalog/Controllers/CatalogProductController.php` and its route `GET catalog/products/{catalog_product}` in the shop group; make `CreateShopProduct` answer `catalog_product_id.merged` for a merged product

**Checkpoint**: Duplicates can be merged; every catalog change goes through a server command

---

## Phase 6: User Story 4 - A product with a barcode carries the catalog's name (Priority: P1)

**Goal**: Shop products with a checked barcode carry the catalog name in their language and follow catalog changes; every other product keeps the merchant's name

**Independent Test**: Add a checked barcode in Arabic and see the Arabic catalog name; a rename is refused; a pending product renamed by the merchant takes the catalog name once checked by a file load; a correction reaches the shop

- [ ] T023 [US4] Add migration `app/Modules/Catalog/database/migrations/2026_11_19_000004_add_catalog_name_columns_to_shop_products_table.php`:
  - `name_language`: char(2), null, set to `fr` on existing rows;
  - `name_from_catalog`: boolean, default false;
  - `identity_key` made nullable (the unique index `(shop_id, identity_key)` stays);
  - then, in plain SQL, on rows linked to a checked catalog product with a barcode, set `name_from_catalog`, the French catalog name, its compared form and `identity_key = null` (research R14; no action is called from the migration).
- [ ] T024 [P] [US4] Write feature tests in `app/Modules/Catalog/Tests/CatalogNamedProductsTest.php`:
  - a checked barcode with `language: ar` gives the Arabic catalog name and `name_from_catalog: true`;
  - with `name` too it gives `name.prohibited`, and without `language` it gives `language.required`;
  - the same for the `catalog_product_id` of a checked barcoded product;
  - a rename gives 409 `product.name_from_catalog`, and a price change works;
  - a pending barcode needs `name` and `language`, keeps `name_language`, and can be renamed while pending;
  - a shop with its own product named like the catalog product can still add the barcode (FR-028);
  - a loose catalog product (no barcode) keeps the merchant's name and stays renamable (FR-026).
- [ ] T025 [P] [US4] Write tests in `app/Modules/Catalog/Tests/CatalogNameSyncTest.php`:
  - after a check by `catalog:load`, every linked shop product carries the catalog name in its own `name_language` (fr, ar and en shops in one test), with `identity_key` null;
  - after a correction by `catalog:load`, and after `catalog:merge` into a checked barcoded product, linked shops follow;
  - a pending barcode merged into a checked product without a barcode: the moved shop products take the catalog name, and the products already linked keep their merchants' names (research R14, finding I2);
  - names merchants chose for loose products never change;
  - prices never change (SC-003).
- [ ] T026 [US4] Add `name_language`, `name_from_catalog` and the nullable `identity_key` handling to `ShopProduct`: the `saving` hook leaves `identity_key` null when `name_from_catalog` is true. Add `name_language` and `name_from_catalog` to `ShopProductResource`
- [ ] T027 [US4] Create `App\Modules\Catalog\Actions\SyncCatalogNames` in `app/Modules/Catalog/Actions/SyncCatalogNames.php`. For a checked catalog product with a barcode, it runs one `UPDATE` per language on its linked shop products whose `name_from_catalog` is true, setting `name`, `compared_name` and `identity_key = null`. Its callers, each inside its own transaction:
  - `LoadCatalogFile` first sets `name_from_catalog` on every product linked to a pending product it checks in place, then calls it;
  - `MergeCatalogProducts` first sets it only on the products it moves, when the source had the barcode, then calls it.

  The load and merge summaries print the number of names synced.
- [ ] T028 [US4] Update the shop-side rules, and the 007 tests that expected an own name on a checked barcode:
  - `CreateShopProduct`:
    - for a checked barcoded link: `language` required, `name` refused with `name.prohibited`, `name_from_catalog` true, the name taken from the catalog, no name-taken check;
    - for a pending link: keep `language` as `name_language`.
  - `UpdateShopProduct`: `product.name_from_catalog` on a rename of a product carrying a catalog name.
  - `ShopProductNameRules::refuseIfNameTaken`: only products whose name the merchant chose.

**Checkpoint**: Barcoded products carry the catalog name everywhere; every P1 story works

---

## Phase 7: User Story 6 - Stop selling a product (Priority: P2)

**Goal**: A merchant removes a product from sale and puts it back; it is hidden, never deleted

**Independent Test**: Remove a product; it is gone from the list and suggestions; scanning its barcode points to it; put it back with the same id, name and prices

- [ ] T029 [US6] Add migration `app/Modules/Catalog/database/migrations/2026_11_19_000005_add_removed_at_to_shop_products_table.php`: `removed_at` timestamp, null; index (`shop_id`, `removed_at`, `compared_name`, `identity_key`); the 007 unique indexes stay as they are
- [ ] T030 [P] [US6] Write feature tests in `app/Modules/Catalog/Tests/RemoveProductFromSaleTest.php` for `POST /api/v1/products/{product}/remove` and `/restore`:
  - remove sets `removed_at` and answers the ShopProduct;
  - the product is absent from `GET /products`, and present in `GET /products?removed=true` and only there;
  - reading it by id and the barcode lookup show `removed_at`;
  - restore clears it, keeping the same id, name, prices and catalog link (SC-008);
  - removing twice, or restoring a product on sale, changes nothing (FR-034);
  - `PATCH` on a removed product gives 409 `product.removed`;
  - the row is never deleted;
  - another shop's product gives `product.not_found`;
  - `?removed=maybe` gives `removed.invalid`;
  - `auth.unauthenticated`;
  - `shop.required`.
- [ ] T031 [P] [US6] Write feature tests in `app/Modules/Catalog/Tests/RemovedProductDuplicatesTest.php`:
  - adding the barcode, the catalog product or the compared name of a removed product gives 409 `product.removed` with `existing_product_id` (FR-032), also with `confirm_different`;
  - renaming another product to a removed product's name gives the same answer;
  - close-name matches leave removed products out;
  - suggestions leave out removed products without a catalog link, and show a catalog product whose shop product is removed with that product and its `removed_at` (FR-030);
  - a checked barcode whose catalog name matches a removed own product is added normally (FR-032, FR-028);
  - `linked_shops_count` drops on removal and comes back on restore, and `catalog:pending` stops showing the removed product's name, then shows it again (FR-035); checked with `CatalogScenario::assertLinkedShopsCountsAreExact()`;
  - a merge of a catalog product some shops removed keeps those products removed (spec Edge Cases).
- [ ] T032 [US6] Add `removed_at` and `isRemoved()` to `ShopProduct`, a `removed()` factory state, and `removed_at` to `ShopProductResource` and `ShopProductSummaryResource`
- [ ] T033 [US6] Create `App\Modules\Catalog\Actions\RemoveShopProduct` and `RestoreShopProduct` in `app/Modules/Catalog/Actions/`. Each locks the row, does nothing if it is already in that state, sets or clears `removed_at`, and decrements or increments the linked catalog product's `linked_shops_count` in the same transaction (research R13)
- [ ] T034 [US6] Apply the removed state to the existing rules:
  - `ShopProductNameRules` answers `product.removed` with `existing_product_id` when the matching product is removed, comparing names only between merchant-chosen names;
  - `UpdateShopProduct` refuses a removed product;
  - `FindCloseProducts` skips removed shop products;
  - `SuggestProducts` skips removed products without a catalog link, but keeps a removed shop product on its catalog product's suggestion;
  - `ListPendingProducts`, `MergeCatalogProducts` and the count migration count, and name, products on sale only.
- [ ] T035 [US6] Add `remove` and `restore` to `ShopProductController` (policy `update`), the `removed` boolean filter to `ListProductsRequest` and the list query, and the routes `POST products/{product}/remove` and `POST products/{product}/restore` to the shop group in `app/Modules/Catalog/routes.php`

---

## Phase 8: User Story 5 - Brands and formats (Priority: P2)

**Goal**: Shared brands kept in the brands file, formats on catalog products, brand search

**Independent Test**: Add "Délice" to the brands file, give two milk entries the brand and formats 1 l and 500 ml, load; a shop searching "délice" sees both

- [ ] T036 [P] [US5] Write command tests in `app/Modules/Catalog/Tests/BrandsFileTest.php` for `catalog:load` with `--brands`:
  - **Success**:
    - new brands are added with name and spellings, and a second load changes nothing (SC-006);
    - a renamed brand updates its name and the brand terms of its products;
    - `brand_create` and `brand_rename` change records with source `catalog:load`;
    - a brand left out of the file is kept;
    - the default brands file is used without `--brands`.
  - **Refusals**: each of `brands_file.invalid`, `brand.key.invalid`, `brand.key.duplicate`, `brand.name.missing`, `brand.name.too_long` (over 80), `brand.spelling.too_long`, `brand.name_taken` (`delice` when `Délice` exists, in the file or stored), and a catalog entry naming a key in neither place (`brand.unknown`):
    - prints the brand or entry position and the reason;
    - exits non-zero;
    - leaves brands, catalog products and terms as before.
- [ ] T037 [P] [US5] Write feature tests in `app/Modules/Catalog/Tests/BrandSearchTest.php`:
  - suggestions for a brand name or spelling return checked products of that brand (FR-021);
  - a renamed brand is found by its new name and no longer by the old one;
  - a shop's own product named like a brand is not held as close to every product of the brand;
  - lookups, suggestions, shop products and close matches carry `brand` and `format` (FR-020).
- [ ] T038 [US5] Create `App\Modules\Catalog\Actions\RebuildBrandTerms` in `app/Modules/Catalog/Actions/RebuildBrandTerms.php`, which replaces the `brand` term rows of the given products from their brand's name and spellings, and make `FindCloseProducts` ignore terms of kind `brand`
- [ ] T039 [US5] Load the brands file with the catalog file:
  - Create `CatalogBrandEntry` in `app/Modules/Catalog/Support/CatalogBrandEntry.php`, validating the brand file rules of data-model.md.
  - Make `LoadCatalogFile` read and check the brands file before any write, then, in the same transaction:
    - add or update brands by key;
    - rebuild brand terms on a rename or a product's brand change;
    - record `brand_create` and `brand_rename`.
  - Add `--brands=` to `LoadCatalogCommand`, defaulting to `DEFAULT_BRANDS_FILE`.

**Checkpoint**: Brands and formats show everywhere shops see catalog products

---

## Phase 9: User Story 7 - Try it with standard tools (Priority: P3)

- [ ] T040 [US7] Add the new merchant requests to folder 5 of `postman/shop-keeper.postman_collection.json` (research R12):
  - read a catalog product by id;
  - try to rename a product added by a checked barcode (`product.name_from_catalog`);
  - remove a product from sale;
  - list removed products;
  - try to add it again (`product.removed`);
  - put it back.

  The owner runs it by hand.
- [ ] T041 [US7] Make sure `tests/Feature/Shared/ApiCollectionTest.php` passes: every new route, including `{{catalog_product_id}}` and the product remove and restore paths, has its request

---

## Phase 10: Polish & Cross-Cutting Concerns

- [ ] T042 [P] Write `app/Modules/Catalog/Tests/NoCatalogWriteRoutesTest.php` (SC-005, FR-001):
  - no API route sits under an `operator` prefix;
  - the only Catalog routes that accept POST, PATCH, PUT or DELETE are the shop product routes behind `auth:sanctum` and `shop`.
- [ ] T043 [P] Extend `tests/Feature/Shared/NoSecretsInLogsTest.php` and add `app/Modules/Catalog/Tests/TypedNamePrivacyTest.php` (SC-009):
  - after a check and a merge, no shop-facing answer, log or change record contains a name typed by another shop;
  - no command output contains a shop's name, city or id or an owner's name, email or phone;
  - a removed product never appears in another shop's answers.
- [ ] T044 [P] Update the docs:
  - `specs/007-catalog-products/contracts/api.md`:
    - CatalogProduct gains `brand`, `format`, `merged_into_id` and the `merged` status;
    - ShopProduct gains `removed_at`, `name_language` and `name_from_catalog`;
    - a checked barcode gives the catalog name and refuses `name`;
    - `GET /products` lists products on sale;
    - see 008 for the rest.
  - `shop-keeper-backend/README.md`: `catalog:pending`, `catalog:merge`, the brands file and the check-by-file flow.
  - The root `CLAUDE.md` focus line.
- [ ] T045 Check that `specs/008-catalog-checking/contracts/api.md` matches the delivered routes, shapes, codes and command outputs, and fix the contract or the code where they differ
- [ ] T046 Write `app/Modules/Catalog/Tests/CatalogVolumeTest.php`, in its own Pest group `volume` excluded from the default run:
  - seed 10 000 pending products and a catalog product linked to 1 000 shops;
  - on the Docker setup, assert that `ListPendingProducts` answers the first page in under 200 ms and that the merge of that product takes under 2 s (plan Performance Goals, finding G1).
- [ ] T047 Run `docker compose exec app vendor/bin/pint` and `docker compose exec app php artisan test`; both must pass
- [ ] T048 Walk through `specs/008-catalog-checking/quickstart.md` scenarios 1 to 13 (catalog commands and files) by hand, and leave the Postman run to the owner

---

## Dependencies & Execution Order

- **Setup (Phase 1)**: none
- **Foundational (Phase 2)**: blocks every story; ends with the 007 suite green
- **US1, US2, US3 (Phases 3 to 5)**: after Foundational; independent of each other (factories provide checked, pending and merged products). US2's `key.merged` test uses a factory `merged()` product, not the merge command
- **US4 (Phase 6)**: after US2 and US3 (it hooks the name sync into the load and the merge)
- **US6 (Phase 7)**: after Foundational (the counter, T008); its queue test needs US1 and its merge test needs US3
- **US5 (Phase 8)**: after US2 (entries name brands by key)
- **US7 (Phase 9)**: after every endpoint exists
- **Polish (Phase 10)**: last

### Parallel Opportunities

- T005, T006 and T010 together; each story's test files are marked [P]
- US1, US2, US3 and US6 can be built by different people once Phase 2 is done

## Parallel Example: P1 stories

```bash
Task: "Write command tests in app/Modules/Catalog/Tests/PendingCatalogCommandTest.php"
Task: "Write command tests in app/Modules/Catalog/Tests/CheckThroughCatalogFileTest.php"
Task: "Write command tests in app/Modules/Catalog/Tests/MergeCatalogCommandTest.php"
```

## Implementation Strategy

### MVP First

1. Phases 1 and 2
2. US1 then US2: the queue and the check by file, which is what this spec is for
3. Stop and check: a pending product can be found and checked, and shops see its names

### Incremental Delivery

1. US1 → queue command
2. US2 → check and correct through the catalog file
3. US3 → merge command
4. US4 → barcoded products carry the catalog name
5. US6 → merchants remove products from sale and put them back
6. US5 → brands file and formats
7. US7 → collection, then Polish

The pull request stays one commit (root `CLAUDE.md`).
