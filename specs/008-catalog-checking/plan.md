# Implementation Plan: Catalog Checking

**Branch**: `008-catalog-checking` | **Date**: 2026-11-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/008-catalog-checking/spec.md`

## Summary

Give the platform operator a way to check the catalog: an operator role on accounts (granted by a server command), a review queue of pending catalog products ordered by how many shops use them, a check that gives a pending product its key and three names, corrections, merges of duplicate catalog products (barcodes and shop links move to the product that stays), shared brands and formats, and a comma-separated import. Catalog products move from one barcode to several. Backend only; `shop-keeper-mobile/` is not touched.

## Technical Context

**Language/Version**: PHP 8.4.27, Laravel 13 (as in 007)

**Primary Dependencies**: none new. CSV is read with PHP's `fgetcsv`

**Storage**: MySQL 8.4; tests use `shop_keeper_test`

**Testing**: Pest feature tests per endpoint and command, rule tests for merge and counter; Pint

**Target Platform**: Docker Compose from 006

**Project Type**: REST API and operator commands

**Performance Goals**: queue pages and checks under 200 ms p95 with 10 000 pending products; a merge of a product linked to 1 000 shops in one transaction under 2 s

**Constraints**: shops never see typed names; shops' own names and prices never change through the catalog; change records hold no typed names; 007 migrations are not edited

**Scale/Scope**: 8 operator endpoints, 1 new shop endpoint, 3 commands (2 new), 3 new tables, 1 dropped and several added columns, a contract change to 007's CatalogProduct

## Constitution Check

*Version 3.1.0. Checked before research and again after design.*

| Principle | Status | How |
|-----------|--------|-----|
| I. Monorepo boundaries | Pass | Only `shop-keeper-backend/`. No operator "mode" in the merchant app; an operator app would be its own project. Identity owns the role and its middleware; Catalog never reads `users` beyond the operator id it is given. |
| II. Mobile experience | N/A | No screen. |
| III. Internationalization | Pass | Stable codes only. The check is what gives products their fr, ar and en names. |
| IV. Secure backend | Pass | Operator routes behind `auth:sanctum` and an `operator` middleware; a policy on catalog products and brands; Form Requests; actions in transactions with row locks; Postman. |
| V. Testability | Pass | Success, validation, signed-out and non-operator tests on every endpoint (FR-023); rule tests for merge, race and counter. |
| VI. Offline-first | Pass | Shop product ids never change in a merge, so offline records keep pointing to them. |
| VII. Product scope | Pass | Roadmap step 1.3, second of four specs. |
| VIII. Data and privacy | Pass | Typed names go only to operators (platform staff); change records keep ids, not names; logs carry no names. |
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
├── app/Modules/Catalog/
│   ├── Actions/                 # ListPendingProducts, CheckCatalogProduct, CorrectCatalogProduct,
│   │                            # MergeCatalogProducts, CreateBrand, RenameBrand, RebuildBrandTerms,
│   │                            # ImportCatalogCsv, RecordCatalogChange; 007 actions updated for barcodes
│   ├── Support/                 # CatalogProductDetails (key, names, spellings, brand, format), Format,
│   │                            # CsvCatalogReader; CatalogFileEntry gains brand and format
│   ├── Console/                 # ImportCatalogCommand; LoadCatalogCommand gains --operator
│   ├── Controllers/             # Operator/PendingProductController, Operator/CatalogProductController,
│   │                            # Operator/BrandController, CatalogProductController (shop read)
│   ├── Models/                  # GlobalProductBarcode, Brand, CatalogChange; CatalogStatus::Merged,
│   │                            # FormatUnit, CatalogTermKind::Brand
│   ├── Policies/                # CatalogProductPolicy, BrandPolicy
│   ├── database/migrations/     # barcodes table + copy, drop barcode, product columns, brands, changes
│   ├── routes.php               # + operator group
│   └── Tests/                   # new tests; 007 tests updated
├── database/seeders/DatabaseSeeder.php   # test@example.com becomes an operator outside production
└── postman/shop-keeper.postman_collection.json   # + "6. Operator catalog"
```

**Structure Decision**: The 007 `Catalog` module grows; Identity gains the role, its middleware and commands. Catalog receives the operator's id from the request user and never reads `users`.

## Design Notes

- **Barcodes first**: moving to several barcodes changes 007 code paths (lookup, pending creation, add by barcode, loader, close-name rule). It lands first, with the 007 tests updated, so every later story starts from a green suite.
- **One state change per endpoint**: check (pending → verified), correct (verified → verified), merge (pending or verified → merged). Each locks its rows; merges lock both products in id order.
- **The counter**: `linked_shops_count` changes only in `CreateShopProduct` and `MergeCatalogProducts`; a shared test helper compares it with a fresh count after each story's tests.
- **Brand search through terms**: brand names become term rows of kind `brand` on each product of the brand; `FindCloseProducts` skips that kind.
- **Docs to update in the same PR**: 007 contract (`barcodes`), backend `CLAUDE.md` (the "to decide later: who verifies pending products" line is now decided), backend README (operator commands, import), root `CLAUDE.md` focus line.

## Spec Changes Made During Planning

None. Settled inside the spec, for the owner to see:

- Import is a server command (`catalog:import`), not an endpoint, because the API takes JSON only (research R10).
- The 007 catalog file now adds a new barcode to an existing key instead of refusing it, since a product can hold several (research R10).
- `CatalogProduct.barcode` becomes `barcodes` in every API answer (research R2).

## Complexity Tracking

No constitution violations.
