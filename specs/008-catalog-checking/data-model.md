# Data Model: Catalog Checking

Changes to the 007 tables (`specs/007-catalog-products/data-model.md`) and new tables. New catalog tables belong to the `Catalog` module; the `users` column belongs to `Identity`. 007 migrations are never edited: every change is a new migration.

## users (module Identity) — new column

| Column | Type | Rules |
|--------|------|-------|
| `operator_since` | timestamp, null | set by `operator:grant`, cleared by `operator:revoke` |

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
| `checked_by` | new, FK users, null on delete, null | operator who checked it through the queue |
| `checked_at` | new, timestamp, null | |

Index `(status, linked_shops_count, created_at, id)` for the queue.

**States**

```text
(none) --unknown barcode added by a shop----------------> pending
(none) --catalog file or import entry-------------------> verified
pending --operator check, file or import entry----------> verified   (same row and id)
pending --merged into a product without a barcode------> merged
verified --merged into another verified product---------> merged     (not if both have a barcode)
```

`merged` is final in this feature. A merged product has no barcode, no terms and no linked shop products.

## global_product_terms — changed

| Column | Change |
|--------|--------|
| `kind` | new value `brand`: the brand's name and spellings, one row per product of that brand |

Close-name checks (007 FR-016) ignore `brand` rows; suggestions use them.

## brands — new

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `name` | string(80) | required, trimmed, kept as written |
| `compared` | string(255), `utf8mb4_bin` | unique; 007 compared form of `name` |
| `spellings` | JSON | list of strings, each at most 80 characters; default `[]` |
| timestamps | | |

No delete in this feature.

## catalog_changes — new

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `operator_id` | FK users, null on delete, null | null for a server command without `--operator` |
| `action` | string(20) | `check`, `correct`, `merge`, `brand_create`, `brand_rename`, `import`, `file_load` |
| `global_product_id` | FK global_products, null | the product checked, corrected or merged away |
| `brand_id` | FK brands, null | |
| `details` | JSON | ids and counts only, never typed names (for example `{ "merged_into_id": 12, "unlinked_shop_products": 1 }`) |
| `created_at` | timestamp | |

Never updated or deleted.

## shop_products — changed

| Column | Change | Rules |
|--------|--------|-------|
| `removed_at` | new, timestamp, null | set when the merchant removes the product from sale, cleared when they put it back; the row is never deleted |
| `name_language` | new, char(2), null | `fr`, `ar` or `en`: the language the product was added with; picks the catalog name to carry (research R14). The migration sets `fr` on existing rows |
| `name_from_catalog` | new, boolean, default false | true when the name is a copy of the catalog name (research R14); never set on products a merchant named |
| `name` | changed meaning | a copy of the catalog name in `name_language` when `name_from_catalog` is true, kept in step by the actions; otherwise the merchant's own name |
| `identity_key` | now nullable | `null` when `name_from_catalog` is true, so only names merchants choose are unique per shop (FR-037) |

Index `(shop_id, removed_at, compared_name, identity_key)` for the two lists (on sale, removed). The 007 unique indexes `(shop_id, identity_key)` and `(shop_id, global_product_id)` still include removed products (research R13).

**States**

```text
(none) --created by the merchant-------> on sale
on sale --removed from sale-------------> removed     (removed_at set)
removed --put back on sale--------------> on sale     (same id, name, prices, link)
```

A merge rewrites `global_product_id` (to the target, or to null for a shop linked to both), whatever the state. Name, prices and id do not change; `updated_at` does.

## shops (module Shops) — read through an action

The queue shows each linked shop's `name` and `city` through the Shops action `DescribeShops` (research R4). Catalog never reads the table.

## Validation

| Field | Rule |
|-------|------|
| `key` | `^products\.[a-z0-9_]+$`, at most 100; not starting with `products.barcode_` (`key.reserved`); unique (`key.taken`) |
| `names.fr`, `names.ar`, `names.en` | required on check; trimmed, 1 to 120 characters |
| `spellings.<lang>` | optional list of strings, each 1 to 120 characters |
| `brand_id` | null or an existing brand (`brand_id.unknown`) |
| `format` | null, or `quantity` integer 1 to 1 000 000 and `unit` in `g`, `kg`, `ml`, `l`, `piece` |
| `into_id` | required, an existing catalog product |
| brand `name` | required, trimmed, 1 to 80 characters, unique once compared (`brand.name_taken`) |
| brand `spellings` | optional list, each 1 to 80 characters |
| `csv` | required string, at most 1 MB (`import.too_large`) |
| `per_page` | 1 to 100, default 50 |

## Import file

UTF-8, comma-separated, header line:

```text
barcode,key,name_fr,name_ar,name_en,brand,format_quantity,format_unit,spellings_fr,spellings_ar,spellings_en
6191234567897,products.delice_milk_1l,Lait Délice 1 L,حليب ديليس 1 لتر,Délice milk 1 L,Délice,1,l,lait delice,7lib delice|حليب,
```

`barcode`, `key` and the three names are required; spellings are separated by `|`. The JSON catalog file of 007 gains optional `brand` (a name) and `format` (`{ "quantity", "unit" }`) per entry.
