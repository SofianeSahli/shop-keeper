---

description: "Task list for spec 009, suppliers and deliveries"
---

# Tasks: Suppliers and Deliveries

**Input**: Design documents from `specs/009-suppliers-deliveries/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Required. FR-020 asks for success, validation, unauthenticated, no-shop and cross-shop tests on every endpoint, and its own tests for the rules of stock, balance, cancellation and repeated requests.

**Organization**: Tasks are grouped by user story so each story can be built and checked on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: The user story the task belongs to (US1 to US7)
- Every path is relative to `shop-keeper-backend/` unless it starts with `specs/`

## Path Conventions

- Module code: `app/Modules/Purchasing/` and `app/Modules/Inventory/`, with `Actions/`, `Support/`, `Controllers/` (with `Requests/` and `Resources/`), `Models/`, `Policies/`, `database/`, `Tests/`
- Run every command in the container: `docker compose exec app <command>`
- Follow `shop-keeper-backend/CLAUDE.md` and the root code style: thin controllers, one public `handle` per action, no comments, named constants, no call inside an argument, `vendor/bin/pint` before a task is done

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: The two empty modules, wired into the app, and the shop's clock

- [X] T001 Create `app/Modules/Inventory/InventoryServiceProvider.php` (loads its migrations) and `app/Modules/Purchasing/PurchasingServiceProvider.php` (loads its migrations and `routes.php` under `api/v1` with the `api` middleware, as `CatalogServiceProvider` does); register both in `bootstrap/providers.php`
- [X] T002 Create `app/Modules/Purchasing/routes.php` with one group using `auth:sanctum` then `RequireShop::ALIAS`
- [X] T003 [P] Add `shop_time_zone` (`env('SHOP_TIME_ZONE', 'Africa/Tunis')`) to `config/shop_keeper.php`, `SHOP_TIME_ZONE=Africa/Tunis` to `.env.example`, and `App\Shared\ShopClock` in `app/Shared/ShopClock.php` with `today(): CarbonImmutable` in that zone (research R8)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Tables, models and the stock path every story uses

**⚠️ CRITICAL**: No user story starts before this phase is done

- [X] T004 Add migration `app/Modules/Catalog/database/migrations/2026_11_20_000001_add_stock_quantity_to_shop_products_table.php`: signed `bigInteger('stock_quantity')->default(0)` after `purchase_price` (research R3)
- [X] T005 Add migration `app/Modules/Inventory/database/migrations/2026_11_20_000002_create_stock_movements_table.php` per data-model.md: binary(16) `id`, `shop_id`, binary(16) `shop_product_id` FK `shop_products`, `kind` string(30), signed `integer('quantity')`, binary(16) `source_id`, `created_at`; unique (`kind`, `source_id`); index (`shop_id`, `shop_product_id`)
- [X] T006 Add Purchasing migrations in `app/Modules/Purchasing/database/migrations/` for `suppliers`, `deliveries`, `delivery_lines`, `delivery_cancellations`, `supplier_payments`, `supplier_payment_cancellations`, exactly as data-model.md (generated `sort_key` columns with `storedAs('lower(hex(`id`))')`, unique and plain indexes, foreign keys with restrict delete)
- [X] T007 [P] Create `StockMovementKind` enum (`Delivery`, `DeliveryCancellation`) and `StockMovement` model (binary key, no `updated_at`) in `app/Modules/Inventory/Models/`
- [X] T008 [P] Create models in `app/Modules/Purchasing/Models/`: `Supplier` (derives `compared_name` and `identity_key` on save with `NameComparison`, `scopeOfShop`, `scopeWithBalance`), `Delivery` (lines, cancellation, supplier, payment relations; no `updated_at`), `DeliveryLine`, `DeliveryCancellation`, `SupplierPayment` (cancellation, supplier), `SupplierPaymentCancellation`, enum `PaymentMethod` (`cash`, `cheque`, `card`, `transfer`); each with `HasBinaryUuidKey` and a `uuid()` accessor
- [X] T009 [P] Create factories for `Supplier` in `app/Modules/Purchasing/database/factories/`
- [X] T010 Create Catalog actions `FindProductIdsOfShop` (returns which of the given ids are products of the shop) and `ChangeStockQuantities` (adds a quantity to each product of the shop, in product id order) in `app/Modules/Catalog/Actions/`
- [X] T011 Create `App\Modules\Inventory\Support\NewStockMovement` and action `RecordStockMovements::handle(int $shopId, StockMovementKind $kind, array $movements)` in `app/Modules/Inventory/`: inserts one movement per item with a server UUID v7, then calls `ChangeStockQuantities` with the summed quantities
- [X] T012 Add `stock_quantity` to `ShopProductResource` in `app/Modules/Catalog/Controllers/Resources/ShopProductResource.php` and the `stock_quantity` integer cast on `ShopProduct`; extend `app/Modules/Catalog/Tests/ShowProductTest.php` to show 0 for a new product
- [X] T013 Write `app/Modules/Inventory/Tests/RecordStockMovementsTest.php`: movements are stored with their kind, sign and source; each product's `stock_quantity` changes by the sum; a second movement with the same kind and source is refused by the unique index
- [X] T014 [P] Create `app/Modules/Purchasing/Tests/Support/PurchasingScenario.php`: merchant with shop, supplier, product, delivery body builder, payment body builder, and the `stockOf` and `balanceOf` helpers

**Checkpoint**: Migrations run, models load, stock changes through one path

---

## Phase 3: User Story 2 - Keep a list of my suppliers (Priority: P1)

**Goal**: Create, list, read and change suppliers, with duplicate names refused and balances shown

**Independent Test**: Create two suppliers, try a third with the same name in another case, see it refused and pointed to the first; rename one; list them

Suppliers come before deliveries because a delivery names one.

- [X] T015 [US2] Create `SupplierResource` (with `balance`) and `SupplierSummaryResource` in `app/Modules/Purchasing/Controllers/Resources/`
- [X] T016 [US2] Create `CreateSupplierRequest`, `UpdateSupplierRequest` and `ListRequest` (per_page, cursor, optional `supplier_id`) in `app/Modules/Purchasing/Controllers/Requests/`, with the field codes of data-model.md; the phone is checked by `InternationalPhone` and normalized
- [X] T017 [US2] Create actions `CreateSupplier` (repeat answers the existing one, `id.taken` for another shop's id, `supplier.name_taken` 409 with `existing_supplier_id`, lost race handled) and `UpdateSupplier` (lock, name check excluding itself, phone set or cleared) in `app/Modules/Purchasing/Actions/`, with `NewSupplier` and `SupplierChanges` in `Support/`
- [X] T018 [US2] Create `SupplierPolicy` and `SupplierController` (`index`, `store`, `show`, `update`) and their routes; register the policy
- [X] T019 [US2] Write `app/Modules/Purchasing/Tests/SupplierTest.php`: create (201, normalized phone, balance 0), repeat (200, one row), same name in another case (409 with id), same name in another shop allowed, validation codes, list order by name with pages, read, rename and phone clear, rename onto another's name refused, no update fields, another shop's supplier 404 on read and change, another shop's id `id.taken`, no session 401, no shop 403

**Checkpoint**: Suppliers work on their own

---

## Phase 4: User Story 1 - Record a delivery (Priority: P1) 🎯 MVP

**Goal**: A delivery with its lines brings stock in, all or nothing, once

**Independent Test**: Create two products, record one delivery with both, read the products back: stock grown, prices unchanged; the delivery shows its lines and total

- [X] T020 [US1] Create `DeliveryResource`, `DeliveryLineResource` and `SupplierPaymentResource` in `app/Modules/Purchasing/Controllers/Resources/`
- [X] T021 [US1] Create `RecordDeliveryRequest` in `app/Modules/Purchasing/Controllers/Requests/`: `id`, `supplier_id`, `delivery_date` (`Y-m-d`, not after `ShopClock::today()`), `reference`, `lines` (1 to 200; distinct `id` and `product_id`; quantity and unit price ranges), optional `payment` (needs `supplier_id`; `id`, `amount`, `method`, `reference`), lowercased ids, building `NewDelivery`, `NewDeliveryLine` and `NewSupplierPayment` in `Support/`
- [X] T022 [US1] Create action `RecordSupplierPayment` in `app/Modules/Purchasing/Actions/` (repeat answers existing, `id.taken`, unknown supplier `supplier_id.unknown`; optional `deliveryId`; error keys prefixed when called for a delivery)
- [X] T023 [US1] Create action `RecordDelivery` in `app/Modules/Purchasing/Actions/`: one transaction; repeat answers existing (200), another shop's id `id.taken`; line ids and payment id not used elsewhere (`lines.N.id: id.taken`, `payment.id: id.taken`); supplier of the shop (`supplier_id.unknown`); products of the shop through `FindProductIdsOfShop` (`lines.N.product_id: product_id.unknown`); amounts and total with the `lines.total_too_high` cap; insert delivery, then lines, then the payment through `RecordSupplierPayment`, then `RecordStockMovements` with kind `Delivery`; lost race answers the winner
- [X] T024 [US1] Create `DeliveryPolicy`, `DeliveryController::store` and `show`, and their routes
- [X] T025 [US1] Write `app/Modules/Purchasing/Tests/RecordDeliveryTest.php`: success (201, lines in order, amounts, total, stock grown, purchase and sale prices unchanged), 200 lines accepted in one request (SC-001), no supplier accepted, date default today in the shop zone (travel to 23:30 UTC the day before a Tunis date), past date kept, future date refused, each line error names its line and records nothing (no line, 201 lines, quantity 0, 1.5, too high, negative price, duplicate product, unknown product, another shop's product, duplicate line id), free goods at price 0, total cap, repeat with the same id (200, stock once), another shop's delivery id `id.taken`, a line id already used `id.taken`, payment recorded with the delivery, payment with no supplier refused, another shop's supplier `supplier_id.unknown`, no session 401, no shop 403

**Checkpoint**: MVP: stock enters the shop through deliveries

---

## Phase 5: User Story 3 - Correct a wrong delivery (Priority: P1)

**Goal**: Cancel a whole delivery, take its stock back, once

**Independent Test**: Record a delivery, cancel it, see stock back, delivery marked cancelled, and a second cancel change nothing

- [X] T026 [US3] Create `CancelRequest` (`id` required UUID) and action `CancelDelivery` in `app/Modules/Purchasing/`: lock the delivery, answer it if cancelled, `id.taken` when the cancellation id is used, else insert the cancellation and `RecordStockMovements` with kind `DeliveryCancellation` and negative quantities
- [X] T027 [US3] Add `DeliveryController::cancel` and route `POST deliveries/{delivery}/cancellation` (201 when cancelled now, 200 when already)
- [X] T028 [US3] Write `app/Modules/Purchasing/Tests/CancelDeliveryTest.php`: stock back, `cancelled_at` set, second cancel 200 with nothing changed, other deliveries of the same products untouched, payment recorded with it still counts, no route to change or delete a delivery (`PATCH`/`DELETE` give 405), another shop's delivery 404, another shop's cancellation id `id.taken`, no session 401, no shop 403

---

## Phase 6: User Story 4 - Pay my suppliers and know what I owe (Priority: P1)

**Goal**: Record and cancel payments; balances follow

**Independent Test**: Two deliveries from one supplier, pay part, read the balance; cancel the payment, read again

- [X] T029 [US4] Create `RecordSupplierPaymentRequest` and action `CancelSupplierPayment` in `app/Modules/Purchasing/`
- [X] T030 [US4] Create `SupplierPaymentPolicy`, `SupplierPaymentController` (`index`, `store`, `show`, `cancel`) and their routes
- [X] T031 [US4] Write `app/Modules/Purchasing/Tests/SupplierPaymentTest.php`: record (201, balance down), date default and future refused, amount 0 / too high / not an integer, unknown method, no supplier, overpay allowed (negative balance), repeat (200, balance once), cancel and cancel again, list newest first and by supplier, another shop's payment 404 and its supplier `supplier_id.unknown`, no session 401, no shop 403
- [X] T032 [US4] Write `app/Modules/Purchasing/Tests/BalanceTest.php`: after a mixed series of deliveries (with and without supplier), payments and cancellations, every supplier's balance equals non-cancelled delivery totals minus non-cancelled payments (SC-008), and every product's `stock_quantity` equals the sum of its movements and the delivered minus cancelled quantities (SC-002)

---

## Phase 7: User Story 5 - See my past deliveries (Priority: P2)

**Goal**: List deliveries newest first, filter by supplier, open one

**Independent Test**: Deliveries from two suppliers on different dates; list all, then one supplier's; open one

- [X] T033 [US5] Add `DeliveryController::index` with `ListRequest`, ordered by `delivery_date` then `sort_key` descending, cursor paged, optional `supplier_id`; route `GET deliveries`
- [X] T034 [US5] Write `app/Modules/Purchasing/Tests/ListDeliveriesTest.php`: order, pages with cursor, supplier filter, another shop's supplier filter gives an empty page, `cancelled_at` and `line_count` in the list, read one with its lines, another shop's delivery 404, no session 401, no shop 403

---

## Phase 8: User Story 6 - Only my shop's records (Priority: P1)

**Goal**: Isolation across every endpoint

- [X] T035 [US6] Write `app/Modules/Purchasing/Tests/IsolationTest.php`: a second merchant gets the same answers for another shop's supplier, delivery and payment ids as for unknown ids on every read, change and cancel route, and every list holds only their own records

---

## Phase 9: User Story 7 - Try it with standard tools (Priority: P3)

- [X] T036 [US7] Add folders "Suppliers", "Deliveries" and "Supplier payments" to `postman/shop-keeper.postman_collection.json` after "Products", with test scripts that save `supplier_id`, `delivery_id`, `payment_id` and fresh UUIDs for every body id, and the new variables to `postman/shop-keeper.postman_environment.json`; `tests/Feature/Shared/ApiCollectionTest.php` must pass

---

## Phase 10: Polish & Cross-Cutting Concerns

- [X] T037 [P] Update `shop-keeper-backend/CLAUDE.md` (Catalog: stock is `stock_quantity`, derived from `stock_movements`) and the root `CLAUDE.md` current focus
- [X] T038 Run `docker compose exec app vendor/bin/pint` and `docker compose exec app php artisan test`; all pass
- [X] T039 Check the code against the root code style (no call inside an argument) and the contract file; fix any gap

---

## Dependencies & Execution Order

- Setup (Phase 1) → Foundational (Phase 2) → user stories.
- US2 (suppliers) before US1 (deliveries name a supplier and may carry a payment); US1 before US3, US4, US5. US6 and US7 last.
- Inside a story: resources and requests, then actions, then controller and routes, then tests.

## Parallel Opportunities

- T003 beside T001 and T002; T007, T008, T009 and T014 together once migrations exist.
- Within US4, the payment request and the balance test can be written beside the controller.

## Implementation Strategy

1. Phases 1 and 2, then US2 and US1: suppliers and stock entering the shop (MVP).
2. US3 and US4: corrections and debt.
3. US5, US6, US7, polish.
