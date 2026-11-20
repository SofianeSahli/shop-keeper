# Research: Suppliers and Deliveries

Decisions for `specs/009-suppliers-deliveries/plan.md`. Sources: the spec and its clarifications of 2026-11-20, `shop-keeper-backend/CLAUDE.md`, the 007 code (`CreateShopProduct`, `ShopProductNameRules`, `HasBinaryUuidKey`), MySQL 8.4 reference (generated columns, InnoDB locking on duplicate keys), Laravel 13 docs (cursor pagination, `withSum`). Checked 2026-11-20.

## R1. Two new modules: `Purchasing` and `Inventory`

- **Decision**: `app/Modules/Purchasing/` owns `suppliers`, `deliveries`, `delivery_lines`, `delivery_cancellations`, `supplier_payments` and `supplier_payment_cancellations`. `app/Modules/Inventory/` owns `stock_movements`. Catalog keeps `shop_products` and gains a cached `stock_quantity` column (R3).
- **Rationale**: The backend guide lists `Inventory` as the stock module, and spec 010 adds adjustments, losses and alerts to it. Suppliers, deliveries and what is owed to suppliers are one business area (buying), read together for the balance (R6), so they share a module. A module never touches another module's tables: Purchasing calls Inventory's `RecordStockMovements`, which calls Catalog's `ChangeStockQuantities`; Purchasing checks products through Catalog's `FindProductIdsOfShop`.
- **Alternatives**: everything in `Inventory`: mixes money owed to suppliers with stock. A `Suppliers` module plus a `Deliveries` module: the balance query would cross modules.

## R2. Stock movements

- **Decision**: `stock_movements` has a server-generated UUID key, `shop_id`, `shop_product_id`, `kind` (`delivery` or `delivery_cancellation`, enum `StockMovementKind`), a signed `quantity`, `source_id` (the delivery line) and `created_at`. Unique `(kind, source_id)`: a line is received once and taken back once, even if a bug calls the action twice.
- **Rationale**: Clarification 1. The movement points to its line, not the line to the movement, so 010's adjustments and losses use the same table with their own sources. The app never creates movements, so a server UUID v7 is fine.
- **Alternatives**: a movement per delivery: loses the per-product link 010 needs.

## R3. Stock quantity cached on the shop product

- **Decision**: New Catalog migration adds `shop_products.stock_quantity` (signed `BIGINT`, default 0). Only Catalog's `ChangeStockQuantities` changes it, with `stock_quantity = stock_quantity + ?`, in product id order, called inside the delivery or cancellation transaction. `ShopProductResource` shows it. A test checks that it always equals the sum of the product's movements (SC-002).
- **Rationale**: The product list (007) shows every product of a shop; a sum over movements per page is avoidable work, and the sales spec will lower stock from sale lines, not movements (backend guide), so the true stock will not be a single sum anyway. The row update also serializes concurrent changes on one product. It is a derived value, not a money or stock record, so updating it does not break the "never update" rule; the movements remain the record.
- **Alternatives**: compute on read from `stock_movements`: Catalog would depend on Inventory for every product read, and 011 would need a second sum.

## R4. Ids, repeats and races

- **Decision**: Suppliers, deliveries, delivery lines, payments and both cancellations take client UUIDs stored as `BINARY(16)` (`HasBinaryUuidKey`). Each creation runs in one transaction that first reads the record with that id: same shop answers it with 200 and changes nothing; another shop gives `id.taken`. A lost race on the primary key (`UniqueConstraintViolationException`) re-reads with `lockForUpdate` and answers the same way, as `CreateShopProduct` does. The delivery row is inserted before its lines, payment and movements, so a second identical request blocks on the key and finds the committed delivery (FR-017). A line or payment id already used by any other record gives `id.taken` on that field.
- **Rationale**: FR-016, FR-017, and the 007 pattern the team already reviews.

## R5. Cancellations are rows of their own

- **Decision**: `delivery_cancellations` and `supplier_payment_cancellations`, each with a client id, `shop_id`, a unique link to the cancelled record and `created_at`. Cancelling locks the delivery (or payment) row for update, then answers the record unchanged if a cancellation exists, else inserts one. A delivery cancellation also records one `delivery_cancellation` movement per line with the negative quantity. Cancelling a delivery never cancels the payment recorded with it.
- **Rationale**: FR-012, FR-024: a `cancelled_at` column would update a money record. The unique link makes a second cancellation impossible even across a race; the row lock makes it answer cleanly.
- **Alternatives**: a status column on the delivery: breaks the never-update rule.

## R6. Balance per supplier

- **Decision**: Computed on read: `withSum` of `deliveries.total` without a cancellation, minus `withSum` of `supplier_payments.amount` without a cancellation. Shown on every supplier as `balance` (signed integer millimes).
- **Rationale**: Clarification 4. Computing from records means the balance can never drift; a shop has tens of suppliers and hundreds of deliveries a month, so the sums are cheap with the `supplier_id` indexes.
- **Alternatives**: a cached balance column: one more derived value to keep right for no need yet.

## R7. Lists, cursors and binary keys

- **Decision**: Suppliers are ordered like products: `compared_name`, then `identity_key` (unique in a shop). Deliveries and payments are ordered by date descending, then by `sort_key` descending, a stored generated column `lower(hex(id))`. Lists take `cursor` and `per_page` (1 to 100, default 50) and an optional `supplier_id`; another shop's supplier id simply gives an empty list.
- **Rationale**: A cursor cannot carry the binary `id` (007 data-model note), and dates are not unique. The generated column is the id in a form a cursor can carry, so it costs nothing to keep right.

## R8. Dates in the shop's time zone

- **Decision**: New setting `shop_keeper.shop_time_zone` from `SHOP_TIME_ZONE` (default `Africa/Tunis`), read by `App\Shared\ShopClock::today()`. Delivery and payment dates are `Y-m-d`, default today in that zone, and refused when after today (`delivery_date.in_future`, `payment_date.in_future`).
- **Rationale**: FR-005. Shops have no time zone column and every shop is in Tunisia today (currency TND, spec 006); a per-shop zone can come with the multi-country work. Configuration only through `.env`, as the constitution requires.

## R9. Amounts

- **Decision**: Line `amount = quantity × unit_price` and the delivery `total` are computed by the server with integer arithmetic and stored (`BIGINT UNSIGNED`). A delivery whose total would exceed 9 007 199 254 740 991 millimes is refused with `lines.total_too_high`.
- **Rationale**: FR-006. The spec allows lines up to 999 999 × 999 999 999 and 200 lines, about 2 × 10^17, which a JavaScript number in the app cannot hold exactly. The cap is far above any real delivery (9 billion dinars) and keeps "never rounded" true end to end. This is a small addition to the spec, listed in the plan.

## R10. Payments recorded with a delivery

- **Decision**: `POST /deliveries` takes an optional `payment` object (`id`, `amount`, `method`, optional `reference`). It is refused with `payment.supplier_required` when the delivery has no supplier. The payment is recorded by the same `RecordSupplierPayment` action as `POST /supplier-payments`, inside the delivery transaction, dated the delivery date, with `delivery_id` set. The delivery answer includes it.
- **Rationale**: FR-023; one rule for payments.

## R11. Shared helpers reused across modules

- **Decision**: Purchasing uses Catalog's `NameComparison` for supplier names, Catalog's `PriceInMillimes` for unit prices, and Identity's `InternationalPhone` rule for the phone, stored normalized (`+216…`).
- **Rationale**: "Compared as in spec 007" (FR-002) and "as in spec 006" (FR-001) mean the same rule; these are pure classes, not tables or models, so the module rule is kept. Moving them to `app/Shared/` can wait until a third module needs them.

## R12. Payment methods

- **Decision**: Enum `PaymentMethod`: `cash`, `cheque`, `card`, `transfer`. Field code `method.invalid`.
- **Rationale**: Constitution VII names these four for customer settlements; the same list for suppliers keeps the apps consistent.
