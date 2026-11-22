---

description: "Task list for spec 010, stock movements and low-stock warning"
---

# Tasks: Stock Movements and Low-Stock Warning

**Input**: Design documents from `specs/010-stock-movements/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Required. FR-020: success, validation, unauthenticated, no-shop and cross-shop tests on every endpoint, plus rule tests for stock sums, opening stock once, counts with movements in between, stock after each history entry, the low-stock list and repeated requests. Write each story's tests first and see them fail.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: US1 to US7 as in spec.md
- Paths are relative to `shop-keeper-backend/` unless they start with `specs/`

## Path Conventions

- Modules: `app/Modules/Inventory/`, `app/Modules/Catalog/`, `app/Modules/Transactions/`, each with `Actions/`, `Support/`, `Controllers/` (`Requests/`, `Resources/`), `Models/`, `database/`, `Tests/`, and `Contracts/` where plan.md lists one
- Run every command in the container: `docker compose exec app <command>`
- Follow `shop-keeper-backend/CLAUDE.md` and the root `CLAUDE.md`: thin controllers, one public `handle` per action, no comments, named constants, no call inside an argument, no module reads another module's tables, migrations of 007 and 009 never edited, `vendor/bin/pint` before a task is done

---

## Phase 1: Setup

**Purpose**: Give Inventory its routes and the kinds it needs

- [X] T001 In `app/Modules/Inventory/InventoryServiceProvider.php`, load `app/Modules/Inventory/routes.php` under `api/v1` with the `api` middleware, as `CatalogServiceProvider` does; create `routes.php` with one group using `auth:sanctum` then `RequireShop::ALIAS`, empty for now
- [X] T002 [P] In `app/Modules/Inventory/Models/StockMovementKind.php`, add `OpeningStock = 'opening_stock'`, `Count = 'count'`, `Loss = 'loss'`; leave `Purchase` and `PurchaseCancellation` unchanged
- [X] T003 [P] Create enums in `app/Modules/Inventory/Models/`: `StockAdjustmentKind` (`OpeningStock = 'opening_stock'`, `Count = 'count'`, `Loss = 'loss'`, method `movementKind(): StockMovementKind`) and `LossReason` (`Broken = 'broken'`, `Expired = 'expired'`, `Stolen = 'stolen'`, `OwnUse = 'own_use'`, `Other = 'other'`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: No user story starts before this phase is done

- [X] T004 Add migration `app/Modules/Inventory/database/migrations/2026_11_21_000010_create_stock_adjustments_table.php` exactly as data-model.md: `id` binary(16) primary (client UUID), `shop_id` FK shops restrict delete, `kind` string(20), `adjustment_date` date, `line_count` unsigned smallint, `created_by` FK users restrict delete, `created_at` timestamp; index `(shop_id, adjustment_date)`
- [X] T005 Add migration `app/Modules/Inventory/database/migrations/2026_11_21_000011_create_stock_adjustment_lines_table.php` exactly as data-model.md: `id` binary(16) primary, `stock_adjustment_id` binary(16) FK restrict delete, `position` unsigned smallint, `shop_product_id` binary(16) FK shop_products restrict delete, `quantity` signed int, `counted_quantity` unsigned int null, `shown_quantity` int null, `loss_reason` string(10) null, `note` string(255) null, `opening_product_id` binary(16) null, `created_at`; unique `(stock_adjustment_id, shop_product_id)`, `(stock_adjustment_id, position)`, `(opening_product_id)`; no `shop_id`
- [X] T006 Add migration `app/Modules/Inventory/database/migrations/2026_11_21_000012_add_sequence_to_stock_movements_table.php`: a raw `ALTER TABLE stock_movements ADD COLUMN sequence BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE` (research R8: Laravel's `autoIncrement()` would add a second primary key), then index `(shop_product_id, sequence)`; `down()` drops both
- [X] T007 [P] Create models `StockAdjustment` (no `updated_at`, `HasBinaryUuidKey`, `uuid()`, `scopeOfShop`, casts `kind` to `StockAdjustmentKind`, `lines()` ordered by `position`) and `StockAdjustmentLine` (no `updated_at`, casts `shop_product_id` with `BinaryUuid`, `loss_reason` to `LossReason`, integers) in `app/Modules/Inventory/Models/`; add the `sequence` integer cast to `StockMovement`
- [X] T008 [P] Create `Support/NewStockAdjustment` (`id`, `shopId`, `kind`, `adjustmentDate`, `recordedBy`, list of lines, `productIds()`) and `Support/NewStockAdjustmentLine` (`id`, `productId`, `quantity`, `countedQuantity`, `shownQuantity`, `lossReason`, `note`, `signedQuantity(StockAdjustmentKind)`: `+quantity` for opening stock, `counted − shown` for a count, `−quantity` for a loss) in `app/Modules/Inventory/`
- [X] T009 Create `Actions/RecordStockAdjustment` and `Actions/FindStockAdjustmentOfShop` in `app/Modules/Inventory/` (plan Design Notes): in one database transaction, `RecordWithSameId` on the adjustment id (same shop and kind answers it; another kind throws `id.taken`), refuse used line ids (`lines.N.id: id.taken`), check products with Catalog's `FindProductIdsOfShop` (`lines.N.product_id: product_id.unknown`), insert the adjustment first and catch a lost race as `RecordTransaction` does, insert the lines with their signed `quantity`, then `RecordStockMovements` with `kind->movementKind()` and one `NewStockMovement(productId, signedQuantity, lineId)` per line; returns `Created`
- [X] T010 Create `Controllers/Requests/AdjustmentFieldRules` in `app/Modules/Inventory/` with the common rules and messages of data-model.md: `id` (`id.required`, `id.invalid`), `adjustment_date` optional `Y-m-d` not after `ShopClock::todayAsDate()` (`adjustment_date.invalid`, `adjustment_date.in_future`), `lines` "1 to 200" (`lines.required`, `lines.too_many`, `line.invalid`), `lines.N.id` "required UUID, distinct" (`id.required`, `id.invalid`, `id.duplicate`), `lines.N.product_id` "required UUID ..., distinct" (`product_id.required`, `product_id.invalid`, `product_id.duplicate`); field errors keyed by line as in `RecordTransactionRequest`
- [X] T011 Create `StockAdjustmentResource` and `StockAdjustmentLineResource` in `app/Modules/Inventory/Controllers/Resources/` with the StockAdjustment shape of contracts/api.md (`recorded_by` from `created_by`; `counted_quantity`, `shown_quantity`, `reason`, `note` null when not of the kind), and `StockAdjustmentController` with a private `answer(Created)` giving 201 or 200
- [X] T012 [P] Create test helper `app/Modules/Inventory/Tests/Support/InventoryScenario.php`: a merchant with a shop and products (reuse `CatalogScenario`), body builders for opening stock, count and loss, `stockOf(productId)`, `sumOfMovements(productId)`

**Checkpoint**: Migrations run on top of 009 data (`php artisan migrate`), existing tests still pass, the action exists with no route yet

---

## Phase 3: User Story 1 - Set the stock I already have (P1) 🎯 MVP

**Independent Test**: opening stock of 30 and 12, read products, try again on the first (spec US1)

- [X] T013 [P] [US1] Write `app/Modules/Inventory/Tests/RecordOpeningStockTest.php`: several products at once, stock grows, `recorded_by`, default and past dates, `adjustment_date.in_future`; after purchases it adds; second opening stock with new ids gives `lines.N.product_id: product_id.opening_already_set` and records nothing; every field code of data-model.md for `quantity` ("JSON integer 1 to 999 999") with its line index; 201 lines `lines.too_many`; same product twice; unknown product; repeat with the same ids answers 200 and stock changes once; two requests with different ids racing on the same product: exactly one accepted; unauthenticated and no shop
- [X] T014 [US1] In `RecordStockAdjustment`, for `OpeningStock`: fill `opening_product_id` with the product id, refuse products that already have an opening line before inserting, and turn a unique violation on `opening_product_id` into the same field errors after re-reading (research R5)
- [X] T015 [US1] Create `RecordOpeningStockRequest` (common rules plus `lines.N.quantity` "JSON integer 1 to 999 999": `quantity.required`, `quantity.integer`, `quantity.too_low`, `quantity.too_high`; method `newStockAdjustment(shopId, accountId, ShopClock)`) and `StockAdjustmentController::openings`; add route `POST stock/openings`

**Checkpoint**: A merchant can start with real stock

---

## Phase 4: User Story 2 - Correct the stock after counting the shelves (P1)

**Independent Test**: stock 20, count 17 shown 20, read −3 and 17; count again equal (spec US2)

- [X] T016 [P] [US2] Write `app/Modules/Inventory/Tests/RecordCountTest.php`: `counted − shown` recorded, `counted_quantity` and `shown_quantity` answered; a count equal to the shown stock records 0 and keeps the stock; a purchase recorded between the shown stock and the count still counts (stock 20, purchase 5, count 17 shown 20 gives 22); shown stock below zero accepted; a count of 0 accepted; field codes `counted_quantity` ("JSON integer 0 to 999 999": `.required`, `.integer`, `.negative`, `.too_high`) and `shown_quantity` ("JSON integer −999 999 999 to 999 999 999": `.required`, `.integer`, `.too_low`, `.too_high`) with line index; several products at once; repeat answers 200; the id of an opening stock reused for a count gives `id.taken`
- [X] T017 [US2] Create `RecordCountRequest` (common rules plus the two fields above) and `StockAdjustmentController::counts`; add route `POST stock/counts`

---

## Phase 5: User Story 3 - Record a loss (P1)

**Independent Test**: stock 10, loss of 2 broken, read 8 and the reason (spec US3)

- [X] T018 [P] [US3] Write `app/Modules/Inventory/Tests/RecordLossTest.php`: stock goes down by the quantity, `reason` and trimmed `note` answered; a loss larger than the stock is accepted and the stock goes below zero; every reason of `LossReason`; `reason.required`, `reason.invalid` (`lost`), `note.invalid`, `note.too_long` (256 characters), `quantity` codes as in T013; several products at once, all or nothing; repeat answers 200
- [X] T019 [US3] Create `RecordLossRequest` (common rules plus `quantity` "JSON integer 1 to 999 999", `reason` in `broken`, `expired`, `stolen`, `own_use`, `other`, `note` "optional, nullable, at most 255 after trimming") and `StockAdjustmentController::losses`; add route `POST stock/losses`

**Checkpoint**: Every stock change of a small shop except sales can be recorded

---

## Phase 6: User Story 4 - See a product's stock history (P2)

**Independent Test**: opening stock, purchase, loss and count on one product; list its history (spec US4)

- [X] T020 [P] [US4] Write `app/Modules/Inventory/Tests/StockHistoryTest.php` with a fake `PurchaseLineSources`: newest first by `sequence`; `stock_after` right on every entry and on every page with `per_page` 2 (SC-004); the newest `stock_after` equals the product's `stock_quantity`; details by kind (`adjustment_id`, `counted_quantity`, `shown_quantity`, `reason`, `note`, `date`, `recorded_by`); purchase entries show `transaction_id`, the purchase date and its recorder; purchase cancellation entries show the cancellation day and the account that cancelled; a product with no movement gives an empty page; `per_page.invalid`, `cursor.invalid`; another shop's product and an unknown id give 404 `product.not_found`; query count does not grow with the page size
- [X] T021 [US4] Create the interface `app/Modules/Inventory/Contracts/PurchaseLineSources.php` (`describe(int $shopId, array $lineIds): array<string, PurchaseLineSource>`) and `Support/PurchaseLineSource` (`transactionId`, `transactionDate`, `recordedBy`, `cancelledBy`, `cancelledOn`)
- [X] T022 [US4] Create `Support/DescribePurchaseLines` in `app/Modules/Transactions/` implementing it from `transaction_lines`, `transactions` and `transaction_cancellations` (cancellation day in `SHOP_TIME_ZONE`), scoped to the shop; bind it in `TransactionsServiceProvider`; test it in `app/Modules/Transactions/Tests/DescribePurchaseLinesTest.php`
- [X] T023 [US4] Create `Actions/ListStockMovements` in `app/Modules/Inventory/` (research R8, R9): check the product with `FindProductIdsOfShop` (`product.not_found`, 404), cursor-paginate the shop's movements of the product by `sequence` descending, one sum query for the stock up to the oldest on the page, fill `stock_after` upwards, load the page's adjustment lines with their adjustments in one query, ask `PurchaseLineSources` once for the purchase lines
- [X] T024 [US4] Create `ListStockMovementsRequest` (`per_page` 1 to 100, default 50; `cursor`), `StockMovementResource` (shape of contracts/api.md) and `StockMovementController::index`; add route `GET products/{product}/stock-movements`

---

## Phase 7: User Story 5 - Know which products are running low (P2)

**Independent Test**: level 5, stock 6, loss of 1, product listed; purchase of 10, product gone (spec US5)

- [X] T025 [P] [US5] Write `app/Modules/Catalog/Tests/LowStockTest.php`: `PATCH /products/{product}` sets, changes and clears (`null`) `low_stock_level` ("JSON integer 0 to 999 999": `low_stock_level.integer`, `low_stock_level.negative`, `low_stock_level.too_high`) without touching the name rules or recording a movement; a level-only body is not `fields.required`; `ShopProduct` answers `low_stock_level`; the list holds exactly the products with a level and `stock_quantity` ≤ level (SC-005), lowest stock first, cursor pages; a level of 0 lists only stock 0 or below; products with no level never appear; another shop's products never appear; unauthenticated and no shop
- [X] T026 [US5] Add migration `app/Modules/Catalog/database/migrations/2026_11_21_000013_add_low_stock_level_to_shop_products_table.php` (`low_stock_level` unsigned int null); add it to `ShopProduct` fillable and casts and to `ShopProductResource`
- [X] T027 [US5] Extend `UpdateShopProductRequest` (`low_stock_level` in `CHANGEABLE_FIELDS`, rules and messages above), `Support/ShopProductChanges` and `UpdateShopProduct` (apply the level; skip the name checks when the name is not changing)
- [X] T028 [US5] Create `Actions/ListLowStockProducts`, `Controllers/Requests/ListLowStockProductsRequest` and `ShopProductController::lowStock` in `app/Modules/Catalog/`; add route `GET products/low-stock` before `products/{product}` in `app/Modules/Catalog/routes.php`

---

## Phase 8: User Story 6 - Only my shop's stock (P1)

- [X] T029 [P] [US6] Write `app/Modules/Inventory/Tests/IsolationTest.php`: another shop's product in an opening stock, count or loss gives `product_id.unknown` and records nothing; its history gives 404 `product.not_found`; setting its level gives 404; it never appears in the low-stock list; reusing another shop's adjustment or line id gives `id.taken`; nothing revealed (SC-007)

---

## Phase 9: User Story 7 - Try it with standard tools (P3)

- [X] T030 [US7] Add the folder "8. Stock" to `postman/shop-keeper.postman_collection.json` and its variables to `postman/shop-keeper.postman_environment.json`: "Set the opening stock", "Record a loss", "Record a count", "Read the stock history", "List low-stock products", following quickstart.md scenarios 1 to 12, with scripts that create ids and carry them over; add `low_stock_level` to the body of "Change the prices" in "4. Products" and rename it "Change the prices and low-stock level"; `tests/Feature/Shared/ApiCollectionTest.php` passes. The owner runs the collection by hand

---

## Phase 10: Polish & Cross-Cutting Concerns

- [X] T031 [P] Add a rule test in `app/Modules/Inventory/Tests/StockSumTest.php`: after random opening stocks, purchases, cancellations, counts and losses, every product's `stock_quantity` equals the sum of its movements (SC-002), and no update or delete query touches `stock_adjustments`, `stock_adjustment_lines` or `stock_movements`
- [X] T032 [P] Extend `tests/Feature/Shared/NoSecretsInLogsTest.php` with a loss note
- [X] T033 [P] Update `shop-keeper-backend/CLAUDE.md` (Data: the `stock_movements` line names purchases, opening stock, counts and losses; Catalog: `low_stock_level` on `shop_products`, a setting), the module tree in `shop-keeper-backend/README.md`, and the root `CLAUDE.md` current focus
- [X] T034 Compare `specs/010-stock-movements/contracts/api.md` with the routes, responses and codes; fix any drift in the same change
- [ ] T035 Run `php artisan test` and `vendor/bin/pint --test`, then every quickstart.md scenario on the Docker stack

---

## Dependencies & Execution Order

- **Phase 1** first, then **Phase 2**, which blocks every story.
- **US1**, **US2** and **US3** can run in parallel after Phase 2 (each adds its own request, controller method and route; T014 touches `RecordStockAdjustment` only for opening stock).
- **US4** needs at least one adjustment story for its tests. **US5** is independent of US1 to US4 (it uses purchases from 009 or a factory to set stock).
- **US6** and **US7** need every other story.
- Within a story: tests first and failing, then support classes, actions, requests and resources, controller, routes.

## Parallel Example

```text
After T012:
  T013 [US1] RecordOpeningStockTest   T016 [US2] RecordCountTest   T018 [US3] RecordLossTest   T025 [US5] LowStockTest
```

## Implementation Strategy

1. Phases 1 and 2.
2. US1: opening stock (MVP: a merchant can start with real figures).
3. US2 and US3: counts and losses.
4. US4 and US5: history and low stock.
5. US6, US7, then Polish.

All of it ships in one pull request with one commit, as the repository rules require.

## Notes

- [P] tasks touch different files and depend on no unfinished task.
- Any endpoint change updates `contracts/api.md`, the tests and the collection in the same change.
- Log no personal data; no secret in code or git.
