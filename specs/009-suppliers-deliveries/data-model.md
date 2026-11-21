# Data Model: Contacts, Transactions and Payments (purchases)

Modules `Contacts`, `Transactions`, `Payments`, `Inventory`, plus one Catalog column (research R1 to R3). Every key a client creates is a UUID stored as `BINARY(16)`. Money is unsigned `BIGINT` millimes. No row of `transactions`, `transaction_lines`, `transaction_cancellations`, `payments`, `payment_cancellations` or `stock_movements` is ever updated or deleted; they have `created_at` only.

## contacts (Contacts)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `name` | string(120) | required, trimmed, kept as written |
| `compared_name` | string(255), `utf8mb4_bin` | from `name` with `NameComparison` |
| `identity_key` | string(255), `utf8mb4_bin` | `compared_name` without spaces |
| `phone` | string(16), null | E.164, normalized |
| `address` | string(255), null | trimmed |
| `note` | string(255), null | trimmed |
| `is_supplier` | boolean, default false | |
| `is_customer` | boolean, default false | |
| timestamps | | |

Unique `(shop_id, identity_key)`. Index `(shop_id, compared_name, identity_key)`. Never deleted. `balance` is not stored (research R7).

## transactions (Transactions)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `kind` | string(20) | `purchase` (enum `TransactionKind`); `sale` from 011 |
| `contact_id` | binary(16), null, FK contacts, restrict delete | a contact of the same shop |
| `transaction_date` | date | default today in the shop's zone; not after today |
| `reference` | string(50), null | supplier's note number |
| `total` | unsigned bigint | sum of line amounts; at most 9 007 199 254 740 991 |
| `line_count` | unsigned smallint | 1 to 200 |
| `created_by` | FK users, restrict delete | |
| `sort_key` | char(32), stored generated `lower(hex(id))` | |
| `created_at` | timestamp | |

Indexes `(shop_id, transaction_date, sort_key)`, `(shop_id, contact_id, transaction_date, sort_key)`, `(shop_id, kind, transaction_date, sort_key)`.

## transaction_lines (Transactions)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `transaction_id` | binary(16), FK transactions, restrict delete | |
| `position` | unsigned smallint | order sent, from 1 |
| `shop_product_id` | binary(16), FK shop_products, restrict delete | a product of the same shop |
| `quantity` | unsigned int | 1 to 999 999 |
| `unit_price` | unsigned bigint | 0 to 999 999 999 |
| `amount` | unsigned bigint | `quantity × unit_price` |
| `created_at` | timestamp | |

Unique `(transaction_id, shop_product_id)` and `(transaction_id, position)`.

No `shop_id`: a line belongs to its transaction, and its shop is the transaction's shop. Lines are only read through a transaction already scoped to the shop (research R16).

## transaction_cancellations (Transactions)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `transaction_id` | binary(16), FK transactions, restrict delete | unique |
| `reason` | string(255), null | trimmed |
| `cancelled_by` | FK users, restrict delete | |
| `created_at` | timestamp | |

## payments (Payments)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `direction` | string(10) | `paid` or `received` (enum `PaymentDirection`) |
| `contact_id` | binary(16), null, FK contacts, restrict delete | |
| `transaction_id` | binary(16), null, FK transactions, restrict delete | |
| `amount` | unsigned bigint | 1 to 999 999 999 999 |
| `method` | string(10) | `cash`, `cheque`, `card`, `transfer` (enum `PaymentMethod`) |
| `payment_date` | date | default today in the shop's zone; not after today |
| `reference` | string(50), null | cheque or transfer number |
| `created_by` | FK users, restrict delete | |
| `sort_key` | char(32), stored generated `lower(hex(id))` | |
| `created_at` | timestamp | |

CHECK `contact_id IS NOT NULL OR transaction_id IS NOT NULL`. Indexes `(shop_id, payment_date, sort_key)`, `(shop_id, contact_id, payment_date, sort_key)`, `(transaction_id)`.

## payment_cancellations (Payments)

Same columns as `transaction_cancellations`, with `payment_id` (binary(16), FK payments, restrict delete, unique) instead of `transaction_id`.

## stock_movements (Inventory)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; server UUID v7 |
| `shop_id` | FK shops, restrict delete | |
| `shop_product_id` | binary(16), FK shop_products, restrict delete | |
| `kind` | string(30) | `purchase`, `purchase_cancellation` (enum `StockMovementKind`); 010 and 011 add more |
| `quantity` | int, signed | positive for `purchase`, negative for `purchase_cancellation` |
| `source_id` | binary(16) | the transaction line |
| `created_at` | timestamp | |

Unique `(kind, source_id)`. Index `(shop_id, shop_product_id)`.

## shop_products (Catalog, column kept from the first version)

`stock_quantity`: bigint, signed, default 0; the sum of the product's movements, changed only by `ChangeStockQuantities`.

## States

```text
Transaction: recorded --cancel (no active payment)--> cancelled
Payment:     recorded --cancel--> cancelled
Payment state of a transaction (computed): unpaid (paid = 0) → partly_paid (0 < paid < total) → paid (paid = total)
A transaction with total 0 is paid.
```

## Computed figures

```text
amount_paid(transaction) = Σ payments.amount  where transaction_id = t and no cancellation
amount_left(transaction) = total − amount_paid

balance(contact) = Σ transactions.total where contact_id = c, kind = purchase, no cancellation
                 − Σ payments.amount    where contact_id = c, direction = paid,     no cancellation
                 + Σ payments.amount    where contact_id = c, direction = received, no cancellation
```

## Validation

| Field | Rule and field codes |
|-------|----------------------|
| `id` (every creation) | required UUID; `id.required`, `id.invalid`, `id.taken` |
| contact `name` | required, at most 120 after trimming; `name.required`, `name.invalid`, `name.too_long` |
| contact `phone` | optional, nullable; E.164 after normalizing; `phone.invalid` |
| contact `address`, `note` | optional, nullable, at most 255; `address.invalid`, `address.too_long`, `note.invalid`, `note.too_long` |
| `is_supplier`, `is_customer` | optional booleans, default false; `is_supplier.invalid`, `is_customer.invalid` |
| contact update | any of the fields above, at least one; `fields.required`; `id.prohibited` |
| `role` (contact list) | `supplier` or `customer`; `role.invalid` |
| `kind` | required, `purchase` in this feature; `kind.required`, `kind.invalid` |
| `contact_id` | optional UUID of a contact of the shop; `contact_id.invalid`, `contact_id.unknown`; on a payment `contact_id.required` (no contact and no transaction), `contact_id.mismatch` |
| `transaction_date`, `payment_date` | optional `Y-m-d`; `<field>.invalid`, `<field>.in_future` |
| `reference` | optional, at most 50; `reference.invalid`, `reference.too_long` |
| `lines` | 1 to 200; `lines.required`, `lines.too_many`, `lines.total_too_high`; a line that is not an object `line.invalid` |
| `lines.N.id` | required UUID, distinct; `id.required`, `id.invalid`, `id.duplicate`, `id.taken` |
| `lines.N.product_id` | required UUID of a product of the shop, distinct; `product_id.required`, `product_id.invalid`, `product_id.duplicate`, `product_id.unknown` |
| `lines.N.quantity` | JSON integer 1 to 999 999; `quantity.required`, `quantity.integer`, `quantity.too_low`, `quantity.too_high` |
| `lines.N.unit_price` | JSON integer 0 to 999 999 999; `unit_price.required`, `unit_price.integer`, `unit_price.negative`, `unit_price.too_high` |
| `direction` | required, `paid` or `received`; `direction.required`, `direction.invalid`, `direction.mismatch` |
| `transaction_id` | optional UUID of a transaction of the shop; `transaction_id.invalid`, `transaction_id.unknown`, `transaction_id.cancelled` |
| `amount` | JSON integer 1 to 999 999 999 999; `amount.required`, `amount.integer`, `amount.too_low`, `amount.too_high`, `amount.exceeds_left` |
| `method` | `cash`, `cheque`, `card`, `transfer`; `method.required`, `method.invalid` |
| cancellation `reason` | optional, nullable, at most 255 after trimming; `reason.invalid`, `reason.too_long` |
| `per_page`, `cursor` | as in 007; `per_page.invalid`, `cursor.invalid` |
