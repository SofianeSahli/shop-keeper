# API Contract: Catalog Checking

Base path `/api/v1`. JSON only. Operator routes take `Authorization: Bearer <token>` of an account with the operator role and need no shop. The common error shape and the codes of 006 and 007 still apply. The collection `shop-keeper-backend/postman/shop-keeper.postman_collection.json` gains a folder "6. Operator catalog" and must match this file.

## Changed shape (also used by every 007 endpoint)

**CatalogProduct** (fields added to 007's shape):

```json
{
  "id": 12,
  "key": "products.delice_milk_1l",
  "status": "verified",
  "barcode": "6191234567897",
  "names": { "fr": "Lait Délice 1 L", "ar": "حليب ديليس 1 لتر", "en": "Délice milk 1 L" },
  "brand": { "id": 3, "name": "Délice" },
  "format": { "quantity": 1, "unit": "l" },
  "merged_into_id": null
}
```

- `barcode` is unchanged from 007: a string or `null`, one at most.
- `status` is `verified`, `pending` or `merged`. `names` is `null` unless `verified`.
- `brand` and `format` may be `null`. `format.unit` is `g`, `kg`, `ml`, `l` or `piece`; `quantity` is a whole number.
- `merged_into_id` is set only when `status` is `merged`; a merged product has `barcode: null` and `names: null`.

**PendingEntry** (operators only):

```json
{
  "catalog_product": { "...": "CatalogProduct, status pending" },
  "typed_name": "Biscuit du quartier",
  "typed_language": "fr",
  "linked_shops_count": 3,
  "shops": [
    { "product_name": "Biscuit du quartier", "shop_name": "Epicerie Amira", "city": "Sousse" },
    { "product_name": "Choco", "shop_name": "Hanout Slim", "city": null }
  ],
  "first_added_at": "2026-11-07T10:15:00Z"
}
```

`shops` holds at most 20 entries, oldest link first. No owner name, email or phone.

**Brand**: `{ "id": 3, "name": "Délice", "spellings": ["delice", "ديليس"] }`

**ImportResult**: `{ "added": 18, "updated": 0, "unchanged": 2 }`

**ShopProduct** and **ShopProductSummary** (007) gain `removed_at`: `null` while on sale, or the time the merchant removed it, for example `"2026-11-20T08:30:00Z"`.

**ShopProduct** (007) also gains `name_language` (`fr`, `ar` or `en`) and `name_from_catalog` (`true` when the name is the catalog name in `name_language` and cannot be changed by the merchant: added from a checked barcode, linked to a pending product that was then checked, or brought by a merge with its barcode; products a merchant named keep `false`).

## Shop endpoint (merchant with a shop)

| Method and path | Success |
|-----------------|---------|
| `GET /catalog/products/{catalog_product}` | 200 CatalogProduct, merged ones included |

An unknown id answers `catalog_product.not_found` (404). Adding a merged product with `POST /products` and `catalog_product_id` answers `validation.failed` with `catalog_product_id.merged`.

## Removing a product from sale (merchant with a shop)

| Method and path | Body | Success |
|-----------------|------|---------|
| `POST /products/{product}/remove` | | 200 ShopProduct with `removed_at` set; already removed: same answer, nothing changes |
| `POST /products/{product}/restore` | | 200 ShopProduct with `removed_at: null`; already on sale: same answer, nothing changes |
| `GET /products?removed=true` | query `cursor`, `per_page` | 200 page of removed products only |

Changes to 007 endpoints:

- `GET /products` lists products on sale only.
- `GET /products/suggestions` and close matches (`product.name_close`) leave out removed products that have no catalog link. A catalog product whose shop product is removed is still suggested, with `shop_product.removed_at` set, so the app offers to put it back.
- `GET /products/{product}` and `GET /catalog/barcodes/{barcode}` still return a removed product, with `removed_at` set.
- `POST /products` answers `product.removed` with `existing_product_id` when the barcode or catalog product is that of a removed product of the shop, or when the merchant-chosen name matches a removed product's merchant-chosen name once compared.
- `PATCH /products/{product}` on a removed product answers `product.removed`.

Another shop's product answers `product.not_found` (404), as in 007.

## Names of products with a barcode (changes to 007)

| Request | Before (007) | Now |
|---------|--------------|-----|
| `POST /products` with a checked `barcode`, or the `catalog_product_id` of a checked product with a barcode | `name` optional, defaults to the catalog name | `language` required; `name` refused with `name.prohibited`; the name is the catalog name |
| `POST /products` with an unknown or pending `barcode` | `name` and `language` required | unchanged; `language` is kept as `name_language` |
| `PATCH /products/{product}` with `name`, product carrying a catalog name | allowed | 409 `product.name_from_catalog`; prices still change |
| Same compared name as another product of the shop | `product.name_taken` | only when both names were chosen by merchants |
| Pending barcode merged into a checked product that had no barcode | | the moved shop products take the catalog name; products already linked keep their merchants' names |

After a check, a correction, a merge or a catalog file or import, linked products with a barcode show the new catalog name in their `name_language`.

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
| `POST /operator/catalog/imports` | `csv` (the file's text, at most 1 MB) | 200 ImportResult |

`names` is `{ "fr", "ar", "en" }`; `spellings` is `{ "fr": [], "ar": [], "en": [] }` (each language optional) and replaces the stored spellings; `format` is `{ "quantity", "unit" }` or `null`; `brand_id` may be `null` to remove the brand. The page shape is the 007 cursor page (`data`, `links`, `meta.next_cursor`).

A wrong import line answers:

```json
{ "error": { "code": "import.invalid_line", "line": 7, "key": "products.x", "reason": "barcode.invalid" } }
```

## Server commands

| Command | Effect |
|---------|--------|
| `php artisan operator:grant {email}` | gives the account the operator role |
| `php artisan operator:revoke {email}` | removes it |
| `php artisan catalog:import {file} --operator=<email>` | imports a comma-separated file of any size, with the same rules as `POST /operator/catalog/imports` |
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
| `merge.both_have_barcodes` | 409 | Both products have a barcode; a product holds one at most |
| `brand.not_found` | 404 | Unknown brand id |
| `brand.name_taken` | 409 | Another brand has the same compared name |
| `import.too_large` | 413 | `csv` above 1 MB; use `catalog:import` |
| `import.invalid_line` | 422 | A line is wrong; has `line`, `key` and `reason` |
| `product.name_from_catalog` | 409 | Rename of a product whose name comes from the catalog (a checked barcode) |
| `product.removed` | 409 | The product, or the one the new product would duplicate, is removed from sale; has `existing_product_id`; put it back with `POST /products/{product}/restore` |

New field codes: `key.required`, `key.invalid`, `key.reserved`, `key.taken`, `names.required`, `names.fr.required`, `names.ar.required`, `names.en.required`, `names.<lang>.too_long`, `spellings.invalid`, `spellings.<lang>.too_long`, `brand_id.unknown`, `format.invalid`, `format.quantity.invalid`, `format.unit.invalid`, `into_id.required`, `into_id.unknown`, `name.required`, `name.too_long`, `csv.required`, `catalog_product_id.merged`, `name.prohibited`, `removed.invalid`, `per_page.invalid`.

Import reasons (in `reason` and in the command's output): `header.invalid`, `key.invalid`, `key.reserved`, `key.duplicate`, `name.missing`, `name.too_long`, `barcode.invalid`, `barcode.in_store`, `barcode.duplicate`, `barcode.taken`, `barcode.changed`, `key.taken`, `format.invalid`, `brand.too_long`.
