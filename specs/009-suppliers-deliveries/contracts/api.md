# API Contract: Contacts, Transactions and Payments (purchases)

Base path `/api/v1`. JSON only. Every route takes `Authorization: Bearer <token>` and needs a merchant who owns a shop (`shop.required`, 403, otherwise). Successful responses wrap their content in `data`. The common error shape and the codes of 006 and 007 still apply. The collection `shop-keeper-backend/postman/shop-keeper.postman_collection.json` gains the folders "5. Contacts", "6. Purchases" and "7. Payments" and must match this file. "Cancel the purchase" is the last request of "7. Payments", after its payment is cancelled.

Ids are UUIDs made by the app, answered in lowercase. Money is integer millimes. Dates are `YYYY-MM-DD`; times are UTC ISO 8601. Account ids (`recorded_by`, `cancelled_by`) are integers.

## Change to 007's ShopProduct

`ShopProduct` gains `stock_quantity` (integer): the sum of the product's stock movements, 0 for a product never bought.

## Common shapes

**Contact**:

```json
{ "id": "<uuid>", "name": "Grossiste Ben Ali", "phone": "+21698123456", "address": null, "note": null, "is_supplier": true, "is_customer": false, "balance": 24000, "created_at": "…", "updated_at": "…" }
```

`balance` is what the shop owes the contact (below zero: what the contact owes the shop).

**ContactSummary**: `{ "id": "<uuid>", "name": "Grossiste Ben Ali" }`

**Cancellation**: `{ "reason": "wrong supplier", "cancelled_by": 1, "cancelled_at": "2026-11-21T10:05:00Z" }`, or `null`.

**Transaction**:

```json
{
  "id": "<uuid>",
  "kind": "purchase",
  "contact": { "...": "ContactSummary, or null" },
  "transaction_date": "2026-11-21",
  "reference": "BL-2041",
  "total": 54000,
  "amount_paid": 20000,
  "amount_left": 34000,
  "payment_state": "partly_paid",
  "line_count": 2,
  "recorded_by": 1,
  "cancellation": null,
  "created_at": "2026-11-21T09:30:00Z",
  "lines": [ { "id": "<uuid>", "product_id": "<uuid>", "quantity": 24, "unit_price": 1200, "amount": 28800 } ],
  "payments": [ { "...": "Payment" } ]
}
```

`payment_state` is `unpaid`, `partly_paid` or `paid`. `lines` (in the order sent) and `payments` (newest first) appear when one transaction is answered, not in lists.

**Payment**:

```json
{ "id": "<uuid>", "direction": "paid", "contact": { "...": "ContactSummary, or null" }, "transaction_id": "<uuid or null>", "amount": 20000, "method": "cash", "payment_date": "2026-11-21", "reference": null, "recorded_by": 1, "cancellation": null, "created_at": "…" }
```

**Page**: as in 007: `data`, `links`, `meta` with `next_cursor`; pass it back as `cursor`.

## Endpoints

| Method and path | Input | Success |
|-----------------|-------|---------|
| `GET /contacts` | query `cursor`, `per_page`, `role` | 200 page of Contact, by compared name |
| `POST /contacts` | `id`, `name`, `phone`, `address`, `note`, `is_supplier`, `is_customer` | 201 Contact; 200 when the shop already has this `id` |
| `GET /contacts/{contact}` | | 200 Contact |
| `PATCH /contacts/{contact}` | any of `name`, `phone`, `address`, `note`, `is_supplier`, `is_customer` (`null` clears an optional text) | 200 Contact |
| `GET /transactions` | query `cursor`, `per_page`, `kind`, `contact_id` | 200 page of Transaction, latest date first |
| `POST /transactions` | see below | 201 Transaction; 200 when the shop already has this `id` |
| `GET /transactions/{transaction}` | | 200 Transaction |
| `POST /transactions/{transaction}/cancellation` | `id`, `reason` | 201 Transaction, now cancelled; 200 when it was already cancelled |
| `GET /payments` | query `cursor`, `per_page`, `contact_id`, `transaction_id` | 200 page of Payment, latest date first |
| `POST /payments` | see below | 201 Payment; 200 when the shop already has this `id` |
| `GET /payments/{payment}` | | 200 Payment |
| `POST /payments/{payment}/cancellation` | `id`, `reason` | 201 Payment, now cancelled; 200 when it was already cancelled |

There is no route to change or delete a transaction, a line, a payment or a contact. A filter naming another shop's contact or transaction gives an empty page.

### Record a purchase

```json
{
  "id": "<uuid>",
  "kind": "purchase",
  "contact_id": "<uuid, optional>",
  "transaction_date": "2026-11-21",
  "reference": "BL-2041",
  "lines": [
    { "id": "<uuid>", "product_id": "<uuid>", "quantity": 24, "unit_price": 1200 },
    { "id": "<uuid>", "product_id": "<uuid>", "quantity": 10, "unit_price": 2520 }
  ]
}
```

- `kind` must be `purchase` in this feature (`kind.invalid` otherwise).
- `transaction_date` optional (today in the shop's time zone), never after today.
- One wrong field records nothing; field errors name the line: `"lines.1.quantity": ["quantity.too_low"]`.
- Effects, in one change: the transaction, its lines, one `purchase` stock movement per line (each product's `stock_quantity` grows). Product prices never change.
- Paid at once: send a payment linked to it right after.

### Record a payment

```json
{ "id": "<uuid>", "direction": "paid", "contact_id": "<uuid, optional>", "transaction_id": "<uuid, optional>", "amount": 20000, "method": "cash", "payment_date": "2026-11-21", "reference": null }
```

- At least one of `contact_id` and `transaction_id` (`contact_id.required`).
- With `transaction_id`: the transaction's contact (taken from it when `contact_id` is absent; `contact_id.mismatch` when different), `direction` `paid` for a purchase (`direction.mismatch`), not cancelled (`transaction_id.cancelled`), `amount` at most its `amount_left` (`amount.exceeds_left`).
- Without `transaction_id`: on account with the contact; may take the balance below zero.

### Cancel a transaction or a payment

Body `{ "id": "<uuid>", "reason": "optional text, at most 255" }`, the cancellation's id. A purchase's cancellation records one `purchase_cancellation` stock movement per line. A transaction with payments that are not cancelled is refused with `transaction.has_payments` (409).

## Error codes

| Code | HTTP | Meaning |
|------|------|---------|
| `shop.required` | 403 | Signed-in merchant has no shop |
| `contact.not_found` | 404 | Contact does not exist or belongs to another shop |
| `transaction.not_found` | 404 | Same for a transaction |
| `payment.not_found` | 404 | Same for a payment |
| `contact.name_taken` | 409 | Another contact of the shop has the same compared name; has `existing_contact_id` |
| `transaction.has_payments` | 409 | Cancel its payments first |
| `validation.failed` | 422 | Field codes in data-model.md, Validation |

```json
{ "error": { "code": "contact.name_taken", "existing_contact_id": "<uuid>" } }
```

`id.taken` means the id is used by a record of another shop, or by another record of this shop; it never says which. Ids are checked among records of the same kind: a line id among lines, a cancellation id among the cancellations of transactions, or of payments.
