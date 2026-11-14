# Data Model: Catalog Checking

Changes to the 007 tables (`specs/007-catalog-products/data-model.md`) and new tables. New catalog tables belong to the `Catalog` module; the `users` column belongs to `Identity`. 007 migrations are never edited: every change is a new migration.

## users (module Identity) — new column

| Column | Type | Rules |
|--------|------|-------|
| `operator_since` | timestamp, null | set by `operator:grant`, cleared by `operator:revoke` |

## global_products — changed

| Column | Change | Rules |
|--------|--------|-------|
| `barcode` | **removed** after its values move to `global_product_barcodes` | |
| `status` | new value `merged` | `verified`, `pending` or `merged` |
| `merged_into_id` | new, FK global_products, null | set only when `merged`; never another merged product |
| `brand_id` | new, FK brands, restrict delete, null | |
| `format_quantity` | new, unsigned int, null | 1 to 1 000 000; set with `format_unit` or both null |
| `format_unit` | new, string(5), null | `g`, `kg`, `ml`, `l`, `piece` |
| `linked_shops_count` | new, unsigned int, default 0 | number of shop products linked; kept by the actions (research R3) |
| `checked_by` | new, FK users, null-on-delete, null | operator who checked it through the queue |
| `checked_at` | new, timestamp, null | |

Index `(status, linked_shops_count, created_at, id)` for the queue.

**States**

```text
(none) --unknown barcode added by a shop----------------> pending
(none) --catalog file or import entry-------------------> verified
pending --operator check, file or import entry----------> verified   (same row and id)
pending --merged into another product-------------------> merged
verified --merged into another verified product---------> merged
```

`merged` is final in this feature. A merged product has no barcodes, no terms and no linked shop products.

## global_product_barcodes — new

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `global_product_id` | FK global_products, cascade delete | |
| `barcode` | string(13) | unique; normalized as in 007 research R4 |
| timestamps | | |

Index `(global_product_id)`. The migration copies every non-null `global_products.barcode`.

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
| `operator_id` | FK users, null-on-delete, null | null for a server command without `--operator` |
| `action` | string(20) | `check`, `correct`, `merge`, `brand_create`, `brand_rename`, `import`, `file_load` |
| `global_product_id` | FK global_products, null | the product checked, corrected or merged away |
| `brand_id` | FK brands, null | |
| `details` | JSON | ids and counts only, never typed names (for example `{ "merged_into_id": 12, "unlinked_shop_products": 1 }`) |
| `created_at` | timestamp | |

Never updated or deleted.

## shop_products — unchanged columns

A merge rewrites `global_product_id` (to the target, or to null for a shop linked to both). Name, prices, id and timestamps of the shop's own data do not change; `updated_at` does.

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
| `per_page` | 1 to 100, default 50 |

## Import file

UTF-8, comma-separated, header line:

```text
barcode,key,name_fr,name_ar,name_en,brand,format_quantity,format_unit,spellings_fr,spellings_ar,spellings_en
6191234567897,products.delice_milk_1l,Lait Délice 1 L,حليب ديليس 1 لتر,Délice milk 1 L,Délice,1,l,lait delice,7lib delice|حليب,
```

`barcode`, `key` and the three names are required; spellings are separated by `|`. The JSON catalog file of 007 gains optional `brand` (a name) and `format` (`{ "quantity", "unit" }`) per entry.
