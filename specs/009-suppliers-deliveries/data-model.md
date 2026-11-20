# Data Model: Suppliers and Deliveries

Tables of the new modules `Purchasing` and `Inventory`, and one new column in Catalog (research R1 to R3). Every key a client creates is a UUID stored as `BINARY(16)`. Money is unsigned `BIGINT` millimes. No row of `deliveries`, `delivery_lines`, `delivery_cancellations`, `supplier_payments`, `supplier_payment_cancellations` or `stock_movements` is ever updated or deleted; they have `created_at` only.

## suppliers (Purchasing)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `name` | string(120) | required, trimmed, kept as written |
| `compared_name` | string(255), `utf8mb4_bin` | derived from `name` with 007's `NameComparison` |
| `identity_key` | string(255), `utf8mb4_bin` | `compared_name` without spaces |
| `phone` | string(16), null | E.164 (`+216…`), normalized |
| timestamps | | |

- Unique `(shop_id, identity_key)` (FR-002). Index `(shop_id, compared_name, identity_key)` for the list.
- Never deleted (FR-003). Name and phone may change.
- `balance` is not stored (research R6).

## deliveries (Purchasing)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `supplier_id` | binary(16), null, FK suppliers, restrict delete | a supplier of the same shop |
| `delivery_date` | date | default today in the shop's zone; not after today |
| `reference` | string(50), null | supplier's note number |
| `total` | unsigned bigint | sum of line amounts; at most 9 007 199 254 740 991 |
| `line_count` | unsigned smallint | 1 to 200 |
| `sort_key` | char(32), stored generated `lower(hex(id))` | list tiebreaker (research R7) |
| `created_at` | timestamp | |

Index `(shop_id, delivery_date, sort_key)`; index `(shop_id, supplier_id, delivery_date, sort_key)`.

## delivery_lines (Purchasing)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `delivery_id` | binary(16), FK deliveries, restrict delete | |
| `position` | unsigned smallint | order sent, from 1 |
| `shop_product_id` | binary(16), FK shop_products, restrict delete | a product of the same shop |
| `quantity` | unsigned int | 1 to 999 999 |
| `unit_price` | unsigned bigint | 0 to 999 999 999 |
| `amount` | unsigned bigint | `quantity × unit_price` |
| `created_at` | timestamp | |

Unique `(delivery_id, shop_product_id)`: a product once per delivery (FR-004). Unique `(delivery_id, position)`.

## delivery_cancellations (Purchasing)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `delivery_id` | binary(16), FK deliveries, restrict delete | unique: one cancellation per delivery |
| `created_at` | timestamp | the cancellation date shown on the delivery |

## supplier_payments (Purchasing)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `supplier_id` | binary(16), FK suppliers, restrict delete | required |
| `delivery_id` | binary(16), null, FK deliveries, restrict delete | set when recorded with a delivery; unique |
| `amount` | unsigned bigint | 1 to 999 999 999 999 |
| `method` | string(10) | `cash`, `cheque`, `card`, `transfer` (enum `PaymentMethod`) |
| `payment_date` | date | default today in the shop's zone; not after today; the delivery date when recorded with a delivery |
| `reference` | string(50), null | cheque or transfer number |
| `sort_key` | char(32), stored generated `lower(hex(id))` | |
| `created_at` | timestamp | |

Index `(shop_id, payment_date, sort_key)`; index `(shop_id, supplier_id, payment_date, sort_key)`.

## supplier_payment_cancellations (Purchasing)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `supplier_payment_id` | binary(16), FK supplier_payments, restrict delete | unique |
| `created_at` | timestamp | |

## stock_movements (Inventory)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; server UUID v7 |
| `shop_id` | FK shops, restrict delete | |
| `shop_product_id` | binary(16), FK shop_products, restrict delete | |
| `kind` | string(30) | `delivery`, `delivery_cancellation` (enum `StockMovementKind`); 010 adds more |
| `quantity` | int, signed | positive for `delivery`, negative for `delivery_cancellation` |
| `source_id` | binary(16) | the delivery line |
| `created_at` | timestamp | |

Unique `(kind, source_id)`. Index `(shop_id, shop_product_id)`.

## shop_products (Catalog, new column)

| Column | Type | Rules |
|--------|------|-------|
| `stock_quantity` | bigint, signed, default 0 | sum of the product's stock movements; changed only by `ChangeStockQuantities` (research R3) |

Added by a new migration; the 007 migration is not edited.

## States

```text
Delivery:  recorded --cancel--> cancelled          (a second cancel answers the cancelled delivery)
Payment:   recorded --cancel--> cancelled          (same)
Supplier:  no state; never deleted
```

## Balance

```text
balance(supplier) = Σ deliveries.total   where supplier_id = supplier and no delivery_cancellation
                  − Σ supplier_payments.amount where supplier_id = supplier and no payment cancellation
```

## Validation

| Field | Rule and field codes |
|-------|----------------------|
| `id` (every creation) | required UUID; `id.required`, `id.invalid`, `id.taken` |
| supplier `name` | required, at most 120 after trimming; `name.required`, `name.invalid`, `name.too_long` |
| supplier `phone` | optional, nullable; E.164 after removing spaces, dashes, brackets and a leading `00`; `phone.invalid` |
| `supplier_id` (delivery) | optional UUID of a supplier of the shop; `supplier_id.invalid`, `supplier_id.unknown` |
| `supplier_id` (payment) | required, same; `supplier_id.required` |
| `delivery_date`, `payment_date` | optional `Y-m-d`; `<field>.invalid`, `<field>.in_future` |
| `reference` | optional, at most 50; `reference.invalid`, `reference.too_long` |
| `lines` | 1 to 200; `lines.required`, `lines.too_many`, `lines.total_too_high` |
| `lines.N.id` | required UUID, distinct in the request; `id.required`, `id.invalid`, `id.duplicate`, `id.taken` |
| `lines.N.product_id` | required UUID of a product of the shop, distinct; `product_id.required`, `product_id.invalid`, `product_id.duplicate`, `product_id.unknown` |
| `lines.N.quantity` | JSON integer 1 to 999 999; `quantity.required`, `quantity.integer`, `quantity.too_low`, `quantity.too_high` |
| `lines.N.unit_price` | JSON integer 0 to 999 999 999; `unit_price.required`, `unit_price.integer`, `unit_price.negative`, `unit_price.too_high` |
| `payment` (delivery) | optional object; needs `supplier_id`: `payment.supplier_required`; `payment.invalid` if not an object |
| `amount` | JSON integer 1 to 999 999 999 999; `amount.required`, `amount.integer`, `amount.too_low`, `amount.too_high` |
| `method` | `cash`, `cheque`, `card`, `transfer`; `method.required`, `method.invalid` |
| `per_page`, `cursor` | as in 007; `per_page.invalid`, `cursor.invalid` |
| supplier update | `name`, `phone` only, at least one; `fields.required`; `id.prohibited` |
