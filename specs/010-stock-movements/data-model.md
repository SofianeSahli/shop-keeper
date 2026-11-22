# Data Model: Stock Movements and Low-Stock Warning

Module `Inventory` (two new tables, one column on `stock_movements`) and module `Catalog` (one column on `shop_products`). See research R1 to R11. Keys the app creates are UUIDs stored as `BINARY(16)`. No row of `stock_adjustments`, `stock_adjustment_lines` or `stock_movements` is ever updated or deleted; they have `created_at` only.

## stock_adjustments (Inventory, new)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID |
| `shop_id` | FK shops, restrict delete | |
| `kind` | string(20) | `opening_stock`, `count`, `loss` (enum `StockAdjustmentKind`) |
| `adjustment_date` | date | default today in the shop's zone; not after today |
| `line_count` | unsigned smallint | 1 to 200 |
| `created_by` | FK users, restrict delete | the signed-in account |
| `created_at` | timestamp | |

Index `(shop_id, adjustment_date)`.

## stock_adjustment_lines (Inventory, new)

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client UUID; the `source_id` of its movement |
| `stock_adjustment_id` | binary(16), FK stock_adjustments, restrict delete | |
| `position` | unsigned smallint | order sent, from 1 |
| `shop_product_id` | binary(16), FK shop_products, restrict delete | a product of the adjustment's shop |
| `quantity` | int, signed | the movement's quantity: `+quantity` for `opening_stock`, `counted − shown` for `count`, `−quantity` for `loss` |
| `counted_quantity` | unsigned int, null | `count` only: 0 to 999 999 |
| `shown_quantity` | int, null | `count` only: −999 999 999 to 999 999 999 |
| `loss_reason` | string(10), null | `loss` only: `broken`, `expired`, `stolen`, `own_use`, `other` (enum `LossReason`) |
| `note` | string(255), null | `loss` only; trimmed |
| `opening_product_id` | binary(16), null, **unique** | `opening_stock` only: equals `shop_product_id` (research R5) |
| `created_at` | timestamp | |

Unique `(stock_adjustment_id, shop_product_id)`, `(stock_adjustment_id, position)`, `(opening_product_id)`.

No `shop_id`: a line is reached only through its adjustment, as transaction lines in 009 (009 research R16).

## stock_movements (Inventory, changed by a new migration)

Columns from 009 unchanged. Added:

| Column | Type | Rules |
|--------|------|-------|
| `sequence` | unsigned bigint, AUTO_INCREMENT, unique | server order of recording (research R8) |

Added index `(shop_product_id, sequence)`.

`kind` (enum `StockMovementKind`) gains `opening_stock`, `count`, `loss`. `source_id` is a `stock_adjustment_lines.id` for those kinds, a `transaction_lines.id` for `purchase` and `purchase_cancellation`.

| Kind | Source | Quantity |
|------|--------|----------|
| `purchase` (009) | transaction line | + line quantity |
| `purchase_cancellation` (009) | transaction line | − line quantity |
| `opening_stock` | adjustment line | + quantity (1 to 999 999) |
| `count` | adjustment line | counted − shown (may be 0) |
| `loss` | adjustment line | − quantity (1 to 999 999) |

## shop_products (Catalog, changed by a new migration)

| Column | Type | Rules |
|--------|------|-------|
| `low_stock_level` | unsigned int, null | 0 to 999 999; null means no level. A setting: may be updated. |

`stock_quantity` still changes only through `ChangeStockQuantities`.

## Computed figures

```text
stock(product)              = Σ stock_movements.quantity where shop_product_id = p
stock_after(movement m)     = Σ stock_movements.quantity where shop_product_id = m.product and sequence ≤ m.sequence
is_low(product)             = low_stock_level IS NOT NULL and stock_quantity ≤ low_stock_level
```

History details of a movement:

| Kind | Date | Account | Link | Extra |
|------|------|---------|------|-------|
| `opening_stock`, `count`, `loss` | adjustment's `adjustment_date` | adjustment's `created_by` | `adjustment_id` | `counted_quantity`, `shown_quantity` (count); `reason`, `note` (loss) |
| `purchase` | transaction's date | transaction's recorder | `transaction_id` | |
| `purchase_cancellation` | cancellation's day in the shop's zone | account that cancelled | `transaction_id` | |

Purchase details come from Transactions through `PurchaseLineSources` (research R9).

## Validation

| Field | Rule and field codes |
|-------|----------------------|
| `id` (adjustment) | required UUID; `id.required`, `id.invalid`, `id.taken` (another shop, or another kind in this shop) |
| `adjustment_date` | optional `Y-m-d`; `adjustment_date.invalid`, `adjustment_date.in_future` |
| `lines` | 1 to 200; `lines.required`, `lines.too_many`; a line that is not an object `line.invalid` |
| `lines.N.id` | required UUID, distinct; `id.required`, `id.invalid`, `id.duplicate`, `id.taken` |
| `lines.N.product_id` | required UUID of a product of the shop, distinct; `product_id.required`, `product_id.invalid`, `product_id.duplicate`, `product_id.unknown`; opening stock only: `product_id.opening_already_set` |
| `lines.N.quantity` (opening stock, loss) | JSON integer 1 to 999 999; `quantity.required`, `quantity.integer`, `quantity.too_low`, `quantity.too_high` |
| `lines.N.counted_quantity` (count) | JSON integer 0 to 999 999; `counted_quantity.required`, `counted_quantity.integer`, `counted_quantity.negative`, `counted_quantity.too_high` |
| `lines.N.shown_quantity` (count) | JSON integer −999 999 999 to 999 999 999; `shown_quantity.required`, `shown_quantity.integer`, `shown_quantity.too_low`, `shown_quantity.too_high` |
| `lines.N.reason` (loss) | `broken`, `expired`, `stolen`, `own_use`, `other`; `reason.required`, `reason.invalid` |
| `lines.N.note` (loss) | optional, nullable, at most 255 after trimming; `note.invalid`, `note.too_long` |
| `low_stock_level` (product update) | JSON integer 0 to 999 999, or `null` to clear; `low_stock_level.integer`, `low_stock_level.negative`, `low_stock_level.too_high` |
| `per_page`, `cursor` | as in 007; `per_page.invalid`, `cursor.invalid` |

A field of another kind (for example `counted_quantity` on a loss) is ignored.
