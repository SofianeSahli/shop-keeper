# Research: Catalog Checking

Decisions for `specs/008-catalog-checking/plan.md`. They build on the 007 code in `shop-keeper-backend/app/Modules/Catalog/` and on `specs/007-catalog-products/research.md`. Checked 2026-11-14.

## R1. Operator role

- **Decision**: `users.operator_since` (timestamp, null), owned by the Identity module. Two Identity commands: `operator:grant {email}` and `operator:revoke {email}`; an unknown email exits non-zero and changes nothing. An Identity route middleware, alias `operator`, answers `operator.required` (403) when the signed-in account is not an operator. Operator routes sit under `/api/v1/operator/catalog/...` with `auth:sanctum` then `operator`; they never use the `shop` middleware.
- **Rationale**: The role belongs to the account, so Identity owns it, as Shops owns the `shop` middleware (007 R2). A timestamp says since when, for the record. A command fits FR-001 and the assumption that there is no operator management endpoint.
- **Alternatives**: A roles table with RBAC: one role does not need it yet; it can replace the column when employees come (spec 014). Sanctum token abilities: the role must survive new sign-ins, so it lives on the account.

## R2. Several barcodes per catalog product

- **Decision**: A new table `global_product_barcodes` (`barcode` unique, `global_product_id`). A migration copies every `global_products.barcode` into it, then a second migration drops the column (007 migrations are never edited). Every 007 read and write of the barcode moves to the new table: `LookUpBarcode`, `FindOrCreatePendingProduct` (inserts the product and its barcode row in one savepoint), `CreateShopProduct`, `LoadCatalogFile`, the close-name rule "linked to a barcoded product is never held" (now: has at least one barcode), factories and tests.
- **Rationale**: FR-015, and a merge must keep both barcodes (US3). The unique index on the barcode table keeps "a barcode is unique across the catalog" (007 FR-001) and the 007 race handling.
- **Contract change**: `CatalogProduct.barcode` (string or null) becomes `barcodes` (list, sorted). No app reads it yet (the merchant app connects in 013), so the 007 contract and collection change with it.

## R3. Pending queue order and its counter

- **Decision**: `global_products.linked_shops_count` (unsigned int, default 0), kept in the same transaction as every change of a link: `CreateShopProduct` increments it when it links a product; a merge recomputes both products' counts. A migration fills it from `shop_products`. The queue is `GET /operator/catalog/pending`, cursor pages ordered by `linked_shops_count` desc, `created_at` asc, `id` asc, with an index on `(status, linked_shops_count, created_at, id)`.
- **Rationale**: The backend guide requires cursor pagination, and a cursor cannot order by an aggregate computed in the query. Links change in only two places (creation and merge), both in this module, so the counter stays exact. A test compares it with a fresh count after every story.
- **Alternatives**: Offset pages over a `COUNT` subquery: breaks the backend rule. A database trigger: hides a business rule outside the actions.

## R4. Queue entry

- **Decision**: Each entry shows the catalog product id, its barcodes, `pending_name`, `pending_language`, `linked_shops_count`, `created_at`, and `shop_names`: the distinct names of the shop products linked to it, at most 20, most common first. No shop id, owner or city is shown.
- **Rationale**: FR-005. The names are platform data (constitution VIII) and the operator needs them to choose the catalog names; who typed them is not needed. 20 is a named constant that keeps one entry small.

## R5. Check and correct

- **Decision**: `POST /operator/catalog/products/{catalog_product}/check` with `key`, `names` (fr, ar, en), optional `spellings`, `brand_id`, `format`. The action locks the row; if it is not pending it answers `catalog_product.not_pending` (409), so of two simultaneous checks exactly one applies (FR-010). `PATCH /operator/catalog/products/{catalog_product}` corrects a checked product with the same fields, all optional; a pending product answers `catalog_product.not_verified` (409), a merged one `catalog_product.merged` (409). Names and spellings are rewritten through 007's `ReplaceCatalogNames`.
- **Key rules**: the 007 key form, at most 100 characters, unique (`key.taken`), and not starting with `products.barcode_` (`key.reserved`). The same rules are added to `catalog:load` and the import.
- **Rationale**: One endpoint per state change keeps the race rule simple: check is the only transition from pending, under a row lock.

## R6. Merge

- **Decision**: `POST /operator/catalog/products/{catalog_product}/merge` with `into_id`. In one transaction the action locks both rows in id order (no deadlock between two opposite merges), then: refuses the same product (`merge.same_product`, 422), a merged source or target (`catalog_product.merged`, 409), and a checked source into a pending target (`merge.checked_into_pending`, 409); moves the source's barcode rows to the target; for each shop linked to both, removes the link of its product on the source (`global_product_id` null, name, prices and id unchanged); relinks every other source-linked shop product to the target; deletes the source's term rows so it is never suggested; sets the source `status = merged`, `merged_into_id = target`; recomputes both `linked_shops_count`. The source's names stay for the record.
- **Reading by id**: a new shop endpoint `GET /catalog/products/{catalog_product}` returns any catalog product; a merged one has `status: merged`, `merged_into_id`, no names. Adding a merged product by id answers `catalog_product_id.merged`.
- **Rationale**: FR-011 to FR-014. A shop product that loses its link stays a product of its own, so its later stock and sales keep pointing at the same id.
- **Pending into pending** is allowed: the target stays pending, with the source's barcodes and shops.

## R7. Brands

- **Decision**: Table `brands` (`id`, `name` string(80), `compared` unique, `utf8mb4_bin`, `spellings` JSON list). Brands are created and renamed by operators (`brand.name_taken`, 409); there is no delete. A catalog product has a nullable `brand_id`.
- **Search**: every checked product of a brand gets term rows of kind `brand` for the brand's name and spellings, rebuilt when the brand or the product's brand changes. `SuggestProducts` then finds products by brand with no change to its query; `FindCloseProducts` ignores terms of kind `brand`, so a shop's own "Délice …" product is not held for every Délice product.
- **Rationale**: FR-016, FR-019, and one search table as in 007 R6. Rewriting terms on a rename is an operator action on at most a few hundred products, in one transaction.

## R8. Formats

- **Decision**: `global_products.format_quantity` (unsigned int, null) and `format_unit` (string(5), null): `g`, `kg`, `ml`, `l`, `piece`. Both set or both null. Returned as `format: { "quantity": 500, "unit": "g" }` or `null`. Quantity from 1 to 1 000 000.
- **Rationale**: FR-017. Whole numbers in the shown unit keep it simple (`1 l`, `500 ml`); fractions such as 1.5 l are written `1500 ml`.

## R9. Change records

- **Decision**: Table `catalog_changes` (`id`, `operator_id` FK users null, `action` string(20), `global_product_id` null, `brand_id` null, `details` JSON with ids and counts only, `created_at`). Actions: `check`, `correct`, `merge`, `brand_create`, `brand_rename`, `import`, `file_load`. `checked_by` and `checked_at` also go on `global_products` for the queue's history. `catalog:load` and `catalog:import` take an optional `--operator=<email>`; without it the record has no operator (a server command).
- **Rationale**: FR-003 and the backend rule that logs hold no personal data: the record keeps ids, never typed names.

## R10. Import

- **Decision**: An operator command, `php artisan catalog:import {file} --operator=<email>`, reading UTF-8 comma-separated text with a header line: `barcode,key,name_fr,name_ar,name_en,brand,format_quantity,format_unit,spellings_fr,spellings_ar,spellings_en`. `barcode`, `key` and the three names are required; spellings are separated by `|`. Each line becomes a 007 `CatalogFileEntry` (extended with brand and format) and goes through the same `LoadCatalogFile` steps: validate everything, check against the database, then one transaction. An unknown brand name is created. Errors print `line <n> (<key>): <reason>`.
- **Rationale**: FR-020 to FR-022 with one loader. A command, like `catalog:load`, because the API takes JSON only and a supplier list can be thousands of lines; an upload endpoint can come with an operator app.
- **Barcode rule change from 007**: a file or import entry whose barcode is not yet on its key's product now adds that barcode (a product holds several, R2) instead of stopping with `barcode.changed`. A barcode held by another checked product still stops the load.
- **Open point for review**: if the owner wants operators without server access to import, an endpoint taking the CSV text in a JSON field is a small addition.

## R11. What shops see

- **Decision**: `CatalogProductResource` gains `barcodes`, `brand` (`{ id, name }` or null), `format`, and `merged_into_id` (null unless merged). Lookups, suggestions, shop products and close matches all use it.
- **Rationale**: FR-018 with one resource.

## R12. Postman

- **Decision**: A folder "6. Operator catalog" in `postman/shop-keeper.postman_collection.json`. `DatabaseSeeder` makes its seeded account (`test@example.com`, password `password`) an operator when the environment is not production. The folder signs in with the collection variables `operator_email` and `operator_password` (those defaults) and stores `operator_token`; then it adds a pending barcode from the shop of folder 2, lists the queue, creates a brand, checks the product, looks it up from the shop, adds a second pending barcode and merges it, then reads the merged product.
- **Rationale**: US7 and SC-007: after `migrate --seed`, nothing needs editing. Granting the role stays a server command (R1); the seeder just runs it for the local account.
