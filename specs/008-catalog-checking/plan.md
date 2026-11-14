# Implementation Plan: Catalog Checking

**Branch**: `008-catalog-checking` | **Date**: 2026-11-14, revised 2026-11-16 after clarify | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/008-catalog-checking/spec.md`

## Summary

Give the platform operator a way to check the catalog: an operator role on accounts (granted by a server command), a review queue of pending catalog products ordered by how many shops use them, a check that gives a pending product its key and three names, corrections, merges of duplicate catalog products (the barcode, if any, and the shop links move to the product that stays), shared brands and formats, and a comma-separated import through a server command or the API. A catalog product keeps one barcode at most. On the shop side, a product with a checked barcode carries the catalog name in its language and follows the operator's corrections, while other products keep the merchant's own name; and a merchant can remove a product from sale and put it back, hidden but never deleted. Backend only; `shop-keeper-mobile/` is not touched.

## Technical Context

**Language/Version**: PHP 8.4.27, Laravel 13 (as in 007)

**Primary Dependencies**: none new. CSV is read with PHP's `fgetcsv`

**Storage**: MySQL 8.4; tests use `shop_keeper_test`

**Testing**: Pest feature tests per endpoint and command, rule tests for merge and counter; Pint

**Target Platform**: Docker Compose from 006

**Project Type**: REST API and operator commands

**Performance Goals**: queue pages and checks under 200 ms p95 with 10 000 pending products; a merge of a product linked to 1 000 shops in one transaction under 2 s

**Constraints**: shops never see typed names; shops' prices and the names merchants chose never change through the catalog, while barcoded products follow the catalog name; change records hold no typed names; 007 migrations are not edited

**Scale/Scope**: 9 operator endpoints, 3 new shop endpoints (catalog product read, remove, restore), 4 commands (3 new), 2 new tables, new columns on `global_products` and `users`, fields added to 007's CatalogProduct

## Constitution Check

*Version 3.2.0 (amended 2026-11-16 for this feature). Checked before research, after design, and after the analyze pass.*

| Principle | Status | How |
|-----------|--------|-----|
| I. Monorepo boundaries | Pass | Only `shop-keeper-backend/`. No operator "mode" in the merchant app; an operator app would be its own project. Identity owns the role and its middleware; Catalog never reads `users` beyond the operator id it is given. |
| II. Mobile experience | N/A | No screen. |
| III. Internationalization | Pass | Stable codes only. The check is what gives products their fr, ar and en names. |
| IV. Secure backend | Pass (after amendment) | Constitution 3.2.0 (2026-11-16) lets the platform operator role read the shop data needed to maintain the catalog: the queue shows names shops typed and each shop's name and city, through operator endpoints only, never owner contact details. Before the amendment this was a violation of "a user MUST never reach another shop's data" (analyze finding C1). Operator routes behind `auth:sanctum` and an `operator` middleware; a policy on catalog products and brands; Form Requests; actions in transactions with row locks; Postman. |
| V. Testability | Pass | Success, validation, signed-out and non-operator tests on every endpoint (FR-023); rule tests for merge, race and counter. |
| VI. Offline-first | Pass | Shop product ids never change in a merge or a removal, so offline records keep pointing to them. |
| VII. Product scope | Pass | Roadmap step 1.3, second of four specs. |
| VIII. Data and privacy | Pass | Typed names and the shop name and city behind them go only to operators (platform staff), never to other shops; no owner name, email or phone; change records keep ids, not names; logs carry no names. |
| Records never deleted | Pass | Removing a product from sale sets a date; the row stays for later sales and stock movements (research R13). |
| Catalog constraint | Pass | `global_products` gains brand and format, as the constitution lists, and still has no price, stock or shop. |

No violations, so Complexity Tracking is empty.

## Project Structure

### Documentation (this feature)

```text
specs/008-catalog-checking/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── api.md
├── checklists/requirements.md
└── tasks.md
```

### Source Code

```text
shop-keeper-backend/
├── app/Modules/Identity/
│   ├── Console/                 # GrantOperatorCommand, RevokeOperatorCommand
│   ├── Controllers/Middleware/RequireOperator.php   # alias "operator"
│   ├── Actions/FindAccountIdByEmail.php
│   └── database/migrations/     # add operator_since to users
├── app/Modules/Shops/
│   └── Actions/DescribeShops.php        # shop ids -> name and city, for the queue
├── app/Modules/Catalog/
│   ├── Actions/                 # ListPendingProducts, CheckCatalogProduct, CorrectCatalogProduct,
│   │                            # MergeCatalogProducts, CreateBrand, RenameBrand, RebuildBrandTerms,
│   │                            # ImportCatalogCsv, RecordCatalogChange,
│   │                            # RemoveShopProduct, RestoreShopProduct, SyncCatalogNames
│   ├── Support/                 # CatalogProductDetails (key, names, spellings, brand, format), Format,
│   │                            # CsvCatalogReader; CatalogFileEntry gains brand and format
│   ├── Console/                 # ImportCatalogCommand; LoadCatalogCommand gains --operator
│   ├── Controllers/             # Operator/PendingProductController, Operator/CatalogProductController,
│   │                            # Operator/BrandController, Operator/ImportController,
│   │                            # CatalogProductController (shop read)
│   ├── Models/                  # Brand, CatalogChange; CatalogStatus::Merged,
│   │                            # FormatUnit, CatalogTermKind::Brand
│   ├── Policies/                # CatalogProductPolicy, BrandPolicy
│   ├── database/migrations/     # product columns, brands, changes, shop_products.removed_at
│   ├── routes.php               # + operator group
│   └── Tests/                   # new tests; 007 tests updated
├── database/seeders/DatabaseSeeder.php   # test@example.com becomes an operator outside production
└── postman/shop-keeper.postman_collection.json   # + "6. Operator catalog"
```

**Structure Decision**: The 007 `Catalog` module grows; Identity gains the role, its middleware and commands; Shops gains `DescribeShops` for the queue. Catalog receives the operator's id from the request user and never reads `users` or `shops`.

## Design Notes

- **007 stays as it is**: one barcode per product (clarified), so 007's lookup, pending creation and loader keep their code; 008 only adds columns and fields.
- **One importer, two doors**: `ImportCatalogCsv` serves `catalog:import` and `POST /operator/catalog/imports`; tests run the same files through both.
- **One state change per endpoint**: check (pending → verified), correct (verified → verified), merge (pending or verified → merged). Each locks its rows; merges lock both products in id order.
- **The counter**: `linked_shops_count` changes only in `CreateShopProduct` and `MergeCatalogProducts`; a shared test helper compares it with a fresh count after each story's tests.
- **Brand search through terms**: brand names become term rows of kind `brand` on each product of the brand; `FindCloseProducts` skips that kind.
- **Catalog names on shop products**: a stored copy kept in step by `SyncCatalogNames` inside every operator change; products carrying one have no `identity_key`, so the operator's renames never collide with a merchant's names (research R14).
- **Removed products stay in the unique indexes**: so a re-add finds them and answers `product.removed`, and 007's "one product per name and per catalog product" still holds (research R13).
- **Docs to update in the same PR**: 007 contract (new CatalogProduct fields), backend `CLAUDE.md` (the "to decide later: who verifies pending products" line is now decided), backend README (operator commands, import), root `CLAUDE.md` focus line.

## Spec Changes Made During Planning

The clarify session of 2026-11-16 (5 questions, recorded in the spec) changed the first plan of 2026-11-14:

- One barcode per catalog product: the barcode table and the rework of 007 code are gone; merging two barcoded products is refused (research R2, R6).
- Import through both a server command and an API endpoint taking the CSV text in JSON, with a 1 MB limit (research R10).
- The queue shows each linked shop's name and city, read through a Shops action (research R4).
- Formats confirmed as whole quantities in five units (research R8).

Added on 2026-11-16 by the owner during the PR review:

- A merchant can remove a product from sale at any time (User Story 8, FR-025 to FR-031, research R13).
- A product with a checked barcode carries the catalog name and cannot be renamed; this changes spec 007's naming rule for barcoded products (User Story 9, FR-032 to FR-037, research R14).

## Complexity Tracking

No constitution violations.
