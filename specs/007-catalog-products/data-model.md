# Data Model: Shared Catalog and Shop Products

All tables belong to the new module `Catalog` (research R1). Text columns use `utf8mb4`; derived compared columns use `utf8mb4_bin` (research R5).

## global_products

The shared catalog. No `shop_id`, no price, no stock.

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `translation_key` | string(100) | unique; `^products\.[a-z0-9_]+$` |
| `status` | string(10) | `verified` or `pending` (enum `CatalogStatus`) |
| `barcode` | string(13), null | unique; normalized EAN-13 or EAN-8 (research R4) |
| `pending_name` | string(120), null | set only while `pending`; never returned to any shop |
| `pending_language` | char(2), null | `fr`, `ar` or `en`; set only while `pending` |
| timestamps | | |

**States**

```text
(none) --unknown barcode added by a shop--> pending
(none) --catalog file entry--------------> verified
pending --catalog file entry with that barcode--> verified   (same row, same id; key replaced; pending_* cleared)
```

No other transition in this feature. A row is never deleted.

## global_product_names

One name per checked catalog product and language.

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `global_product_id` | FK global_products, cascade delete | |
| `language` | char(2) | `fr`, `ar`, `en` |
| `name` | string(120) | trimmed, kept exactly as written |
| timestamps | | |

Unique `(global_product_id, language)`. A `verified` row has exactly three names; a `pending` row has none.

## global_product_terms

Search rows, rebuilt from names and spellings whenever the catalog file changes a product. Never written by a shop.

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `global_product_id` | FK global_products, cascade delete | |
| `language` | char(2) | |
| `kind` | string(10) | `name` or `spelling` |
| `term` | string(120) | as written in the file |
| `compared` | string(255), `utf8mb4_bin` | compared form (research R5) |

Index `(compared)` for prefix search; index `(global_product_id)`. Only `verified` products have terms.

## shop_products

A shop's own product.

| Column | Type | Rules |
|--------|------|-------|
| `id` | binary(16) | primary key; client-generated UUID (research R3) |
| `shop_id` | FK shops, restrict delete | |
| `global_product_id` | FK global_products, restrict delete, null | set at creation, never changed |
| `name` | string(120) | required, trimmed, kept as written |
| `compared_name` | string(255), `utf8mb4_bin` | derived from `name` (research R5) |
| `identity_key` | string(255), `utf8mb4_bin` | `compared_name` without spaces |
| `sale_price` | unsigned bigint | millimes, 0 to 999 999 999 |
| `purchase_price` | unsigned bigint, null | millimes, 0 to 999 999 999 |
| timestamps | | |

- Unique `(shop_id, identity_key)`: same name twice in one shop (FR-017).
- Unique `(shop_id, global_product_id)`: same catalog product twice in one shop (FR-011).
- Index `(shop_id, compared_name, identity_key)`: list order and search. The list's tiebreaker is `identity_key`, unique in a shop, because a cursor cannot carry the binary `id`.
- No delete in this feature (FR-012). Stock and unit of sale come later.

`compared_name` and `identity_key` are set by the model on every save from `name`; no request writes them.

## Validation

| Field | Rule |
|-------|------|
| `id` | required UUID on create; `id.invalid`, `id.taken` (held by another shop) |
| `barcode` | optional; research R4; `barcode.invalid`, `barcode.in_store`; not with `catalog_product_id` |
| `catalog_product_id` | optional; a `verified` catalog product; `catalog_product_id.unknown`; not with `barcode` |
| `name` | at most 120 characters after trimming; only spaces counts as missing; required when there is no link, or when the barcode is unknown or pending |
| `language` | `fr`, `ar`, `en`; required when `name` is missing, or when the barcode is unknown to the catalog (FR-010, research R9) |
| `sale_price` | required on create; JSON integer, 0 to 999 999 999 |
| `purchase_price` | optional, nullable; same range |
| `confirm_different` | optional boolean, default false |
| `q` (suggestions) | required, at least 2 characters after trimming, at most 120 |
| `per_page` | integer 1 to 100, default 50 |
| update body | `name`, `sale_price`, `purchase_price`, `confirm_different` only, at least one of the first three; `id`, `barcode`, `catalog_product_id` are `<field>.prohibited` |

## Catalog file

`app/Modules/Catalog/database/catalog/products.json`:

```json
[
  {
    "key": "products.tomato",
    "names": { "fr": "Tomate", "ar": "طماطم", "en": "Tomato" },
    "spellings": { "fr": ["tomates"], "ar": ["طماطة", "tmatem", "tmatim"], "en": [] }
  },
  {
    "key": "products.example_biscuit",
    "barcode": "6191234567897",
    "names": { "fr": "…", "ar": "…", "en": "…" }
  }
]
```

`spellings` and each of its languages are optional. Load rules: research R10.
