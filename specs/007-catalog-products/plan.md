# Implementation Plan: Shared Catalog and Shop Products

**Branch**: `007-catalog-products` | **Date**: 2026-11-07 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/007-catalog-products/spec.md`

## Summary

Add a `Catalog` module to the backend: a shared catalog (`global_products`) whose names are translation keys with French, Arabic and English names and other spellings stored with the catalog, loaded from a JSON file by `catalog:load` with at least 150 common unbarcoded products; each shop's own products (`shop_products`) with their own name and prices in millimes; adding a product by barcode (EAN-13, EAN-8, UPC-A, unknown barcodes become pending catalog products) or by name with suggestions; and a duplicate check that holds close names until confirmed and refuses the same name or the same catalog product twice, backed by unique indexes. Backend only; `shop-keeper-mobile/` is not touched.

## Technical Context

**Language/Version**: PHP 8.4.27 (pinned in `docker/php/Dockerfile`), Laravel 13

**Primary Dependencies**: none new. `intl` (already in the image) for Unicode normalization; Sanctum from 006

**Storage**: MySQL 8.4 everywhere; tests use `shop_keeper_test`. Redis for the rate limiter

**Testing**: Pest feature tests per endpoint, unit tests for `Barcode`, `NameComparison` and close names, command tests for `catalog:load` with fixture files; Pint

**Target Platform**: Docker Compose from 006

**Project Type**: REST API

**Performance Goals**: suggestions and lookups under 200 ms p95 with about 5 000 catalog terms and 5 000 products in a shop

**Constraints**: no user-facing text; catalog names are data in three languages; money as integer millimes; another shop's typed name is never shown; configuration only through `.env`

**Scale/Scope**: 6 endpoints, 1 new module, 4 new tables, 1 operator command, a catalog file of at least 150 products

## Constitution Check

*Version 3.1.0. Checked before research and again after design.*

| Principle | Status | How |
|-----------|--------|-----|
| I. Monorepo boundaries | Pass | Only `shop-keeper-backend/`. Catalog reads the shop through a Shops middleware and action, never the `shops` table (research R2). |
| II. Mobile experience | N/A | No screen. Suggestions in one request and add in one more keep the future flow within 3 taps (SC-001). |
| III. Internationalization | Pass | Stable codes only. Catalog names are data in fr, ar, en, so a new product needs no app release; RTL and translations come with the connected-app spec. |
| IV. Secure backend | Pass | `/api/v1`, Form Requests, thin controllers, one action per use case, `ShopProductPolicy`, shop-scoped queries, unique indexes, Postman collection. |
| V. Testability | Pass | Success, validation, unauthenticated, no-shop and cross-shop tests per endpoint (FR-021); rule tests for barcodes, names, holds and the load. |
| VI. Offline-first | Pass | Shop products take client UUIDs and a resend with the same `id` is harmless, ready for the sync spec (research R3). No mobile change now. |
| VII. Product scope | Pass | Roadmap step 1.3, first of four specs. No stock, units or suppliers. |
| VIII. Data and privacy | Pass | A pending product's typed name is stored for checking only and never returned; logs carry no names or barcodes with personal data. |
| Catalog constraint | Pass | `global_products` (key, status, barcode, no price) and `shop_products` (prices, optional link) as required. Brand and format wait for `008`. |
| Money constraint | Pass | Unsigned `BIGINT` millimes, JSON integers only (research R11). |
| Immutable money records | N/A | Prices on a product are attributes, not records of money; a change replaces the current price (spec Assumptions). |

No violations, so Complexity Tracking is empty.

## Project Structure

### Documentation (this feature)

```text
specs/007-catalog-products/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── api.md
├── checklists/requirements.md
└── tasks.md                                   # /speckit-tasks
```

### Source Code

```text
shop-keeper-backend/
├── app/
│   ├── Shared/
│   │   ├── CurrentShop.php                    # the request's shop id
│   │   ├── Database/BinaryUuid.php            # cast: UUID string <-> BINARY(16)
│   │   └── Errors/ApiException.php            # + details merged into the error body
│   └── Modules/
│       ├── Shops/
│       │   ├── Actions/FindShopIdOfOwner.php
│       │   └── Controllers/Middleware/RequireShop.php   # alias "shop", shop.required
│       └── Catalog/
│           ├── Actions/        # LookUpBarcode, SuggestProducts, CreateShopProduct,
│           │                   # UpdateShopProduct, FindCloseProducts, FindOrCreatePendingProduct,
│           │                   # LoadCatalogFile
│           ├── Support/        # Barcode, NameComparison, CodePointDistance, CatalogFileEntry
│           ├── Console/        # LoadCatalogCommand (catalog:load)
│           ├── Controllers/    # BarcodeController, SuggestionController, ShopProductController,
│           │                   # Requests/, Resources/
│           ├── Models/         # GlobalProduct, GlobalProductName, GlobalProductTerm,
│           │                   # ShopProduct, CatalogStatus, CatalogLanguage
│           ├── Policies/       # ShopProductPolicy
│           ├── database/
│           │   ├── migrations/
│           │   ├── factories/
│           │   └── catalog/products.json
│           ├── routes.php
│           ├── CatalogServiceProvider.php
│           └── Tests/          # feature, unit and command tests, fixtures/
├── bootstrap/providers.php      # + CatalogServiceProvider
├── database/seeders/DatabaseSeeder.php   # calls catalog:load
├── postman/shop-keeper.postman_collection.json   # renamed, + Products folder
└── tests/Feature/Shared/ApiCollectionTest.php    # new file name, query strings, {{barcode}}
```

**Structure Decision**: The module layout from `shop-keeper-backend/CLAUDE.md`, with one new module `Catalog` for both product tables (research R1). The Shops module gains the middleware and action that resolve the current shop; the shared `CurrentShop` value carries it to Catalog.

## Design Notes

- **One path to a product**: `CreateShopProduct` handles the three forms (barcode, catalog product, own name) in one transaction: resolve the link (with `FindOrCreatePendingProduct` for unknown barcodes), default the name, check `already_in_shop`, check `name_taken`, check the hold when it applies, insert. `UpdateShopProduct` reuses the same name checks. The rules live once.
- **Compared names are derived**: `ShopProduct` sets `compared_name` and `identity_key` from `name` on save, and `LoadCatalogFile` builds term rows through the same `NameComparison`, so search, holds and the unique index all use one rule.
- **Concurrency**: unique indexes decide; actions catch `UniqueConstraintViolationException` and answer like the request that came second. The pending catalog insert uses a savepoint so a lost race links to the winner's row (research R8).
- **Privacy of pending names**: `pending_name` is hidden on the model, absent from every resource, and pending products have no term rows. A test asserts that no endpoint body contains another shop's typed name (SC-005).
- **Cross-shop answers**: a product id from another shop is looked up with the current shop's scope, so it is simply not found: `product.not_found`, the same as an unknown id (FR-013). The policy is still applied, as the backend guide requires.
- **Docs to update in the same PR**: the collection rename in `specs/006-auth-shop/contracts/api.md` and `quickstart.md`, the backend README (catalog load step), and the 007 contract.

## Spec Changes Made During Planning

None. Two points the plan settles inside the spec, for the owner to see:

- `language` is also required when the barcode is unknown, because FR-004 keeps the language the merchant typed (research R9).
- Shop products take client UUIDs from now on (research R3), for the offline sync to come.

One question left open, not built: GS1 reserves more in-store prefixes than the spec's `2…` 13-digit rule (research R4); it can go to `008`.

## Complexity Tracking

No constitution violations.
