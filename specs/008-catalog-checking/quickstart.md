# Quickstart: Catalog Checking

How to prove the feature works. Endpoints and codes are in [contracts/api.md](./contracts/api.md); tables in [data-model.md](./data-model.md).

## Prerequisites

- The 006 and 007 setup: from `shop-keeper-backend/`, `.env` filled, `AUTH_TEST_MODE=true`, `docker compose up -d --build`.
- Migrate, seed and load the catalog:

```bash
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan catalog:load
```

The seeder makes `test@example.com` (password `password`) an operator outside production.

## Automated checks

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

All tests pass, covering for each endpoint success, validation, signed-out access and access by a non-operator (FR-023), the 007 tests updated for several barcodes, and a check that `linked_shops_count` always equals a fresh count.

## Postman journey

Import `shop-keeper-backend/postman/shop-keeper.postman_collection.json`, set `base_url`, and run it in order. Folder 6 signs in as the operator by itself. Expected: every request succeeds without edits (SC-007).

## Scenarios

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Three shops add the same unknown barcode with different names, one shop another | queue: first entry has 3 shops and the three names |
| 2 | A merchant calls the queue | 403 `operator.required` |
| 3 | Operator checks the first entry with a key and three names | 200, `verified`; a new shop looking the barcode up sees the names |
| 4 | Shops linked before the check list their products | own names and prices unchanged |
| 5 | Check the same product again | 409 `catalog_product.not_pending` |
| 6 | Check with `products.barcode_123`, a taken key, or a missing Arabic name | 422 `key.reserved`, `key.taken`, `names.ar.required`; nothing changes |
| 7 | Create brand `Délice`, give it to two milk products with formats 1 l and 500 ml | a shop searching `delice` gets both, with brand and format |
| 8 | Merge pending barcode X (shop A) into checked Y (shop B) | lookup of X returns Y; shop A linked to Y; `GET /catalog/products/{X}` says merged into Y |
| 9 | Merge when shop C is linked to both | C's product on X has no catalog link and keeps its name and prices |
| 10 | Merge Y into itself; a checked product into a pending one | 422 `merge.same_product`; 409 `merge.checked_into_pending` |
| 11 | `catalog:import` a 20-line file with 2 pending barcodes | 18 added, 2 checked in place; a second import changes nothing |
| 12 | Import a file with a bad barcode on line 7 | prints `line 7 (...): barcode.invalid`; catalog unchanged |
| 13 | `operator:revoke` the operator, call the queue | 403 `operator.required` |
| 14 | Read `catalog_changes` | one row per check, merge, brand change and import, with the operator, no typed names |
