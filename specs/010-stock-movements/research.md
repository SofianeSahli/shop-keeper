# Research: Stock Movements and Low-Stock Warning

Decisions for `plan.md`. Sources: the spec and its clarifications of 2026-11-21, `shop-keeper-backend/CLAUDE.md`, the 009 code on `main` (Inventory, Transactions, `RecordWithSameId`, `ShopClock`), the 007 code (Catalog, `UpdateShopProductRequest`), `tests/Feature/Shared/ApiCollectionTest.php`, MySQL 8.4 reference (AUTO_INCREMENT on a non-primary column, unique indexes and NULL), Laravel 13 docs (cursor pagination).

## R1. Modules and how they talk

- **Decision**: Inventory owns the new tables `stock_adjustments` and `stock_adjustment_lines`, and keeps `stock_movements`. Catalog owns the low-stock level (a column on `shop_products`) and the low-stock list, since both read `shop_products`.
- **Dependencies**: Inventory → Catalog (`FindProductIdsOfShop`, `ChangeStockQuantities`, as in 009). Transactions → Inventory (as in 009). The history needs facts about 009's purchase movements (purchase, date, account) that live in Transactions' tables: Inventory declares an interface `Inventory\Contracts\PurchaseLineSources`, and Transactions implements and binds it. Same consumer-owned interface pattern as 009 R1. No cycle: Inventory never calls Transactions' classes.
- **Alternatives**: copying the account and date onto every stock movement: the 009 movements already recorded have neither, and adding a NOT NULL column to a table with rows fails. Inventory reading `transactions`: breaks the module rule.

## R2. Adjustments and their lines

- **Decision**: one request records one **stock adjustment** with a kind (`opening_stock`, `count`, `loss`), a date, the account and 1 to 200 lines. Each line names one product and becomes one stock movement whose `source_id` is the line id, exactly as a purchase line is the source of a `purchase` movement. The adjustment and every line carry client UUIDs.
- **Rationale**: Same shape as 009's transaction and lines, so repeats (FR-016), races (FR-017) and all-or-nothing (FR-006) reuse `RecordWithSameId` and the insert-header-first pattern of `RecordTransaction`. `stock_movements` keeps its columns and its `(kind, source_id)` uniqueness.
- **Spec change**: the spec gives ids to entries; the request also gets its own id (the adjustment). Recorded in `plan.md`.
- **Alternatives**: one row per product with no header: repeats must be checked line by line, and a half-repeated request has no clear answer. Kind-specific columns directly on `stock_movements`: mixes purchase movements, which have no reason or count, with adjustment details.

## R3. Three endpoints, not one with a kind

- **Decision**: `POST /stock/openings`, `POST /stock/counts`, `POST /stock/losses`, all handled by one action `RecordStockAdjustment` with a kind.
- **Rationale**: Each kind has its own line fields (quantity; counted and shown; quantity, reason and note), so one Form Request per kind keeps validation and error codes simple. The API collection test requires exactly one Postman request per route, and the journey needs all three kinds.
- **Alternatives**: `POST /stock-adjustments` with `kind`, like `POST /transactions`: one route, so the collection could show only one kind, and the rules would branch on the kind.

## R4. Movement kinds

- **Decision**: `StockMovementKind` gains `OpeningStock = 'opening_stock'`, `Count = 'count'`, `Loss = 'loss'`; `Purchase` and `PurchaseCancellation` are unchanged. The adjustment kind uses the same values (`StockAdjustmentKind`, with `movementKind()`).
- **Rationale**: One word per idea across the adjustment, its movements and the history.

## R5. Opening stock at most once per product

- **Decision**: `stock_adjustment_lines.opening_product_id` (binary(16), null, **unique**) holds the product id on opening-stock lines and NULL on others. `RecordStockAdjustment` checks it first and answers `lines.N.product_id: product_id.opening_already_set`. A twin request with another adjustment id that wins the race hits the unique index; the action catches it, re-reads which products now have an opening stock, and answers the same field error.
- **Rationale**: FR-002 and FR-017 with a database guarantee. MySQL allows many NULLs in a unique index.
- **Alternatives**: a lock on the product row: that row is Catalog's. A stored generated column from the kind: the kind sits on the header, not the line.

## R6. Count rule

- **Decision**: a count line carries `counted_quantity` (0 to 999 999) and `shown_quantity` (−999 999 999 to 999 999 999, the stock the app showed). The movement quantity is `counted − shown`, computed on the server. No lock or read of the current stock is needed. A difference of 0 is still recorded.
- **Rationale**: Owner answer Q1 of 2026-11-21. Movements that reach the server between the count and its arrival keep counting. The widest difference (999 999 + 999 999 999) fits the signed `INT` column of `stock_movements`.

## R7. Loss reasons

- **Decision**: enum `LossReason`: `broken`, `expired`, `stolen`, `own_use`, `other`. Optional `note`, at most 255 characters after trimming, stored on the line. Personal data is not expected in a note, but notes are never logged.
- **Rationale**: Owner answer Q3. Codes only; the app translates them.

## R8. Server order and stock after each movement

- **Decision**: a new Inventory migration adds `stock_movements.sequence`, an unsigned BIGINT AUTO_INCREMENT with a unique index, through a raw `ALTER TABLE`, plus an index `(shop_product_id, sequence)`. Existing rows get numbers in insertion order. The history orders by `sequence` descending with cursor pagination. For a page, one query sums the product's movements up to the oldest movement on the page, and the stock after each movement is computed upwards from there.
- **Rationale**: FR-010, FR-011, SC-004. Movement ids are UUID v7 made per row, and their order within one millisecond is not guaranteed, so they cannot define "stock after". Inventory computes from its own movements, never from Catalog's cached `stock_quantity`, and a test checks the newest entry equals that cache.
- **Alternatives**: order by `created_at` then id: ties inside one request. A window function over all the product's movements per page: same result, more work as the history grows. Laravel's `autoIncrement()` on an added column also declares a primary key, which the table already has, hence the raw statement.

## R9. Details of 009 movements in the history

- **Decision**: `PurchaseLineSources::describe(int $shopId, array $lineIds)` returns for each transaction line id: `transaction_id`, `transaction_date`, `recorded_by`, and, if cancelled, `cancelled_by` and the cancellation's date in the shop's time zone. The history shows, for `purchase`: the transaction's date and account; for `purchase_cancellation`: the cancellation's date and account. Both show `transaction_id`. One call per page.
- **Rationale**: The spec's assumption says purchase movements show the account that recorded their purchase. For a cancellation, the account that cancelled is the one that changed the stock, which is more useful; recorded as a spec refinement in `plan.md`.

## R10. Dates and accounts

- **Decision**: `stock_adjustments.adjustment_date` (`Y-m-d`, default `ShopClock::todayAsDate()`, never after today; `adjustment_date.invalid`, `adjustment_date.in_future`) and `created_by` (FK users, restrict delete), as `transaction_date` and `created_by` in 009.

## R11. Low-stock level and list

- **Decision**: a Catalog migration adds `shop_products.low_stock_level` (unsigned INT, null). It is set, changed or cleared (`null`) through the existing `PATCH /products/{product}`, which gains the field; a level-only change skips the name checks. `ShopProduct` answers `low_stock_level`. The list is `GET /products/low-stock` in Catalog (action `ListLowStockProducts`): products of the shop with a level and `stock_quantity <= low_stock_level`, ordered by `stock_quantity` ascending then id, cursor pages of ShopProduct. The route is registered before `products/{product}`.
- **Rationale**: Owner answer Q2: one level per product, no shop default, list only. Changing the level is a product setting, not a stock record, so it may be updated (FR-012). A shop has hundreds to a few thousand products; the comparison of two columns scans only the shop's rows through the `shop_id` index.
- **Alternatives**: a `low_stock=1` filter on `GET /products`: that list is ordered by name, and its cursor would need a second ordering.

## R12. Authorization and isolation

- **Decision**: as in 009: `RequireShop`, every lookup scoped to the current shop, products checked with `FindProductIdsOfShop` (`product_id.unknown`), another shop's product answered `product.not_found` (404) on the history route. Adjustments are only created, never read by id, so they need no policy method beyond the scoped creation. An adjustment or line id used by another shop, or by another kind of adjustment in the same shop, answers `id.taken`.

## R13. Out of scope, noted for 011

- `shop-keeper-backend/CLAUDE.md` says a sale never writes a stock movement and that its stock exit is its sale lines, while 009 and this feature make stock the sum of movements. 010 does not touch sales; spec 011 must decide which rule changes. Until then the guide's line "`stock_movements` holds only receptions, adjustments and losses" is updated to name the kinds that exist.
