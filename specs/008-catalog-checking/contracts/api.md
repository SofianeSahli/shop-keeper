# API Contract: Catalog Checking

Base path `/api/v1`. JSON only. Operator routes take `Authorization: Bearer <token>` of an account with the operator role and need no shop. The common error shape and the codes of 006 and 007 still apply. The collection `shop-keeper-backend/postman/shop-keeper.postman_collection.json` gains a folder "6. Operator catalog" and must match this file.

## Changed shape (also used by every 007 endpoint)

**CatalogProduct**:

```json
{
  "id": 12,
  "key": "products.delice_milk_1l",
  "status": "verified",
  "barcodes": ["6191234567897"],
  "names": { "fr": "Lait Délice 1 L", "ar": "حليب ديليس 1 لتر", "en": "Délice milk 1 L" },
  "brand": { "id": 3, "name": "Délice" },
  "format": { "quantity": 1, "unit": "l" },
  "merged_into_id": null
}
```

- `barcodes` replaces 007's `barcode`: a list, sorted, possibly empty.
- `status` is `verified`, `pending` or `merged`. `names` is `null` unless `verified`.
- `brand` and `format` may be `null`. `format.unit` is `g`, `kg`, `ml`, `l` or `piece`.
- `merged_into_id` is set only when `status` is `merged`; a merged product has empty `barcodes` and `names: null`.

**PendingEntry** (operators only):

```json
{
  "catalog_product": { "...": "CatalogProduct, status pending" },
  "typed_name": "Biscuit du quartier",
  "typed_language": "fr",
  "linked_shops_count": 3,
  "shop_names": ["Biscuit du quartier", "Choco", "Biscuit choco"],
  "first_added_at": "2026-11-07T10:15:00Z"
}
```

`shop_names` holds at most 20 distinct names, most common first.

**Brand**: `{ "id": 3, "name": "Délice", "spellings": ["delice", "ديليس"] }`

## Shop endpoint (merchant with a shop)

| Method and path | Success |
|-----------------|---------|
| `GET /catalog/products/{catalog_product}` | 200 CatalogProduct, merged ones included |

An unknown id answers `catalog_product.not_found` (404). Adding a merged product with `POST /products` and `catalog_product_id` answers `validation.failed` with `catalog_product_id.merged`.

## Operator endpoints

| Method and path | Body | Success |
|-----------------|------|---------|
| `GET /operator/catalog/pending` | query `cursor`, `per_page` | 200 page of PendingEntry, most linked shops first, then oldest |
| `POST /operator/catalog/products/{catalog_product}/check` | `key`, `names`; optional `spellings`, `brand_id`, `format` | 200 CatalogProduct, now `verified` |
| `PATCH /operator/catalog/products/{catalog_product}` | any of `key`, `names`, `spellings`, `brand_id`, `format` | 200 CatalogProduct |
| `POST /operator/catalog/products/{catalog_product}/merge` | `into_id` | 200 CatalogProduct (the product that stays) |
| `GET /operator/catalog/brands` | query `cursor`, `per_page` | 200 page of Brand, by name |
| `POST /operator/catalog/brands` | `name`; optional `spellings` | 201 Brand |
| `PATCH /operator/catalog/brands/{brand}` | `name` and/or `spellings` | 200 Brand |

`names` is `{ "fr", "ar", "en" }`; `spellings` is `{ "fr": [], "ar": [], "en": [] }` (each language optional) and replaces the stored spellings; `format` is `{ "quantity", "unit" }` or `null`; `brand_id` may be `null` to remove the brand.

The page shape is the 007 cursor page (`data`, `links`, `meta.next_cursor`).

## Server commands

| Command | Effect |
|---------|--------|
| `php artisan operator:grant {email}` | gives the account the operator role |
| `php artisan operator:revoke {email}` | removes it |
| `php artisan catalog:import {file} --operator=<email>` | imports a comma-separated file (data-model "Import file"), all or nothing |
| `php artisan catalog:load {file?} --operator=<email>` | 007 command; `--operator` is new and optional |

An unknown email or a wrong line exits non-zero and changes nothing; import errors print `line <n> (<key>): <reason>`.

## Error codes

| Code | HTTP | Meaning |
|------|------|---------|
| `operator.required` | 403 | Signed-in account is not an operator |
| `catalog_product.not_found` | 404 | Unknown catalog product id |
| `catalog_product.not_pending` | 409 | Check sent for a product already checked or merged (for example by another operator) |
| `catalog_product.not_verified` | 409 | Correction sent for a pending product; use check |
| `catalog_product.merged` | 409 | The product, or the merge target, is merged |
| `merge.same_product` | 422 | Merging a product into itself |
| `merge.checked_into_pending` | 409 | Merging a checked product into a pending one |
| `brand.not_found` | 404 | Unknown brand id |
| `brand.name_taken` | 409 | Another brand has the same compared name |

New field codes: `key.required`, `key.invalid`, `key.reserved`, `key.taken`, `names.required`, `names.fr.required`, `names.ar.required`, `names.en.required`, `names.<lang>.too_long`, `spellings.invalid`, `spellings.<lang>.too_long`, `brand_id.unknown`, `format.invalid`, `format.quantity.invalid`, `format.unit.invalid`, `into_id.required`, `into_id.unknown`, `name.required`, `name.too_long`, `catalog_product_id.merged`, `per_page.invalid`.
