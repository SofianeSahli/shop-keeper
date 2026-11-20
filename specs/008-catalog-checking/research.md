# Research: Catalog Checking

Decisions for `specs/008-catalog-checking/plan.md`. They build on the 007 code in `shop-keeper-backend/app/Modules/Catalog/` and on `specs/007-catalog-products/research.md`. Written 2026-11-14, revised 2026-11-16 after the clarify session, and revised 2026-11-19 after the owner removed the operator role (spec "Clarifications").

## R1. Checking on the server, no operator role (decided 2026-11-19)

- **Decision**: No account role, no `users` column, no middleware and no API endpoint for the catalog. The platform team works on the server:
  - `catalog:pending` lists the queue (read only, R4);
  - the catalog file `products.json` and a new brands file `brands.json`, loaded by `catalog:load`, check and correct products, brands and formats (R5, R7);
  - `catalog:merge` merges two products (R6).
- **Rationale**: The owner decided that whoever can run commands on the server is trusted, and that platform and shop roles are designed before spec 014. 007's `LoadCatalogFile` already checks a pending product in place when an entry carries its barcode, so the file is the natural place for names. Keeping the files as the **only** place names are written means a later `catalog:load` never undoes a change made another way. Every check and correction is reviewed in a pull request, and git records who made it. The files are UTF-8, so Arabic names never go through a console's code page; arguments typed in Windows PowerShell and passed through `docker compose exec` often mangle them.
- **Alternatives**:
  - The operator role with API endpoints (first plan): rejected by the owner on 2026-11-19.
  - Commands for every change (`catalog:check`, `catalog:correct`, `catalog:brand`): rejected. They give two places that write names, so a load can revert a command's correction. Arabic arguments are fragile on Windows consoles. And no change is reviewed in git.
- **What stays a command**: the queue, because it reads live data, and the merge, because it is a one-time change of links that a file entry cannot express.

## R2. One barcode per catalog product (clarified 2026-11-16)

- **Decision**: Keep 007's `global_products.barcode` (unique, nullable). No barcode table, no change to 007's barcode code paths, and `CatalogProduct.barcode` stays a string or `null` in every answer.
- **Effect on merges**: merging two products that both have a barcode is refused with `merge.both_have_barcodes`. When only the merged-away product has one, the merge clears it on that product, then sets it on the product that stays, in the same transaction, so the unique index never sees it twice.
- **Rationale**: The owner chose one barcode at most. A manufacturer's new code for the same product stays a second catalog product (spec Edge Cases).
- **Alternatives**: A barcode table with several codes per product: rejected by the owner.

## R3. Pending queue order and its counter

- **Decision**: `global_products.linked_shops_count` (unsigned int, default 0), kept in the same transaction as every change of a link: `CreateShopProduct` increments it when it links a product; removing and restoring a shop product change it (R13); a merge recomputes both products' counts. A migration fills it from `shop_products`. The queue is a cursor page ordered by `linked_shops_count` desc, `created_at` asc, `id` asc, with an index on `(status, linked_shops_count, created_at, id)`.
- **Rationale**: The backend guide requires cursor pagination, and a cursor cannot order by an aggregate computed in the query. Links change in few places, all in this module, so the counter stays exact. A test compares it with a fresh count.
- **Alternatives**: Offset pages over a `COUNT` subquery: breaks the backend rule. A database trigger: hides a business rule outside the actions.

## R4. The queue command (revised 2026-11-19)

- **Decision**: `php artisan catalog:pending {--limit=50} {--cursor=}` calls the action `ListPendingProducts`, which returns a cursor page of `PendingEntry` value objects. It prints one block per product, then the next page's option:

  ```text
  #12  6191234567897  3 shops  first added 2026-11-07
       typed (fr): Biscuit du quartier
       names given: Biscuit du quartier | Choco | biscuits quartier
  Next page: php artisan catalog:pending --cursor=eyJ...
  ```

  `names given` holds the different names, once compared as in spec 007, of the linked shop products that are on sale and whose name the merchant chose, at most `MAX_NAMES_PER_ENTRY = 20`, oldest link first. An empty queue prints `No product waiting to be checked.` A `--limit` outside 1 to 100 stops with `limit.invalid`, and an unreadable cursor with `cursor.invalid`. Both exit non-zero.
- **No shop data**: no shop id, name or city, no merchant name, email or phone (spec FR-003). The Shops module is not called at all, so the 2026-11-16 `DescribeShops` action is dropped.
- **Rationale**: FR-005, FR-006. The typed names are what the platform team confirms and translates; who typed them is not needed to check a product. Without shop data, constitution IV needs no exception, and version 3.3.0 removes the one 3.2.0 had added.

## R5. Check and correct through the catalog file (revised 2026-11-19)

- **Decision**: `LoadCatalogFile` stays the one action that writes catalog names. 007 already matches a pending product by barcode and sets it `verified` in place. 008 adds:
  - **Locks**: the targets are read inside the transaction with `lockForUpdate`, in id order, so a load and a merge on the same rows run one after the other (FR-011). Today they are read before the transaction.
  - **Key rules**: the reserved prefix `products.barcode_` gives `key.reserved`. A key held by a merged product gives `key.merged` (FR-012); without it, a load would set a merged product back to `verified`.
  - **Checked time**: `checked_at` is set when a pending product becomes `verified`.
  - **Change records**: one `check` or `correct` record per product that changed, with source `catalog:load` (R9).
  - **Shop names**: `SyncCatalogNames` (R14) runs for each changed product that is `verified` with a barcode.
  - **Brand and format** per entry (R7, R8).
- **Output**: `Catalog loaded: 3 added, 2 updated, 40 unchanged; brands: 1 added, 0 updated; 7 shop product names synced.` A refusal prints `Catalog not loaded, nothing changed. entry 12 (products.x): key.reserved` and exits non-zero, as in 007.
- **Rationale**: One writer for names (R1). 007's rules already give all-or-nothing loads and repeatable files (spec 007 FR-003, FR-003a); 008 only adds the rules the queue and merges need.

## R6. Merge

- **Decision**: `php artisan catalog:merge {from} {into} {--yes}` calls `MergeCatalogProducts`. The command first prints both products (id, key, barcode, status, linked shops) and asks for confirmation unless `--yes` is given. Without `--yes` in a non-interactive run, or when the answer is no, it stops with `merge.cancelled`. Ids that are not positive integers give `id.invalid`; unknown ids give `catalog_product.not_found`.
- **The action**, in one transaction:
  1. Locks both rows in id order, so two opposite merges cannot deadlock.
  2. Refuses a merge into itself (`merge.same_product`), a merged source or target (`catalog_product.merged`), a checked source into a pending target (`merge.checked_into_pending`), and two barcodes (`merge.both_have_barcodes`).
  3. Moves the source's barcode, if any (R2).
  4. For each shop linked to both products, removes the link of its product on the source: `global_product_id` becomes null, and the name, prices and id stay.
  5. Relinks every other shop product linked to the source.
  6. Deletes the source's term rows, so it is never suggested.
  7. Sets the source to `status = merged` with `merged_into_id = target`.
  8. Recomputes both `linked_shops_count`.
  9. Runs `SyncCatalogNames` on the target (R14) and records a `merge` change with source `catalog:merge`.

  The source's names stay for the record. Every refusal comes from a `CatalogMergeError` with a reason code, and the command prints `Nothing changed. <reason>`.
- **Output**: `Merged 41 into 12: barcode moved, 3 shop products relinked, 1 unlinked, 3 names synced.`
- **Reading by id**: a new shop endpoint `GET /catalog/products/{catalog_product}` returns any catalog product; a merged one has `status: merged`, `merged_into_id`, no names. Adding a merged product by id answers `catalog_product_id.merged`.
- **Rationale**: FR-013 to FR-016. A shop product that loses its link stays a product of its own, so its later stock and sales keep pointing at the same id. The confirmation guards against swapped ids, since a merge cannot be undone.
- **Pending into pending** is allowed only when at most one has a barcode, which in practice means never (every pending product has one); the rule needs no special case.

## R7. Brands (revised 2026-11-19)

- **Decision**: Table `brands` (`id`, `key` string(100) unique, `name` string(80), `compared` unique `utf8mb4_bin`, `spellings` JSON list). The brands file `app/Modules/Catalog/database/catalog/brands.json` is a JSON list of `{ "key": "brands.delice", "name": "Délice", "spellings": ["delice", "ديليس"] }`. Keys use the form `^brands\.[a-z0-9_]+$`.
- **Loading**: `catalog:load` takes `--brands=<path>`, defaulting to that file, and loads it first, in the same transaction as the catalog file. A brand is matched by its key: new keys are added, and a changed name or spellings updates it and rebuilds its products' brand terms. Brands missing from the file are kept (no delete).
- **Refusals**, all before any write, each stopping the whole load: `brands_file.invalid`, `brand.key.invalid`, `brand.key.duplicate`, `brand.name.missing`, `brand.name.too_long` (over 80), `brand.spelling.too_long`, `brand.name_taken` (same compared name as another brand, in the file or in the database), and `brand.unknown` for a catalog entry naming a key that is neither in the file nor stored. Catalog entries name their brand by key (`"brand": "brands.delice"`).
- **Search**: every checked product of a brand gets term rows of kind `brand` for the brand's name and spellings, rebuilt when the brand or the product's brand changes. `SuggestProducts` then finds products by brand with no change to its query; `FindCloseProducts` ignores terms of kind `brand`, so a shop's own "Délice …" product is not held for every Délice product.
- **Rationale**: FR-018, FR-019, FR-021, and one search table as in 007 R6. A stable key makes a rename an edit of one line in the file.

## R8. Formats (clarified 2026-11-16)

- **Decision**: `global_products.format_quantity` (unsigned int, null) and `format_unit` (string(5), null): `g`, `kg`, `ml`, `l`, `piece`. Both set or both null. Returned as `format: { "quantity": 500, "unit": "g" }` or `null`, and written the same way in a catalog entry. Quantity from 1 to 1 000 000. No decimals, no packs. Anything else gives `format.invalid`.
- **Rationale**: FR-019 as clarified: whole quantities, 1.5 l written 1500 ml.

## R9. Change records (revised 2026-11-19)

- **Decision**: Table `catalog_changes` (`id`, `source` string(30), `action` string(20), `global_product_id` null, `brand_id` null, `details` JSON with ids and counts only, `created_at`). The source is the command, either `catalog:load` or `catalog:merge`. The action is one of `check`, `correct`, `merge`, `brand_create`, `brand_rename`. `checked_at` also goes on `global_products`. There is no person: no `operator_id`, no `checked_by`, no `--operator` option.
- **Rationale**: FR-002 and the backend rule that logs hold no personal data. Who changed names is in the git history of the catalog files; the record says when each change reached the database, and keeps merges, which no file records.

## R10. No separate import (decided 2026-11-19)

- **Decision**: No CSV reader, no `catalog:import` command and no import endpoint. A supplier's list is turned into catalog file entries and goes through the same pull request and `catalog:load`.
- **Rationale**: The API import needed the operator role. A command import would be a second writer of names, against R1.

## R11. What shops see

- **Decision**: `CatalogProductResource` keeps `barcode` and gains `brand` (`{ id, name }` or null), `format`, and `merged_into_id` (null unless merged); `status` may be `merged`. Lookups, suggestions, shop products and close matches all use it. This only adds fields to the 007 contract.
- **Rationale**: FR-020 with one resource.

## R12. Postman and quickstart (revised 2026-11-19)

- **Decision**: No operator folder. Folder 5 of `postman/shop-keeper.postman_collection.json` gains: read a catalog product by id; try to rename a product added from a checked barcode (`product.name_from_catalog`); remove a product from sale; list removed products; try to add it again (`product.removed`); put it back. The quickstart lists the server commands and their expected output, run by hand.
- **Rationale**: US7 and SC-007. The catalog commands are server-only, so the quickstart, not the collection, shows them.

## R13. Removing a shop product from sale (added 2026-11-16)

- **Decision**: `shop_products.removed_at` (timestamp, null). `POST /products/{product}/remove` sets it, `POST /products/{product}/restore` clears it; both lock the row, answer 200 with the product, and change nothing when it is already in that state (FR-034). The row is never deleted.
- **Where a removed product disappears**: the default list (`GET /products`) shows products on sale only, and `GET /products?removed=true` shows removed ones only; `SuggestProducts` and `FindCloseProducts` skip removed shop products, and the queue's names leave them out. Reading by id and the barcode lookup still return it, with `removed_at` set, so the app can offer to put it back.
- **Adding it again**: the unique indexes `(shop_id, identity_key)` and `(shop_id, global_product_id)` still hold removed products, so a second row cannot be created. `ShopProductNameRules` checks removed products first and answers `product.removed` (409) with `existing_product_id`, instead of `product.already_in_shop` or `product.name_taken` (FR-032). A rename of another product to a removed product's name gets the same answer. Names are compared only between names merchants chose: catalog-named products have no `identity_key` (R14), so a checked barcode whose catalog name matches a removed own product is added normally (analyze finding I1).
- **Suggestions**: a catalog product whose shop product is removed is still suggested, with that shop product and its `removed_at`, so the app offers "put back" and never an "add" that would fail (analyze finding U1). A removed product with no catalog link is not suggested.
- **Changes**: `PATCH` on a removed product answers `product.removed` (409) (FR-033).
- **Queue counter**: removing a product linked to a catalog product decrements `linked_shops_count`, putting it back increments it, in the same transaction (FR-035, R3). A merge counts only products on sale.
- **Rationale**: 007 FR-012 and the constitution forbid deleting records that later sales and stock movements will reference; a removal date hides the product for its shop while keeping every reference valid. Keeping the row inside the unique indexes is what lets a re-add point to it rather than create a duplicate, and keeps one product per name and per catalog product per shop, as 007 requires.
- **Alternatives**: A physical delete: breaks future sale and stock references and offline records that already hold the id. Letting a re-add create a new row next to the removed one: two products with the same name in one shop, which 007 forbids. Restoring automatically on a re-add: the app's new id would be dropped silently, which the offline sync of 013 must not meet.

## R14. Barcoded products carry the catalog name (added 2026-11-16)

- **When a product carries a catalog name**: a shop product carries one when its new column `name_from_catalog` (boolean, default false) is true. It becomes true in three cases:
  - it is added from a `verified` catalog product with a barcode;
  - the pending product it is linked to is checked by a catalog file load;
  - a merge moves it, with the source's barcode, to a `verified` product.

  It never becomes true for products already linked to a product that gains a barcode through a merge: their merchants chose those names (owner, 2026-11-16, analyze finding I2). Such a product's `name` is a copy of the catalog name in its `name_language`, a new column (`fr`, `ar` or `en`) set at creation from the request's `language`. Every other shop product keeps the name the merchant chose, as in 007.
- **Creating**: for a checked barcode, `language` is required and `name` is refused (`name.prohibited`). For an unknown or pending barcode, `name` and `language` stay required (007), and `language` is kept as `name_language`. For a catalog product without a barcode or no link, nothing changes.
- **Renaming**: `PATCH` with `name` on a product carrying a catalog name answers `product.name_from_catalog` (409); prices stay editable. A product linked to a pending barcode can be renamed (007 rules, never held as close).
- **Keeping names in step**: one action, `SyncCatalogNames`, rewrites `name`, `compared_name` and `identity_key` of the shop products linked to a catalog product whose `name_from_catalog` is true, with one `UPDATE` per language. It is called inside the transaction of its callers, whenever the product is `verified` with a barcode:
  - `LoadCatalogFile`, for a check or a correction. For a pending product it checks in place, it first sets `name_from_catalog` on every product linked to it.
  - `MergeCatalogProducts`, on the product that stays. It first sets `name_from_catalog` only on the products it moves, and only when the source had the barcode.
- **Same name twice**: 007's unique index `(shop_id, identity_key)` guards the names merchants choose. A product carrying a catalog name gets `identity_key = null` (MySQL allows several), so a sync can never collide with a merchant's own product, and two barcoded products whose catalog names happen to match can both sit in one shop (FR-028). `compared_name` is still set, for search and list order. `ShopProductNameRules::refuseIfNameTaken` checks only products whose name the merchant chose, and is skipped when the new product will carry a catalog name.
- **Existing 007 rows**: the migration, in plain SQL only, so it never depends on an action that will change later (analyze finding D1):
  - sets `name_language = 'fr'` on every shop product;
  - sets `name_from_catalog` on those linked to a checked catalog product with a barcode;
  - copies the French catalog name into them.

  The pilot has no production data yet, so French as the default language is safe; the quickstart says so.
- **Rationale**: FR-023 to FR-028 as the owner decided. A stored copy, rather than a name read from the catalog at each request, keeps 007's search, list order and resources unchanged, and the sync runs only on catalog changes.
- **Alternatives**: No name on the shop product and the app picks a catalog name per language: rejected by the owner (the name follows the language the product was added with). Keeping `identity_key` for catalog-named products: a correction could then collide with a merchant's own product and fail the load.

## R15. Command conventions (added 2026-11-19)

- **Decision**: Commands are thin: they parse their arguments, call one action, and print its result. Business rules stay in the actions. Each refusal is an exception carrying a reason code (`CatalogFileError` for loads, `CatalogMergeError` for merges, `InvalidCommandInput` for options). The command catches it, prints `Nothing changed. <reason>` (loads keep 007's `Catalog not loaded, nothing changed. …`) and returns `FAILURE`. Every check runs before the first write, and each change runs in one transaction inside its action.
- **Output**: ids, keys, barcodes, statuses and counts. Only `catalog:pending` prints typed names, never with the shop that typed them (FR-003). Commands write nothing to the log.
- **Tests**: one Pest feature test file per command, using `$this->artisan(...)`, covering success, every refusal with its exit code and printed reason, a database identical before and after each refusal, and output free of shop and merchant data.
