# Quickstart: Contacts, Transactions and Payments (purchases)

How to prove the feature works. Endpoints and codes: [contracts/api.md](./contracts/api.md); tables: [data-model.md](./data-model.md).

## Prerequisites

- The 006 and 007 setup: from `shop-keeper-backend/`, `.env` filled, `docker compose up -d --build` running, `AUTH_TEST_MODE=true` for the Postman journey, `SHOP_TIME_ZONE=Africa/Tunis` (the default).
- The first 009 version's tables are gone, so rebuild the local database:

```bash
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan catalog:load
```

## Automated checks

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

## Postman journey

Import the collection again and run it in order: 006, "Products", then "Contacts", "Purchases", "Payments" (SC-007).

## Scenarios

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Create contact `Grossiste Ben Ali`, supplier, phone `+216 98 123 456` | 201; `phone` `+21698123456`; `balance` 0 |
| 2 | Create `grossiste ben ali` | 409 `contact.name_taken` with `existing_contact_id` |
| 3 | Mark it also as customer; list contacts with `role=customer` | it appears |
| 4 | Purchase from it: product A 24 × 1 200, product B 10 × 2 520 | 201; `total` 54 000; `payment_state` `unpaid` |
| 5 | Read product A | `stock_quantity` 24; prices unchanged |
| 6 | Send step 4 again | 200, same purchase; stock still 24 |
| 7 | Pay 20 000 cash linked to the purchase | 201; purchase `amount_left` 34 000, `partly_paid`; contact `balance` 34 000 |
| 8 | Pay 40 000 linked to the purchase | 422 `amount.exceeds_left` |
| 9 | Pay 10 000 on account (no `transaction_id`) | 201; purchase unchanged; contact `balance` 24 000 |
| 10 | Pay 34 000 linked to the purchase | `paid`; contact `balance` −10 000 |
| 11 | Cancel the purchase | 409 `transaction.has_payments` |
| 12 | Cancel the two linked payments with a reason, then the purchase | 201 each; cancellations show reason and account; product A `stock_quantity` 0; contact `balance` −10 000 |
| 13 | Cancel the purchase again | 200; nothing changes |
| 14 | Purchase with no contact, then pay it | payment has `contact` null and the `transaction_id` |
| 15 | Payment with neither contact nor transaction | 422 `contact_id.required` |
| 16 | `kind` `sale` | 422 `kind.invalid` |
| 17 | Purchase dated tomorrow; quantity 0 on line 2 | 422 `transaction_date.in_future`; `lines.1.quantity: quantity.too_low`; nothing recorded |
| 18 | List transactions, by contact; list payments, by transaction | latest date first; filtered |
| 19 | Second merchant reads, cancels or uses the first shop's contact, transaction, payment or product | 404, or 422 `…unknown`; nothing revealed |
| 20 | Merchant with no shop calls any route | 403 `shop.required` |
