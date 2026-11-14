# Research: Catalog Checking

Decisions for `specs/008-catalog-checking/plan.md`. They build on the 007 code in `shop-keeper-backend/app/Modules/Catalog/` and on `specs/007-catalog-products/research.md`. Written 2026-11-14, revised 2026-11-16 after the clarify session (spec "Clarifications").

## R1. Operator role

- **Decision**: `users.operator_since` (timestamp, null), owned by the Identity module. Two Identity commands: `operator:grant {email}` and `operator:revoke {email}`; an unknown email exits non-zero and changes nothing. An Identity route middleware, alias `operator`, answers `operator.required` (403) when the signed-in account is not an operator. Operator routes sit under `/api/v1/operator/catalog/...` with `auth:sanctum` then `operator`; they never use the `shop` middleware.
- **Rationale**: The role belongs to the account, so Identity owns it, as Shops owns the `shop` middleware (007 R2). A timestamp says since when. A command fits FR-001 and the assumption that there is no operator management endpoint.
- **Alternatives**: A roles table with RBAC: one role does not need it yet; it can replace the column when employees come (spec 014). Sanctum token abilities: the role must survive new sign-ins, so it lives on the account.

## R2. One barcode per catalog product (clarified 2026-11-16)

- **Decision**: Keep 007's `global_products.barcode` (unique, nullable). No barcode table, no change to 007's barcode code paths, and `CatalogProduct.barcode` stays a string or `null` in every answer.
- **Effect on merges**: merging two products that both have a barcode is refused with `merge.both_have_barcodes` (409). When only the merged-away product has one, the merge clears it on that product, then sets it on the product that stays, in the same transaction, so the unique index never sees it twice.
- **Rationale**: The owner chose one barcode at most. A manufacturer's new code for the same product stays a second catalog product (spec Edge Cases).
- **Alternatives**: A barcode table with several codes per product: rejected by the owner.

## R3. Pending queue order and its counter

- **Decision**: `global_products.linked_shops_count` (unsigned int, default 0), kept in the same transaction as every change of a link: `CreateShopProduct` increments it when it links a product; a merge recomputes both products' counts. A migration fills it from `shop_products`. The queue is `GET /operator/catalog/pending`, cursor pages ordered by `linked_shops_count` desc, `created_at` asc, `id` asc, with an index on `(status, linked_shops_count, created_at, id)`.
- **Rationale**: The backend guide requires cursor pagination, and a cursor cannot order by an aggregate computed in the query. Links change in only two places (creation and merge), both in this module, so the counter stays exact. A test compares it with a fresh count.
- **Alternatives**: Offset pages over a `COUNT` subquery: breaks the backend rule. A database trigger: hides a business rule outside the actions.

## R4. Queue entry and the shops it shows (clarified 2026-11-16)

- **Decision**: Each entry shows the catalog product (with its barcode), `pending_name`, `pending_language`, `linked_shops_count`, `created_at`, and `shops`: for each linked shop product, at most 20, oldest link first, `{ "product_name", "shop_name", "city" }`. No shop id, owner name, email or phone.
- **Where shop data comes from**: the Catalog module must not read `shops` (backend guide). A new Shops action, `DescribeShops`, takes shop ids and returns `{ id: { name, city } }`; the queue calls it once per page with the page's shop ids.
- **Rationale**: FR-005 and FR-006. The owner wants the operator able to contact a shop about an odd product, so the shop's name and city are shown; personal contact details are not, since the shop's public name and city are enough to find it. Data stays inside the platform (constitution VIII). Constitution IV forbade any user to reach another shop's data; version 3.2.0 (2026-11-16) makes the platform operator role the one exception, limited to this data and to operator endpoints (analyze finding C1).

## R5. Check and correct

- **Decision**: `POST /operator/catalog/products/{catalog_product}/check` with `key`, `names` (fr, ar, en), optional `spellings`, `brand_id`, `format`. The action locks the row; if it is not pending it answers `catalog_product.not_pending` (409), so of two simultaneous checks exactly one applies (FR-010). `PATCH /operator/catalog/products/{catalog_product}` corrects a checked product with the same fields, all optional; a pending product answers `catalog_product.not_verified` (409), a merged one `catalog_product.merged` (409). Names and spellings are rewritten through 007's `ReplaceCatalogNames`.
- **Key rules**: the 007 key form, at most 100 characters, unique (`key.taken`), and not starting with `products.barcode_` (`key.reserved`). The same reserved-prefix rule is added to `catalog:load` and the import.
- **Rationale**: One endpoint per state change keeps the race rule simple: check is the only transition from pending, under a row lock.

## R6. Merge

- **Decision**: `POST /operator/catalog/products/{catalog_product}/merge` with `into_id`. In one transaction the action locks both rows in id order (no deadlock between two opposite merges), then: refuses the same product (`merge.same_product`, 422), a merged source or target (`catalog_product.merged`, 409), a checked source into a pending target (`merge.checked_into_pending`, 409) and two barcodes (`merge.both_have_barcodes`, 409); moves the source's barcode, if any (R2); for each shop linked to both, removes the link of its product on the source (`global_product_id` null, name, prices and id unchanged, as clarified); relinks every other source-linked shop product to the target; deletes the source's term rows so it is never suggested; sets the source `status = merged`, `merged_into_id = target`; recomputes both `linked_shops_count`. The source's names stay for the record.
- **Reading by id**: a new shop endpoint `GET /catalog/products/{catalog_product}` returns any catalog product; a merged one has `status: merged`, `merged_into_id`, no names. Adding a merged product by id answers `catalog_product_id.merged`.
- **Rationale**: FR-011 to FR-014. A shop product that loses its link stays a product of its own, so its later stock and sales keep pointing at the same id.
- **Pending into pending** is allowed only when at most one has a barcode, which in practice means never (every pending product has one); the rule needs no special case.

## R7. Brands

- **Decision**: Table `brands` (`id`, `name` string(80), `compared` unique `utf8mb4_bin`, `spellings` JSON list). Brands are created and renamed by operators (`brand.name_taken`, 409); there is no delete. A catalog product has a nullable `brand_id`.
- **Search**: every checked product of a brand gets term rows of kind `brand` for the brand's name and spellings, rebuilt when the brand or the product's brand changes. `SuggestProducts` then finds products by brand with no change to its query; `FindCloseProducts` ignores terms of kind `brand`, so a shop's own "Délice …" product is not held for every Délice product.
- **Rationale**: FR-016, FR-019, and one search table as in 007 R6. Rewriting terms on a rename is an operator action on at most a few hundred products, in one transaction.

## R8. Formats (clarified 2026-11-16)

- **Decision**: `global_products.format_quantity` (unsigned int, null) and `format_unit` (string(5), null): `g`, `kg`, `ml`, `l`, `piece`. Both set or both null. Returned as `format: { "quantity": 500, "unit": "g" }` or `null`. Quantity from 1 to 1 000 000. No decimals, no packs.
- **Rationale**: FR-017 as clarified: whole quantities, 1.5 l written 1500 ml.

## R9. Change records

- **Decision**: Table `catalog_changes` (`id`, `operator_id` FK users null, `action` string(20), `global_product_id` null, `brand_id` null, `details` JSON with ids and counts only, `created_at`). Actions: `check`, `correct`, `merge`, `brand_create`, `brand_rename`, `import`, `file_load`. `checked_by` and `checked_at` also go on `global_products`. `catalog:load` and `catalog:import` take an optional `--operator=<email>`; without it the record has no operator (a server command).
- **Rationale**: FR-003 and the backend rule that logs hold no personal data: the record keeps ids, never typed names.

## R10. Import, by command and by API (clarified 2026-11-16)

- **Decision**: One action, `ImportCatalogCsv`, used by two entry points:
  - the command `php artisan catalog:import {file} --operator=<email>`, for any size;
  - the endpoint `POST /operator/catalog/imports` with JSON `{ "csv": "<file content>" }`, refused above 1 MB of CSV text with `import.too_large` (413). It answers 200 with `{ "data": { "added", "updated", "unchanged" } }`. A wrong line answers 422 `{ "error": { "code": "import.invalid_line", "line": 7, "key": "products.x", "reason": "barcode.invalid" } }`, the same line, key and reason the command prints.
- **File**: UTF-8 comma-separated text with a header line `barcode,key,name_fr,name_ar,name_en,brand,format_quantity,format_unit,spellings_fr,spellings_ar,spellings_en`. `barcode`, `key` and the three names are required; spellings are separated by `|`. Each line becomes a 007 `CatalogFileEntry` (extended with brand and format) and goes through the same `LoadCatalogFile` steps: validate everything, check against the database, then one transaction. An unknown brand name is created.
- **Rationale**: FR-020, FR-020a, FR-021. JSON with the CSV in a string field keeps the API JSON-only (007 FR-019) and works from Postman; the 1 MB limit (a named constant) sends large supplier lists to the command. One action guarantees the same result both ways (US5 scenario 5).
- **007 barcode rule stays**: one barcode per product, so a file entry whose key already has another barcode still stops with `barcode.changed`.

## R11. What shops see

- **Decision**: `CatalogProductResource` keeps `barcode` and gains `brand` (`{ id, name }` or null), `format`, and `merged_into_id` (null unless merged); `status` may be `merged`. Lookups, suggestions, shop products and close matches all use it. This only adds fields to the 007 contract.
- **Rationale**: FR-018 with one resource.

## R12. Postman

- **Decision**: A folder "6. Operator catalog" in `postman/shop-keeper.postman_collection.json`. `DatabaseSeeder` makes its seeded account (`test@example.com`, password `password`) an operator when the environment is not production. The folder signs in with the collection variables `operator_email` and `operator_password` (those defaults) and stores `operator_token`; then it adds a pending barcode from the shop of folder 2, lists the queue, creates a brand, checks the product, looks it up from the shop, adds a second pending barcode and merges it into a checked product without a barcode, reads the merged product, and imports a two-line CSV through the API.
- **Rationale**: US7 and SC-007: after `migrate --seed`, nothing needs editing. Granting the role stays a server command (R1); the seeder just runs it for the local account.

## R13. Removing a shop product from sale (added 2026-11-16)

- **Decision**: `shop_products.removed_at` (timestamp, null). `POST /products/{product}/remove` sets it, `POST /products/{product}/restore` clears it; both lock the row, answer 200 with the product, and change nothing when it is already in that state (FR-030). The row is never deleted.
- **Where a removed product disappears**: the default list (`GET /products`) shows products on sale only, and `GET /products?removed=true` shows removed ones only; `SuggestProducts` and `FindCloseProducts` skip removed shop products. Reading by id and the barcode lookup still return it, with `removed_at` set, so the app can offer to put it back.
- **Adding it again**: the unique indexes `(shop_id, identity_key)` and `(shop_id, global_product_id)` still hold removed products, so a second row cannot be created. `ShopProductNameRules` checks removed products first and answers `product.removed` (409) with `existing_product_id` instead of `product.already_in_shop` or `product.name_taken` (FR-028). A rename of another product to a removed product's name gets the same answer. Names are compared only between names merchants chose: catalog-named products have no `identity_key` (R14), so a checked barcode whose catalog name matches a removed own product is added normally (analyze finding I1).
- **Suggestions**: a catalog product whose shop product is removed is still suggested, with that shop product and its `removed_at`, so the app offers "put back" and never an "add" that would fail (analyze finding U1). A removed product with no catalog link is not suggested.
- **Changes**: `PATCH` on a removed product answers `product.removed` (409) (FR-029).
- **Queue counter**: removing a product linked to a catalog product decrements `linked_shops_count`, putting it back increments it, in the same transaction (FR-031, R3). A merge counts only products on sale.
- **Rationale**: 007 FR-012 and the constitution forbid deleting records that later sales and stock movements will reference; a removal date hides the product for its shop while keeping every reference valid. Keeping the row inside the unique indexes is what lets a re-add point to it rather than create a duplicate, and keeps one product per name and per catalog product per shop, as 007 requires.
- **Alternatives**: A physical delete: breaks future sale and stock references and offline records that already hold the id. Letting a re-add create a new row next to the removed one: two products with the same name in one shop, which 007 forbids. Restoring automatically on a re-add: the app's new id would be dropped silently, which the offline sync of 013 must not meet.

## R14. Barcoded products carry the catalog name (added 2026-11-16)

- **Decision**: A shop product **carries a catalog name** when its new column `name_from_catalog` (boolean, default false) is true. It becomes true when the product is added from a `verified` catalog product with a barcode, when the pending product it is linked to is checked (by the operator, a catalog file or an import), or when a merge moves it, with the source's barcode, to a `verified` product. It never becomes true for products already linked to a product that gains a barcode through a merge: their merchants chose those names (owner, 2026-11-16, analyze finding I2). Such a product's `name` is a copy of the catalog name in its `name_language`, a new column (`fr`, `ar` or `en`) set at creation from the request's `language`. Every other shop product keeps the name the merchant chose, as in 007.
- **Creating**: for a checked barcode, `language` is required and `name` is refused (`name.prohibited`). For an unknown or pending barcode, `name` and `language` stay required (007), and `language` is kept as `name_language`. For a catalog product without a barcode or no link, nothing changes.
- **Renaming**: `PATCH` with `name` on a product carrying a catalog name answers `product.name_from_catalog` (409); prices stay editable. A product linked to a pending barcode can be renamed (007 rules, never held as close).
- **Keeping names in step**: one action, `SyncCatalogNames`, rewrites `name`, `compared_name` and `identity_key` of the shop products linked to a catalog product whose `name_from_catalog` is true, with one `UPDATE` per language. The check, a pending product checked by a file or an import, and the merge (for the products it moves) first set `name_from_catalog` on the products concerned. `CheckCatalogProduct`, `CorrectCatalogProduct`, `MergeCatalogProducts` (on the product that stays) and `LoadCatalogFile` (catalog file and import) call it in their transaction whenever the product is `verified` with a barcode.
- **Same name twice**: 007's unique index `(shop_id, identity_key)` guards the names merchants choose. A product carrying a catalog name gets `identity_key = null` (MySQL allows several), so a sync can never collide with a merchant's own product, and two barcoded products whose catalog names happen to match can both sit in one shop (FR-037). `compared_name` is still set, for search and list order. `ShopProductNameRules::refuseIfNameTaken` checks only products whose name the merchant chose, and is skipped when the new product will carry a catalog name.
- **Existing 007 rows**: the migration sets `name_language = 'fr'` on every shop product, sets `name_from_catalog` on those linked to a checked catalog product with a barcode, and copies the French catalog name into them, all in plain SQL, so the migration never depends on an action that will change later (analyze finding D1). The pilot has no production data yet, so French as the default language is safe; the quickstart says so.
- **Rationale**: FR-032 to FR-037 as the owner decided. A stored copy, rather than a name read from the catalog at each request, keeps 007's search, list order and resources unchanged, and the sync runs only on operator actions.
- **Alternatives**: No name on the shop product and the app picks a catalog name per language: rejected by the owner (the name follows the language the product was added with). Keeping `identity_key` for catalog-named products: a correction could then collide with a merchant's own product and fail the operator's change.
