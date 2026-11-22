# API Contract: Stock Movements and Low-Stock Warning

Base path `/api/v1`. JSON only. Every route takes `Authorization: Bearer <token>` and needs a merchant who owns a shop (`shop.required`, 403, otherwise). Successful responses wrap their content in `data`. The common error shape and the codes of 006, 007 and 009 still apply. The collection `shop-keeper-backend/postman/shop-keeper.postman_collection.json` gains the folder "8. Stock" and must match this file.

Ids are UUIDs made by the app, answered in lowercase. Quantities are whole units. Dates are `YYYY-MM-DD`; times are UTC ISO 8601. Account ids (`recorded_by`) are integers.

## Changes to 007's ShopProduct

`ShopProduct` gains `low_stock_level` (integer or `null`). `PATCH /products/{product}` accepts `low_stock_level`: an integer from 0 to 999 999 sets or changes it, `null` removes it. It counts as a changed field for `fields.required`. Changing it records no stock movement.

## Common shapes

**StockAdjustment**:

```json
{
  "id": "<uuid>",
  "kind": "count",
  "adjustment_date": "2026-11-21",
  "line_count": 1,
  "recorded_by": 1,
  "created_at": "2026-11-21T09:30:00Z",
  "lines": [
    { "id": "<uuid>", "product_id": "<uuid>", "quantity": -3, "counted_quantity": 17, "shown_quantity": 20, "reason": null, "note": null }
  ]
}
```

`kind` is `opening_stock`, `count` or `loss`. `lines` come in the order sent. `quantity` is the signed change to the stock: positive for opening stock, `counted_quantity − shown_quantity` for a count, negative for a loss. `counted_quantity` and `shown_quantity` are `null` except on a count; `reason` and `note` are `null` except on a loss.

**StockMovement** (history entry):

```json
{
  "id": "<uuid>",
  "kind": "loss",
  "quantity": -2,
  "stock_after": 8,
  "date": "2026-11-21",
  "recorded_by": 1,
  "recorded_at": "2026-11-21T10:05:00Z",
  "adjustment_id": "<uuid or null>",
  "transaction_id": "<uuid or null>",
  "counted_quantity": null,
  "shown_quantity": null,
  "reason": "broken",
  "note": "fell from the shelf"
}
```

`kind` is `purchase`, `purchase_cancellation`, `opening_stock`, `count` or `loss`. `stock_after` is the product's stock right after this movement, in the order the server recorded them. `adjustment_id` is set for opening stock, counts and losses; `transaction_id` for purchases and their cancellations. For a purchase cancellation, `date` is the day it was cancelled and `recorded_by` the account that cancelled it.

**Page**: as in 007: `data`, `links`, `meta` with `next_cursor`; pass it back as `cursor`.

## Endpoints

| Method and path | Input | Success |
|-----------------|-------|---------|
| `POST /stock/openings` | see below | 201 StockAdjustment; 200 when the shop already has this `id` |
| `POST /stock/counts` | see below | 201 StockAdjustment; 200 when the shop already has this `id` |
| `POST /stock/losses` | see below | 201 StockAdjustment; 200 when the shop already has this `id` |
| `GET /products/{product}/stock-movements` | query `cursor`, `per_page` | 200 page of StockMovement, newest first |
| `GET /products/low-stock` | query `cursor`, `per_page` | 200 page of ShopProduct whose `stock_quantity` ≤ `low_stock_level`, lowest stock first |
| `PATCH /products/{product}` (007, changed) | adds `low_stock_level` | 200 ShopProduct |

There is no route to change, delete or cancel an adjustment, a line or a movement.

### Common body

```json
{ "id": "<uuid>", "adjustment_date": "2026-11-21", "lines": [ { "id": "<uuid>", "product_id": "<uuid>", "...": "fields of the kind" } ] }
```

- `adjustment_date` optional (today in the shop's time zone), never after today.
- 1 to 200 lines; a product at most once.
- One wrong field records nothing; field errors name the line: `"lines.1.quantity": ["quantity.too_low"]`.
- Effects, in one change: the adjustment, its lines, one stock movement per line; each product's `stock_quantity` changes by the line's `quantity`. A movement is never refused for taking stock below zero.
- An `id` already used by this shop for an adjustment of the same kind answers that adjustment with 200 and records nothing. Used by another shop, or by this shop for another kind: `id.taken`.

### Opening stock: `POST /stock/openings`

Line fields: `quantity` (1 to 999 999). A product that already has an opening stock: `"lines.N.product_id": ["product_id.opening_already_set"]`; correct it with a count.

### Count: `POST /stock/counts`

Line fields: `counted_quantity` (0 to 999 999, what is on the shelf) and `shown_quantity` (−999 999 999 to 999 999 999, the stock the app showed when the merchant counted). The server records `counted_quantity − shown_quantity`, so movements that arrived since the count still count. A difference of 0 is recorded.

### Loss: `POST /stock/losses`

Line fields: `quantity` (1 to 999 999, the units lost), `reason` (`broken`, `expired`, `stolen`, `own_use`, `other`), optional `note` (at most 255).

## Error codes

| Code | HTTP | Meaning |
|------|------|---------|
| `shop.required` | 403 | Signed-in merchant has no shop |
| `product.not_found` | 404 | History of a product that does not exist or belongs to another shop |
| `validation.failed` | 422 | Field codes in data-model.md, Validation |

`id.taken` means the id is used by a record of another shop, or by another record of this shop; it never says which. Ids are checked among records of the same kind: an adjustment id among adjustments, a line id among adjustment lines.
