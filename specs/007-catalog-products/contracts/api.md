# API Contract: Shared Catalog and Shop Products

Base path `/api/v1`. JSON only. Every route here takes `Authorization: Bearer <token>` and needs a merchant who owns a shop. The importable collection becomes `shop-keeper-backend/postman/shop-keeper.postman_collection.json` (renamed from `shop-keeper-006…`, research R14) and must match this file and the 006 contract.

Successful responses wrap their content in `data`. The common error shape, field codes and the codes of 006 still apply.

## Common shapes

**CatalogProduct**:

```json
{ "id": 12, "key": "products.tomato", "status": "verified", "barcode": null, "names": { "fr": "Tomate", "ar": "طماطم", "en": "Tomato" } }
```

`names` is `null` when `status` is `pending`. Apps identify a catalog product by `id`: its `key` changes when a pending product is checked.

**ShopProduct**:

```json
{
  "id": "0190f3a2-6b1c-7d4e-9a10-3c5e7f812b44",
  "name": "Tomates",
  "sale_price": 1800,
  "purchase_price": 1200,
  "catalog_product": { "...": "CatalogProduct, or null" },
  "created_at": "2026-11-07T10:15:00Z",
  "updated_at": "2026-11-07T10:15:00Z"
}
```

Prices are integer millimes. `purchase_price` may be `null`.

**ShopProductSummary**: `{ "id": "<uuid>", "name": "Tomates", "sale_price": 1800 }`

**Suggestion** (also used for close matches):

```json
{ "catalog_product": { "...": "CatalogProduct, or null" }, "shop_product": { "...": "ShopProductSummary, or null" } }
```

At least one side is set. Both are set when the shop already has that catalog product. `shop_product` alone is a product of the shop's own with no catalog link.

**Error with details** (adds to the 006 error shape):

```json
{ "error": { "code": "product.name_taken", "existing_product_id": "<uuid>" } }
{ "error": { "code": "product.name_close", "matches": [ { "...": "Suggestion" } ] } }
```

## Endpoints

| Method and path | Input | Success |
|-----------------|-------|---------|
| `GET /catalog/barcodes/{barcode}` | | 200 Barcode lookup |
| `GET /products/suggestions` | query `q` | 200 `{ "data": [Suggestion] }`, at most 10, best first |
| `POST /products` | see below | 201 ShopProduct; 200 ShopProduct when the shop already has this `id` |
| `GET /products` | query `cursor`, `per_page` | 200 page of ShopProduct |
| `GET /products/{product}` | | 200 ShopProduct |
| `PATCH /products/{product}` | `name`, `sale_price`, `purchase_price`, `confirm_different` | 200 ShopProduct |

`GET /catalog/barcodes/{barcode}` and `GET /products/suggestions` share a limit of 120 requests per minute per account.

### Barcode lookup

```json
{ "data": { "barcode": "6191234567897", "status": "verified", "catalog_product": { "...": "CatalogProduct" }, "shop_product": null } }
```

`barcode` is the stored form (spaces removed, UPC-A with a leading `0`). `status` is `unknown` (then `catalog_product` is `null`), `verified` or `pending`. `shop_product` is the shop's ShopProductSummary for this catalog product, or `null`. An invalid or in-store barcode answers `validation.failed` with `fields.barcode`.

### Create a product

Body: `id` (client UUID) and `sale_price` always; optional `purchase_price`, `name`, `language`, `confirm_different`; then one of three forms:

| Form | Link | `name` | `language` | Duplicate rules |
|------|------|--------|------------|-----------------|
| `barcode` | catalog product of that barcode; a `pending` one is created if unknown | optional only if the barcode is `verified` | required if `name` is missing or the barcode is unknown | `product.already_in_shop`, `product.name_taken`; never held |
| `catalog_product_id` | that `verified` catalog product | optional | required if `name` is missing | `product.already_in_shop`, `product.name_taken`; never held |
| neither | none | required | | `product.name_taken`; `product.name_close` unless `confirm_different` is `true` |

A missing `name` takes the catalog name in `language`.

### Change a product

`name`, `sale_price`, `purchase_price` (send `null` to clear), at least one; `confirm_different` optional. A new name follows `product.name_taken`, and `product.name_close` unless confirmed when the product has no catalog link or its catalog product has no barcode. The catalog link and the `id` cannot change.

### List

```json
{ "data": [ { "...": "ShopProduct" } ], "links": { "first": null, "last": null, "prev": null, "next": "<url>" }, "meta": { "path": "<url>", "per_page": 50, "next_cursor": "<cursor>", "prev_cursor": null } }
```

Ordered by compared name (case, accents and Arabic variants ignored), then by the name without spaces, which is unique in a shop. Pass `next_cursor` as `cursor` for the next page; a cursor that is not a string answers `cursor.invalid`.

## Error codes

| Code | HTTP | Meaning |
|------|------|---------|
| `shop.required` | 403 | Signed-in merchant has no shop |
| `product.not_found` | 404 | Product does not exist or belongs to another shop |
| `product.already_in_shop` | 409 | Shop already has this catalog product; has `existing_product_id` |
| `product.name_taken` | 409 | Same compared name as another product of the shop, even with `confirm_different`; has `existing_product_id` |
| `product.name_close` | 409 | Name close to existing products; nothing created; has `matches` (up to 5); resend with `confirm_different: true` |

New field codes: `id.invalid`, `id.taken`, `id.prohibited`, `barcode.invalid`, `barcode.in_store`, `barcode.prohibited`, `catalog_product_id.unknown`, `catalog_product_id.prohibited`, `name.required`, `name.too_long`, `language.required`, `language.invalid`, `sale_price.required`, `sale_price.integer`, `sale_price.negative`, `sale_price.too_high`, the same four for `purchase_price`, `confirm_different.boolean`, `q.required`, `q.too_short`, `q.too_long`, `per_page.invalid`, `fields.required` (empty update).
