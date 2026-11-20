# Data Model: Catalog Checking

Changes to the 007 tables (`specs/007-catalog-products/data-model.md`) and new tables, all in the `Catalog` module. No other module's table changes: there is no operator column on `users` (spec Clarifications, 2026-11-19). 007 migrations are never edited: every change is a new migration.

## global_products — changed

| Column | Change | Rules |
|--------|--------|-------|
| `barcode` | unchanged | one at most, unique (clarified 2026-11-16); a merge may move it to the product that stays |
| `status` | new value `merged` | `verified`, `pending` or `merged` |
| `merged_into_id` | new, FK global_products, null | set only when `merged`; never another merged product |
| `brand_id` | new, FK brands, restrict delete, null | |
| `format_quantity` | new, unsigned int, null | 1 to 1 000 000; set with `format_unit` or both null |
| `format_unit` | new, string(5), null | `g`, `kg`, `ml`, `l`, `piece` |
| `linked_shops_count` | new, unsigned int, default 0 | number of shop products on sale linked to it; kept by the actions (research R3, R13) |
| `checked_at` | new, timestamp, null | set when a pending product becomes `verified` |

Index `(status, linked_shops_count, created_at, id)` for the queue.

**States**

```text
(none) --unknown barcode added by a shop----------------> pending
(none) --catalog file entry------------------------------> verified
pending --catalog file entry with its barcode-----------> verified   (same row and id)
pending --merged into a product without a barcode------> merged
verified --merged into another verified product---------> merged     (not if both have a barcode)
```

`merged` is final in this feature. A merged product has no barcode, no terms and no linked shop products, and a catalog file entry may not use its key (`key.merged`).

## global_product_terms — changed

| Column | Change |
|--------|--------|
| `kind` | new value `brand`: the brand's name and spellings, one row per product of that brand |

Close-name checks (007 FR-016) ignore `brand` rows; suggestions use them.

## brands — new

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `key` | string(100), unique | `^brands\.[a-z0-9_]+$`; the brands file matches on it |
| `name` | string(80) | required, trimmed, kept as written |
| `compared` | string(255), `utf8mb4_bin` | unique; 007 compared form of `name` |
| `spellings` | JSON | list of strings, each at most 80 characters; default `[]` |
| timestamps | | |

Written only by `catalog:load` from the brands file. No delete in this feature.

## catalog_changes — new

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `source` | string(30) | the command: `catalog:load` or `catalog:merge` |
| `action` | string(20) | `check`, `correct`, `merge`, `brand_create`, `brand_rename` |
| `global_product_id` | FK global_products, null | the product checked, corrected or merged away |
| `brand_id` | FK brands, null | |
| `details` | JSON | ids and counts only, never typed names (for example `{ "merged_into_id": 12, "unlinked_shop_products": 1 }`) |
| `created_at` | timestamp | |

Never updated or deleted. No person is recorded; the git history of the catalog files records who changed names (research R9).

## shop_products — changed

| Column | Change | Rules |
|--------|--------|-------|
| `removed_at` | new, timestamp, null | set when the merchant removes the product from sale, cleared when they put it back; the row is never deleted |
| `name_language` | new, char(2), null | `fr`, `ar` or `en`: the language the product was added with; picks the catalog name to carry (research R14). The migration sets `fr` on existing rows |
| `name_from_catalog` | new, boolean, default false | true when the name is a copy of the catalog name (research R14); never set on products a merchant named |
| `name` | changed meaning | a copy of the catalog name in `name_language` when `name_from_catalog` is true, kept in step by the actions; otherwise the merchant's own name |
| `identity_key` | now nullable | `null` when `name_from_catalog` is true, so only names merchants choose are unique per shop (FR-028) |

Index `(shop_id, removed_at, compared_name, identity_key)` for the two lists (on sale, removed). The 007 unique indexes `(shop_id, identity_key)` and `(shop_id, global_product_id)` still include removed products (research R13).

**States**

```text
(none) --created by the merchant-------> on sale
on sale --removed from sale-------------> removed     (removed_at set)
removed --put back on sale--------------> on sale     (same id, name, prices, link)
```

A merge rewrites `global_product_id` (to the target, or to null for a shop linked to both), whatever the state. Name, prices and id do not change; `updated_at` does.

## Catalog file — changed

`app/Modules/Catalog/database/catalog/products.json`, a JSON list as in 007. Each entry gains an optional `brand` (a brand key) and `format`:

```json
{ "key": "products.delice_milk_1l", "barcode": "6191234567897",
  "names": { "fr": "Lait Délice 1 L", "ar": "حليب ديليس 1 لتر", "en": "Délice milk 1 L" },
  "spellings": { "fr": ["lait delice"], "ar": ["7lib delice"] },
  "brand": "brands.delice", "format": { "quantity": 1, "unit": "l" } }
```

## Brands file — new

`app/Modules/Catalog/database/catalog/brands.json`, a JSON list:

```json
[
  { "key": "brands.delice", "name": "Délice", "spellings": ["delice", "ديليس"] }
]
```

## Validation (catalog files and commands)

| Field | Rule | Reason code |
|-------|------|-------------|
| entry `key` | `^products\.[a-z0-9_]+$`, at most 100 | `key.invalid` |
| entry `key` | not starting with `products.barcode_` | `key.reserved` |
| entry `key` | not used by another catalog product (007 matching rules), once per file | `key.taken`, `key.duplicate` |
| entry `key` | not the key of a merged product | `key.merged` |
| entry `names.fr`, `.ar`, `.en` | required; trimmed, 1 to 120 characters | `name.missing`, `name.too_long` |
| entry `spellings.<lang>` | optional list of strings, each 1 to 120 characters | `spelling.invalid`, `spelling.missing`, `spelling.too_long` |
| entry `barcode` | 007 rules | `barcode.invalid`, `barcode.in_store`, `barcode.duplicate`, `barcode.taken`, `barcode.changed` |
| entry `brand` | null or a brand key in the brands file or stored | `brand.unknown` |
| entry `format` | null, or `quantity` integer 1 to 1 000 000 and `unit` in `g`, `kg`, `ml`, `l`, `piece` | `format.invalid` |
| brands file | a JSON list of objects | `brands_file.invalid` |
| brand `key` | `^brands\.[a-z0-9_]+$`, at most 100, once per file | `brand.key.invalid`, `brand.key.duplicate` |
| brand `name` | required, trimmed, 1 to 80 characters, unique once compared | `brand.name.missing`, `brand.name.too_long`, `brand.name_taken` |
| brand `spellings` | optional list, each 1 to 80 characters | `brand.spelling.too_long` |
| `catalog:pending --limit` | integer 1 to 100, default 50 | `limit.invalid` |
| `catalog:pending --cursor` | a cursor printed by a previous page | `cursor.invalid` |
| `catalog:merge {from} {into}` | positive integers, existing products | `id.invalid`, `catalog_product.not_found` |
