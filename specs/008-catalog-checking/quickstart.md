# Quickstart: Catalog Checking

How to prove the feature works. Endpoints, commands and codes are in [contracts/api.md](./contracts/api.md); tables and file formats in [data-model.md](./data-model.md).

## Prerequisites

- The 006 and 007 setup: from `shop-keeper-backend/`, `.env` filled, `AUTH_TEST_MODE=true`, `docker compose up -d --build`.
- Migrate, seed and load the catalog:

```bash
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan catalog:load
```

Existing 007 shop products get `name_language = fr` from the migration; the pilot has no production data, so French as the default is safe.

## Automated checks

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

The volume checks (plan Performance Goals) run on their own: `docker compose exec app php artisan test --group=volume`.

All tests pass. They cover:
- each endpoint: success, validation, signed-out access, no shop and another shop (FR-036);
- each command: success, every refusal, and no change on failure;
- no route writes the catalog (SC-005);
- no command prints shop or merchant data (SC-009);
- the 007 tests, unchanged except for the new CatalogProduct and ShopProduct fields;
- a check that `linked_shops_count` always equals a fresh count.

## Catalog commands

Everything below runs on the server; there is no account role and no admin endpoint. Whoever can run these commands is trusted.

### 1. See what is waiting

```bash
docker compose exec app php artisan catalog:pending
docker compose exec app php artisan catalog:pending --limit=20 --cursor=<value printed by the previous page>
```

Each product prints its id, barcode, number of shops, first added date, the name and language the first shop typed, and the different names shops gave it. It never shows which shop gave which name, and never a shop's name or city.

### 2. Check or correct products, brands and formats

Edit the files in a branch and open a pull request:

- `app/Modules/Catalog/database/catalog/products.json`: add an entry with the pending product's `barcode`, a `key` (`products.…`, never `products.barcode_…`), `names` in `fr`, `ar` and `en`, and optionally `spellings`, `brand` (a brand key) and `format` (`{ "quantity": 500, "unit": "g" }`). To correct a product, change its entry.
- `app/Modules/Catalog/database/catalog/brands.json`: add or rename brands (`{ "key": "brands.delice", "name": "Délice", "spellings": [] }`).

Once merged and deployed, load both files:

```bash
docker compose exec app php artisan catalog:load
```

Expected: `Catalog loaded: … added, … updated, … unchanged; brands: … added, … updated; … shop product names synced.` On any error: `Catalog not loaded, nothing changed. entry <n> (<key>): <reason>` and nothing in the database changes.

### 3. Merge two catalog products

```bash
docker compose exec app php artisan catalog:merge <from id> <into id>
```

The command shows both products and asks for confirmation; `--yes` skips the question. Expected: `Merged <from> into <into>: …`. On any refusal: `Nothing changed. <reason>`.

## Postman journey

Import `shop-keeper-backend/postman/shop-keeper.postman_collection.json`, set `base_url`, and run it in order. Folder 5 reads a catalog product, tries to rename a product with a catalog name, removes a product from sale, lists removed products and puts it back. Expected: every request succeeds without edits (SC-007).

## Scenarios

| # | Scenario | Expected |
|---|----------|----------|
| 1 | Three shops add the same unknown barcode with different names, one shop another; run `catalog:pending` | first entry has 3 shops and the three names; no shop name, city or id, no owner details |
| 2 | `catalog:pending --limit=0`, then `--cursor=nonsense` | `Nothing changed. limit.invalid`, then `cursor.invalid`; non-zero exit |
| 3 | Add a catalog file entry for the first entry's barcode with a key and three names; `catalog:load` | `1 updated`; the product is `verified` with the same id; a new shop looking the barcode up sees the names |
| 4 | Shops linked before the check list their products | prices unchanged; each now shows the catalog name in the language it was added with |
| 5 | Load the same files again | everything `unchanged`; no change record added |
| 6 | Load an entry with `products.barcode_123`, a taken key, a missing Arabic name, an unknown brand, or `format` `{ "quantity": 1.5, "unit": "l" }` | `key.reserved`, `key.taken`, `name.missing`, `brand.unknown`, `format.invalid`; nothing changes |
| 7 | Add brand `Délice` to the brands file, give it to two milk entries with formats 1 l and 500 ml, load | a shop searching `delice` gets both, with brand and format |
| 8 | Rename `Délice` in the brands file, load | every product of the brand shows the new name; suggestions find it by the new name only |
| 9 | `catalog:merge X Y --yes`: pending barcode X (shop A) into checked Y without a barcode (shop B) | lookup of X's barcode returns Y; shop A linked to Y; `GET /catalog/products/{X}` says merged into Y |
| 10 | Merge when shop C is linked to both | C's product on X has no catalog link and keeps its name and prices |
| 11 | Merge Y into itself; a checked product into a pending one; two barcoded products; an unknown id; without `--yes` in a non-interactive run | `merge.same_product`; `merge.checked_into_pending`; `merge.both_have_barcodes`; `catalog_product.not_found`; `merge.cancelled`; nothing changes |
| 12 | Load a catalog entry that uses X's key after the merge | `key.merged`; nothing changes |
| 13 | Read `catalog_changes` | one row per check, correction, merge and brand change, with its source command, no typed names |
| 14 | Merchant removes a product from sale | 200 with `removed_at`; gone from `GET /products`, suggestions and the queue's names; listed by `GET /products?removed=true` |
| 15 | Merchant scans the removed product's barcode, then adds it | lookup shows it with `removed_at`; adding answers 409 `product.removed` with `existing_product_id` |
| 16 | Merchant changes the removed product's price | 409 `product.removed` |
| 17 | Merchant puts it back | 200, same id, name and prices; back in the list; the queue counts the shop again for a pending product |
| 18 | Merchant adds a checked barcode with `language: ar` | name is the Arabic catalog name, `name_from_catalog: true`; sending `name` too gives `name.prohibited` |
| 19 | Merchant renames that product | 409 `product.name_from_catalog`; a price change still works |
| 20 | Merchant adds a pending barcode with a typed name, renames it, then the barcode is checked through the catalog file | the rename works while pending; after the load the product shows the catalog name in its language |
| 21 | Correct the French name of a checked barcoded product in the catalog file, load | shops that added it in French show the new name; a loose product like `Tomates du marché` never changes |
| 22 | Merge a pending barcode into checked `Tomates` (no barcode) that shops named `Tomates du marché` | the moved shop products take the catalog name; `Tomates du marché` keeps its name |
