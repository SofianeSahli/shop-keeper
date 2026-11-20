# API Contract: Catalog Checking

Base path `/api/v1`. JSON only. Every endpoint here is a merchant endpoint: `Authorization: Bearer <token>` of an account with a shop. No endpoint changes the shared catalog: checking and merging are server commands (end of this file). The common error shape and the codes of 006 and 007 still apply. Folder 5 of the collection `shop-keeper-backend/postman/shop-keeper.postman_collection.json` gains the new requests and must match this file.

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

**ShopProduct** and **ShopProductSummary** (007) gain `removed_at`: `null` while on sale, or the time the merchant removed it, for example `"2026-11-20T08:30:00Z"`.

**ShopProduct** (007) also gains:
- `name_language`: `fr`, `ar` or `en`.
- `name_from_catalog`: `true` when the name is the catalog name in `name_language` and the merchant cannot change it. That is the case for a product added from a checked barcode, one linked to a pending product that was then checked, or one brought by a merge with its barcode. Products a merchant named keep `false`.

## Reading a catalog product

| Method and path | Success |
|-----------------|---------|
| `GET /catalog/products/{catalog_product}` | 200 CatalogProduct, merged ones included |

An unknown id answers `catalog_product.not_found` (404). Adding a merged product with `POST /products` and `catalog_product_id` answers `validation.failed` with `catalog_product_id.merged`.

## Removing a product from sale

| Method and path | Body | Success |
|-----------------|------|---------|
| `POST /products/{product}/remove` | | 200 ShopProduct with `removed_at` set; already removed: same answer, nothing changes |
| `POST /products/{product}/restore` | | 200 ShopProduct with `removed_at: null`; already on sale: same answer, nothing changes |
| `GET /products?removed=true` | query `cursor`, `per_page` | 200 page of removed products only |

Changes to 007 endpoints:

- `GET /products` lists products on sale only.
- `GET /products/suggestions` and close matches (`product.name_close`) leave out removed products that have no catalog link. A catalog product whose shop product is removed is still suggested, with `shop_product.removed_at` set, so the app offers to put it back.
- `GET /products/{product}` and `GET /catalog/barcodes/{barcode}` still return a removed product, with `removed_at` set.
- `POST /products` answers `product.removed` with `existing_product_id` in three cases: the barcode or the catalog product is that of a removed product of the shop, or the merchant-chosen name matches a removed product's merchant-chosen name once compared.
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

After a check, a correction or a merge, linked products with a barcode show the new catalog name in their `name_language`.

## Error codes

| Code | HTTP | Meaning |
|------|------|---------|
| `catalog_product.not_found` | 404 | Unknown catalog product id |
| `product.name_from_catalog` | 409 | Rename of a product whose name comes from the catalog (a checked barcode) |
| `product.removed` | 409 | The product, or the one the new product would duplicate, is removed from sale; has `existing_product_id`; put it back with `POST /products/{product}/restore` |

New field codes: `catalog_product_id.merged`, `name.prohibited`, `removed.invalid`.

## Server commands (not part of the API)

Run on the server with `docker compose exec app php artisan <command>`. They print no shop name, city or id and no merchant name, email or phone. On any error they print the reason, exit non-zero and change nothing.

| Command | Effect | Prints |
|---------|--------|--------|
| `catalog:pending {--limit=50} {--cursor=}` | Lists pending products, most shops first, then oldest. Read only. | One block per product: id, barcode, shop count, first added date, typed name and language, up to 20 different names shops gave (never which shop). Then the command for the next page, or `No product waiting to be checked.` |
| `catalog:load {file?} {--brands=}` | 007 command. Also loads the brands file first (default `app/Modules/Catalog/database/catalog/brands.json`). Entries may carry `brand` and `format`. An entry with a pending barcode checks that product in place. | `Catalog loaded: <a> added, <u> updated, <n> unchanged; brands: <a> added, <u> updated; <s> shop product names synced.` |
| `catalog:merge {from} {into} {--yes}` | Merges catalog product `from` into `into`. Without `--yes`, it shows both products and asks for confirmation. | `Merged <from> into <into>: barcode moved, <r> shop products relinked, <u> unlinked, <s> names synced.` |

Refusal messages:
- `catalog:load`: `Catalog not loaded, nothing changed. entry <n> (<key>): <reason>`. For the brands file, `brand <n> (<key>): <reason>`.
- The other commands: `Nothing changed. <reason>`.

Reasons:

- `catalog:load`:
  - 007 reasons: `file.invalid`, `entry.invalid`, `key.invalid`, `key.duplicate`, `key.taken`, `name.missing`, `name.too_long`, `spelling.invalid`, `spelling.missing`, `spelling.too_long`, `barcode.invalid`, `barcode.in_store`, `barcode.duplicate`, `barcode.taken`, `barcode.changed`;
  - new reasons: `key.reserved`, `key.merged`, `format.invalid`, `brand.unknown`, `brands_file.invalid`, `brand.key.invalid`, `brand.key.duplicate`, `brand.name.missing`, `brand.name.too_long`, `brand.name_taken`, `brand.spelling.too_long`.
- `catalog:pending`: `limit.invalid`, `cursor.invalid`.
- `catalog:merge`: `id.invalid`, `catalog_product.not_found`, `merge.same_product`, `catalog_product.merged`, `merge.checked_into_pending`, `merge.both_have_barcodes`, `merge.cancelled`.
