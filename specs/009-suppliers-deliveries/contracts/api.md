# API Contract: Suppliers and Deliveries

Base path `/api/v1`. JSON only. Every route here takes `Authorization: Bearer <token>` and needs a merchant who owns a shop (`shop.required`, 403, otherwise). Successful responses wrap their content in `data`. The common error shape and the codes of 006 and 007 still apply. The importable collection `shop-keeper-backend/postman/shop-keeper.postman_collection.json` gains the folders "Suppliers", "Deliveries" and "Supplier payments" and must match this file.

Ids are UUIDs made by the app, answered in lowercase. Money is integer millimes. Dates are `YYYY-MM-DD`; times are UTC ISO 8601 (`2026-11-20T09:30:00Z`).

## Change to 007's ShopProduct

`ShopProduct` gains `stock_quantity` (integer, may be below zero later): the sum of the product's stock movements, 0 for a product never delivered.

```json
{ "id": "<uuid>", "name": "Tomates", "sale_price": 1800, "purchase_price": 1200, "stock_quantity": 24, "catalog_product": null, "created_at": "…", "updated_at": "…" }
```

## Common shapes

**Supplier**:

```json
{ "id": "<uuid>", "name": "Grossiste Ben Ali", "phone": "+21698123456", "balance": 125000, "created_at": "…", "updated_at": "…" }
```

`phone` may be `null`. `balance` is what the merchant owes: non-cancelled delivery totals minus non-cancelled payments; it may be below zero.

**SupplierSummary**: `{ "id": "<uuid>", "name": "Grossiste Ben Ali" }`

**Delivery**:

```json
{
  "id": "<uuid>",
  "supplier": { "...": "SupplierSummary, or null" },
  "delivery_date": "2026-11-20",
  "reference": "BL-2041",
  "total": 54000,
  "line_count": 2,
  "cancelled_at": null,
  "created_at": "2026-11-20T09:30:00Z",
  "lines": [
    { "id": "<uuid>", "product_id": "<uuid>", "quantity": 24, "unit_price": 1200, "amount": 28800 }
  ],
  "payment": { "...": "SupplierPayment, or null" }
}
```

`lines` (in the order sent) and `payment` (the payment recorded with the delivery, if any) appear when one delivery is answered, not in lists. `cancelled_at` is the cancellation time, or `null`.

**SupplierPayment**:

```json
{ "id": "<uuid>", "supplier": { "...": "SupplierSummary" }, "delivery_id": null, "amount": 20000, "method": "cash", "payment_date": "2026-11-20", "reference": null, "cancelled_at": null, "created_at": "…" }
```

`method` is `cash`, `cheque`, `card` or `transfer`. `delivery_id` is set when the payment was recorded with a delivery.

**Page**: as in 007: `data`, `links`, `meta` with `next_cursor`; pass it back as `cursor`.

## Endpoints

| Method and path | Input | Success |
|-----------------|-------|---------|
| `GET /suppliers` | query `cursor`, `per_page` | 200 page of Supplier, by compared name |
| `POST /suppliers` | `id`, `name`, `phone` | 201 Supplier; 200 when the shop already has this `id` |
| `GET /suppliers/{supplier}` | | 200 Supplier |
| `PATCH /suppliers/{supplier}` | `name`, `phone` (at least one; `phone: null` clears it) | 200 Supplier |
| `GET /deliveries` | query `cursor`, `per_page`, `supplier_id` | 200 page of Delivery, latest `delivery_date` first |
| `POST /deliveries` | see below | 201 Delivery; 200 when the shop already has this `id` |
| `GET /deliveries/{delivery}` | | 200 Delivery |
| `POST /deliveries/{delivery}/cancellation` | `id` | 201 Delivery, now cancelled; 200 when it was already cancelled |
| `GET /supplier-payments` | query `cursor`, `per_page`, `supplier_id` | 200 page of SupplierPayment, latest `payment_date` first |
| `POST /supplier-payments` | `id`, `supplier_id`, `amount`, `method`, `payment_date`, `reference` | 201 SupplierPayment; 200 when the shop already has this `id` |
| `GET /supplier-payments/{payment}` | | 200 SupplierPayment |
| `POST /supplier-payments/{payment}/cancellation` | `id` | 201 SupplierPayment, now cancelled; 200 when it was already cancelled |

There is no route to change or delete a delivery, a line, a payment or a supplier (`request.method_not_allowed` or `request.not_found`).

A `supplier_id` filter naming a supplier of another shop, or no supplier, gives an empty page.

### Record a delivery

```json
{
  "id": "<uuid>",
  "supplier_id": "<uuid, optional>",
  "delivery_date": "2026-11-20",
  "reference": "BL-2041",
  "lines": [
    { "id": "<uuid>", "product_id": "<uuid>", "quantity": 24, "unit_price": 1200 },
    { "id": "<uuid>", "product_id": "<uuid>", "quantity": 10, "unit_price": 2520 }
  ],
  "payment": { "id": "<uuid>", "amount": 20000, "method": "cash", "reference": null }
}
```

- `delivery_date` is optional (today in the shop's time zone), never after today.
- `lines`: 1 to 200; a product at most once. The server computes `amount` and `total`.
- `payment` is optional and needs `supplier_id`. It is recorded as a SupplierPayment dated `delivery_date`.
- One wrong field records nothing. Field errors name the line: `"lines.1.quantity": ["quantity.too_low"]`, `"payment.method": ["method.invalid"]`.
- Effects, in one change: the delivery, its lines, one `delivery` stock movement per line (each product's `stock_quantity` grows), and the payment. Prices of products never change.
- Sending the same `id` again answers the recorded delivery with 200 and changes nothing, whatever the body.

### Cancel a delivery

Body `{ "id": "<uuid>" }`, the cancellation's id. Records a cancellation and one `delivery_cancellation` stock movement per line, taking back that line's quantity. The delivery's total stops counting in its supplier's balance. A payment recorded with the delivery is not cancelled.

### Cancel a payment

Body `{ "id": "<uuid>" }`. The payment stops counting in its supplier's balance.

## Error codes

| Code | HTTP | Meaning |
|------|------|---------|
| `shop.required` | 403 | Signed-in merchant has no shop |
| `supplier.not_found` | 404 | Supplier does not exist or belongs to another shop |
| `delivery.not_found` | 404 | Delivery does not exist or belongs to another shop |
| `supplier_payment.not_found` | 404 | Payment does not exist or belongs to another shop |
| `supplier.name_taken` | 409 | Another supplier of the shop has the same compared name; has `existing_supplier_id` |
| `validation.failed` | 422 | Field codes below |

```json
{ "error": { "code": "supplier.name_taken", "existing_supplier_id": "<uuid>" } }
```

Field codes: `id.required`, `id.invalid`, `id.taken`, `id.duplicate`, `id.prohibited`, `name.required`, `name.invalid`, `name.too_long`, `phone.invalid`, `fields.required`, `supplier_id.required`, `supplier_id.invalid`, `supplier_id.unknown`, `delivery_date.invalid`, `delivery_date.in_future`, `payment_date.invalid`, `payment_date.in_future`, `reference.invalid`, `reference.too_long`, `lines.required`, `lines.too_many`, `lines.total_too_high`, `line.invalid` (a line that is not an object), `product_id.required`, `product_id.invalid`, `product_id.duplicate`, `product_id.unknown`, `quantity.required`, `quantity.integer`, `quantity.too_low`, `quantity.too_high`, `unit_price.required`, `unit_price.integer`, `unit_price.negative`, `unit_price.too_high`, `payment.invalid`, `payment.supplier_required`, `amount.required`, `amount.integer`, `amount.too_low`, `amount.too_high`, `method.required`, `method.invalid`, `per_page.invalid`, `cursor.invalid`.

`id.taken` means the id is used by a record of another shop, or by another record of this shop (a line or payment id already used); it never says which.
