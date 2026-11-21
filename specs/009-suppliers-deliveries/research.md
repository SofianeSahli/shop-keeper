# Research: Contacts, Transactions and Payments (purchases)

Decisions for `plan.md`. Sources: the spec and its clarifications of 2026-11-20 and 2026-11-21, `shop-keeper-backend/CLAUDE.md`, the 007 code (`CreateShopProduct`, `HasBinaryUuidKey`, `NameComparison`), the first 009 code on this branch (Purchasing, Inventory), MySQL 8.4 reference (generated columns, CHECK constraints, InnoDB locking), Laravel 13 docs (cursor pagination, container bindings). Reworked 2026-11-21.

## R1. Four modules and how they talk

- **Decision**: `Contacts` owns `contacts`. `Transactions` owns `transactions`, `transaction_lines`, `transaction_cancellations`. `Payments` owns `payments`, `payment_cancellations`. `Inventory` (kept from the first version) owns `stock_movements`. Catalog keeps `shop_products` and its cached `stock_quantity` (R3).
- **Dependencies**: Payments → Transactions, Contacts. Transactions → Contacts, Catalog, Inventory. Contacts → nothing. No cycle.
- **Reading money the other way**: Contacts must show a balance and Transactions must show an amount paid, both computed from payments. Each of the two modules declares an interface in its own `Contracts/` folder (`ContactBalances`, `TransactionPayments`); Payments implements both and binds them in `PaymentsServiceProvider`. Contacts and Transactions depend only on their own interfaces, never on Payments.
- **Rationale**: Owner rework of 2026-11-21: an address book, one transaction module for purchases and sales, and payments on their own. Interfaces owned by the consumer keep each module testable alone (a fake binding in tests) and are the seam for extracting Payments into a shared package later, once spec 011 has proven it with sales.
- **Alternatives**: one `Purchasing` module (the first version): mixes the address book, purchases and payments, and spec 011 would copy it for sales. Contacts reading the payments table directly: breaks the module rule.

## R2. Stock movements

- **Decision**: `stock_movements` as in the first version, with kinds `purchase` and `purchase_cancellation` (enum `StockMovementKind`) instead of `delivery` and `delivery_cancellation`; `source_id` is the transaction line. Unique `(kind, source_id)`. Server UUID v7 keys.
- **Rationale**: The transaction line is the source for purchases now and for sales in 011 (kinds `sale`, `sale_cancellation`). 010 adds its own kinds and sources.

## R3. Stock quantity cached on the shop product

- **Decision**: Unchanged from the first version: `shop_products.stock_quantity`, changed only by Catalog's `ChangeStockQuantities`, in product id order, inside the transaction that records the movements. A test checks it equals the sum of movements.

## R4. Ids, repeats and races

- **Decision**: Client UUIDs as `BINARY(16)` for contacts, transactions, lines, payments and cancellations. Each creation reads the id first: same shop answers the record with 200; another shop gives `id.taken`. A lost race on the primary key re-reads with `lockForUpdate`. `RecordWithSameId` and `Created` move from `Purchasing/Support` to `app/Shared/Records/`, since three modules use them.
- **Rationale**: FR-021, FR-022; the pattern already reviewed in 007 and the first 009.

## R5. Cancellations: one shape, one helper

- **Decision**: `transaction_cancellations` and `payment_cancellations` have the same columns: client `id`, `shop_id`, a unique link to the record, `reason` (string 255, null), `cancelled_by` (FK users) and `created_at`. A shared helper `App\Shared\Records\CancelOnce` does the common steps: lock the record row, answer it unchanged if a cancellation exists, refuse a cancellation id used elsewhere, insert the cancellation, then run the caller's extra step (taking stock back for a transaction).
- **Rationale**: FR-017 to FR-019. The first version had two near copies (`CancelDelivery`, `CancelSupplierPayment`); 011 would have added two more. One table per cancelled record type keeps real foreign keys.
- **Alternatives**: a polymorphic `cancellations` table: no foreign key to the cancelled record.

## R6. Transactions with payments cannot be cancelled

- **Decision**: `CancelTransaction` asks `TransactionPayments::hasActivePayments` under the transaction's row lock and refuses with `transaction.has_payments` (409). `RecordPayment` locks the same row when linking a payment, so the two cannot interleave.
- **Rationale**: Owner chose payments linked to transactions. Cancelling a paid purchase would leave payments pointing at a cancelled transaction, and the merchant would not know whether that money is still real. Making them cancel or re-record the payment first is one more tap and keeps every figure explainable.

## R7. Amount paid, amount left and balances are computed

- **Decision**: Computed on read by Payments' implementations. Amount paid of a transaction: sum of its payments without a cancellation. Balance of a contact: sum of its purchases without a cancellation, minus its `paid` payments without a cancellation, plus its `received` payments without a cancellation. Transactions gives Payments the purchase totals per contact through its action `SumTransactionTotalsByContact`. Lists compute them for the whole page in two grouped queries.
- **Rationale**: Records are the truth; a stored figure could drift. A shop has tens of contacts and hundreds of transactions a month, and the queries use `(shop_id, contact_id)` and `transaction_id` indexes.

## R8. Payment direction and linked-payment rules

- **Decision**: `payments.direction` is `paid` (the shop pays) or `received` (the shop receives). A payment needs a contact or a transaction (CHECK constraint and validation `contact_id.required`). Linked to a transaction, it must: have the transaction's contact (`contact_id.mismatch`), or none when the transaction has none; have the direction its kind implies, `paid` for a purchase (`direction.mismatch`); point to a transaction not cancelled (`transaction_id.cancelled`); and not exceed its amount left (`amount.exceeds_left`), checked under the transaction's row lock. When the payment names a transaction and no contact, it takes the transaction's contact.
- **Rationale**: FR-012 to FR-014; one rule set that sales reuse with `received`.

## R9. Who recorded and who cancelled

- **Decision**: `transactions.created_by`, `payments.created_by` and `*_cancellations.cancelled_by` are foreign keys to `users` (restrict delete), set from the signed-in account. Responses show the account id.
- **Rationale**: FR-018, FR-020. Only the owner today, but spec 014 (employees) needs it, and adding it to existing immutable rows later is impossible.

## R10. Lists, cursors and binary keys

- **Decision**: Contacts are ordered by `compared_name`, then `identity_key`; filter `role=supplier|customer`. Transactions and payments by date descending, then `sort_key` descending, a stored generated column `lower(hex(id))`. Filters: transactions `kind`, `contact_id`; payments `contact_id`, `transaction_id`. `cursor` and `per_page` (1 to 100, default 50). Another shop's id in a filter gives an empty page.

## R11. Dates in the shop's time zone

- **Decision**: Kept: `ShopClock::today()` from `SHOP_TIME_ZONE` (default `Africa/Tunis`). `transaction_date` and `payment_date` are `Y-m-d`, default today, never after today.

## R12. Amounts

- **Decision**: Kept: line amount and total computed with integer arithmetic; a total above 9 007 199 254 740 991 is refused with `lines.total_too_high`.

## R13. Shared helpers

- **Decision**: Contacts uses Catalog's `NameComparison` and Identity's `InternationalPhone` rule; lines use Catalog's `PriceInMillimes`. Pure classes, no tables.

## R14. Payment methods

- **Decision**: Enum `PaymentMethod` in Payments: `cash`, `cheque`, `card`, `transfer` (constitution VII).

## R15. Replacing the first version

- **Decision**: The first 009 code was never merged into `main`. Its `Purchasing` module, its three migrations, its tests and its Postman folders are deleted, not migrated. Developers run `php artisan migrate:fresh`. Kept and adapted: Inventory, the `stock_quantity` migration, `ShopClock`, Catalog's `FindProductIdsOfShop` and `ChangeStockQuantities`.
- **Rationale**: No data exists outside development databases.

## R16. Lines carry no shop

- **Decision**: `transaction_lines` has no `shop_id`. A line is reached only through its transaction (`Transaction::lines()`), and every transaction lookup is scoped to the shop. A line's product is checked to belong to the transaction's shop when the transaction is recorded (`FindProductIdsOfShop`). A line id already used anywhere answers `id.taken` on that line.
- **Rationale**: Owner decision of 2026-11-21: one source of truth for ownership, no column that could disagree with its transaction, and a line that does not need to know about shops.
- **Alternatives**: a copied `shop_id` (first version) to query lines directly by shop: no such query is needed; 010 and 012 read through transactions or stock movements.
