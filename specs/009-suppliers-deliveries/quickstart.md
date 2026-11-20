# Quickstart: Suppliers and Deliveries

How to prove the feature works. Endpoints and codes are in [contracts/api.md](./contracts/api.md); tables in [data-model.md](./data-model.md).

## Prerequisites

- The 006 and 007 setup: from `shop-keeper-backend/`, `.env` filled and `docker compose up -d --build` running, with `AUTH_TEST_MODE=true` for the Postman journey.
- Add `SHOP_TIME_ZONE=Africa/Tunis` to `.env` (it is in `.env.example`; the default is the same).
- Run the new migrations:

```bash
docker compose exec app php artisan migrate
```

## Automated checks

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

All tests pass, covering for each endpoint success, validation, unauthenticated access, a merchant with no shop and another shop's records (FR-020), plus the rules of stock, balance, cancellation and repeated requests.

## Postman journey

1. Import `shop-keeper-backend/postman/shop-keeper.postman_collection.json` again.
2. Run the whole collection in order: 006, "Products", then "Suppliers", "Deliveries" and "Supplier payments". Every request succeeds without edits, and ids carry over by themselves (SC-007).

## Scenarios

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Create supplier `Grossiste Ben Ali` with phone `+216 98 123 456` | 201; `phone` is `+21698123456`; `balance` 0 |
| 2 | Create `grossiste ben ali` | 409 `supplier.name_taken` with `existing_supplier_id` |
| 3 | Record a delivery from it: product A 24 × 1 200, product B 10 × 2 520, paid 20 000 cash | 201; `total` 54 000; `payment.amount` 20 000 |
| 4 | Read product A | `stock_quantity` 24; `purchase_price` unchanged |
| 5 | Read the supplier | `balance` 34 000 |
| 6 | Send step 3 again | 200, same delivery; stock still 24, balance still 34 000 |
| 7 | Delivery with a quantity 0 on line 2 | 422 `lines.1.quantity: quantity.too_low`; nothing recorded |
| 8 | Delivery dated tomorrow | 422 `delivery_date.in_future` |
| 9 | Delivery with a payment and no supplier | 422 `payment: payment.supplier_required` |
| 10 | Pay the supplier 50 000 by transfer | 201; `balance` −16 000 |
| 11 | Cancel that payment twice | 201 then 200; `balance` 34 000 |
| 12 | Cancel the delivery of step 3 | 201; `cancelled_at` set; product A `stock_quantity` 0; `balance` −20 000 (the cash payment still counts) |
| 13 | Cancel it again | 200; nothing changes |
| 14 | List deliveries; list them for the supplier; list payments | latest date first; filtered lists |
| 15 | Second merchant reads, cancels or uses the first shop's supplier, delivery, payment or product | 404 not found, or 422 `supplier_id.unknown` / `product_id.unknown`; nothing about the record shown (SC-004) |
| 16 | Merchant with no shop calls any of these routes | 403 `shop.required` |
