# Quickstart: Shared Catalog and Shop Products

How to prove the feature works. Endpoints and codes are in [contracts/api.md](./contracts/api.md); tables in [data-model.md](./data-model.md).

## Prerequisites

- The 006 setup: from `shop-keeper-backend/`, `.env` filled and `docker compose up -d --build` running, with `AUTH_TEST_MODE=true` for the Postman journey.
- Load the catalog:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan catalog:load
```

Expected: the command prints at least 150 added products. Run it again: 0 added, 0 updated (SC-006).

## Automated checks

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

All tests pass, covering for each endpoint success, validation, unauthenticated access, a merchant with no shop and another shop's products (FR-021), plus unit tests for barcodes, compared names and close names.

## Postman journey

1. Import `shop-keeper-backend/postman/shop-keeper.postman_collection.json` (it replaces the 006 file).
2. Set `base_url` to `http://localhost:8000`.
3. Run the whole collection in order: the 006 requests, then "Products". Expected: every request succeeds without edits, ids and tokens carry over by themselves, and a second run succeeds too (SC-007).

## Scenarios

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Look up the random `619…` barcode | `status: unknown` |
| 2 | Create it with `name`, `language`, `sale_price` | 201; `catalog_product.status` is `pending`, `names` null |
| 3 | Look it up again | `status: pending`, `shop_product` set, no name shown |
| 4 | Second merchant looks it up | `status: pending`, `shop_product` null, no name (SC-005) |
| 5 | Second merchant creates it with a name | 201; same `catalog_product.id` as step 2 |
| 6 | Create the same barcode again in the first shop | 409 `product.already_in_shop` with `existing_product_id` |
| 7 | Same barcode with spaces; a 12-digit UPC-A and its `0…` EAN-13 (automated test) | same catalog product |
| 8 | Barcode with a wrong check digit; `2…` 13-digit code | 422 `barcode.invalid`; 422 `barcode.in_store`; nothing created |
| 9 | Suggestions for `tom`, `طما`, `tmat` | the catalog tomatoes in each answer |
| 10 | Create from that `catalog_product_id` with `language: fr`, no name | 201; name `Tomate` |
| 11 | Create an own product `tomates` | 409 `product.name_taken`, even with `confirm_different` |
| 12 | Create an own product `Tomates cerises` | 409 `product.name_close` with `matches`; resend with `confirm_different: true`: 201 |
| 13 | Resend step 12's request with the same `id` | 200, same product, nothing new |
| 14 | List, read, then change a price | the change is saved |
| 15 | Second merchant reads or changes a product of the first | 404 `product.not_found` (SC-004) |
| 16 | Merchant with no shop calls any product route | 403 `shop.required` |
| 17 | Edit `products.json` (new key, changed name), reload | new key added, changed key updated, shops' own names unchanged |
| 18 | Add an entry with step 2's barcode to a copy of the file and load it | the same catalog product is `verified`, both shops still linked |
| 19 | Load a file with a bad key or a missing Arabic name | load stops, prints the entry, catalog unchanged |

## Owner review

Before the feature closes, the owner reviews the first catalog list in `app/Modules/Catalog/database/catalog/products.json` (names in three languages and other spellings).
