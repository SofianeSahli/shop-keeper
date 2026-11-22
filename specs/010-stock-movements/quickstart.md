# Quickstart: Stock Movements and Low-Stock Warning

How to prove the feature works. Endpoints and codes: [contracts/api.md](./contracts/api.md); tables: [data-model.md](./data-model.md).

## Prerequisites

- The 006, 007 and 009 setup: from `shop-keeper-backend/`, `.env` filled, `docker compose up -d --build` running, `AUTH_TEST_MODE=true` for the Postman journey, `SHOP_TIME_ZONE=Africa/Tunis` (the default).
- New migrations only; existing data is kept:

```bash
docker compose exec app php artisan migrate
```

## Automated checks

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

## Postman journey

Import the collection again and run it in order: 006, "Products", "Contacts", "Purchases", "Payments", then "Stock" (SC-008).

## Scenarios

Product A and product B belong to the shop and have no movement.

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Opening stock: A 30, B 12 | 201; A `stock_quantity` 30, B 12 |
| 2 | Send step 1 again | 200, same adjustment; stocks unchanged |
| 3 | Opening stock for A with new ids | 422 `lines.0.product_id: product_id.opening_already_set`; nothing recorded |
| 4 | Loss of 2 of A, reason `broken`, note | 201; A 28 |
| 5 | Loss of 40 of B, reason `stolen` | 201; B −28 |
| 6 | Count A: counted 25, shown 28 | 201; line `quantity` −3; A 25 |
| 7 | Count A: counted 25, shown 25 | 201; line `quantity` 0; A 25 |
| 8 | Count A: counted 20, shown 25, after a purchase of 5 of A recorded since | line `quantity` −5; A 25 (the purchase still counts) |
| 9 | History of A | newest first: count −5 (after 25), purchase +5 (after 30), count 0, count −3, loss −2, opening +30 (after 30); newest `stock_after` equals A's `stock_quantity` |
| 10 | Set A's `low_stock_level` to 25; list low stock | A listed with its stock and level |
| 11 | Set B's level to 0; list low stock | B first (−28), then A |
| 12 | Clear A's level (`null`); list low stock | only B |
| 13 | Loss with reason `lost`, quantity 0, note of 300 characters | 422 `reason.invalid`, `quantity.too_low`, `note.too_long`; nothing recorded |
| 14 | Count with `counted_quantity` −1 and no `shown_quantity` | 422 `counted_quantity.negative`, `shown_quantity.required` |
| 15 | Any kind dated tomorrow; same product twice; 201 lines | 422 `adjustment_date.in_future`; `product_id.duplicate`; `lines.too_many` |
| 16 | Loss sent with the id of the count of step 6 | 422 `id.taken` |
| 17 | Second merchant: history of A, a loss on A, reuse of step 1's id | 404 `product.not_found`; 422 `product_id.unknown`; 422 `id.taken` |
| 18 | Merchant with no shop calls any route | 403 `shop.required` |
