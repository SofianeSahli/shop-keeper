# Implementation Plan: Catalog Checking

**Branch**: `008-catalog-checking` | **Date**: 2026-11-14, revised 2026-11-16 after clarify and 2026-11-19 after the owner removed the operator role | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/008-catalog-checking/spec.md`

## Summary

Let the platform team check the shared catalog from the server, with no account role and no admin API:
- `catalog:pending`, a read-only command, lists the products waiting to be checked, ordered by how many shops use them.
- The 007 catalog file, plus a new brands file, both loaded by `catalog:load`, checks and corrects products, brands and formats. Every change of names is reviewed in git.
- `catalog:merge` merges duplicate catalog products: the barcode, if any, and the shop links move to the product that stays.

A catalog product keeps one barcode at most.

On the shop side:
- A product with a checked barcode carries the catalog name in its language and follows corrections; other products keep the merchant's own name.
- A merchant can remove a product from sale and put it back, hidden but never deleted.

Backend only; `shop-keeper-mobile/` is not touched.

## Technical Context

**Language/Version**: PHP 8.4.27, Laravel 13 (as in 007)

**Primary Dependencies**: none new

**Storage**: MySQL 8.4; tests use `shop_keeper_test`

**Testing**: Pest feature tests per endpoint and per command, rule tests for merge and counter; Pint

**Target Platform**: Docker Compose from 006

**Project Type**: REST API and server commands

**Performance Goals**: a queue page under 200 ms with 10 000 pending products; a merge of a product linked to 1 000 shops in one transaction under 2 s

**Constraints**:
- Shops never see typed names.
- Command output holds no shop or merchant data.
- Shops' prices and the names merchants chose never change through the catalog, while barcoded products follow the catalog name.
- Change records hold no typed names.
- 007 migrations are not edited.

**Scale/Scope**: no catalog-writing endpoint. Changes by area:
- **Shop endpoints**: 3 new (catalog product read, remove, restore).
- **Commands**: 2 new (`catalog:pending`, `catalog:merge`); `catalog:load` gains brands, formats, locks and name sync.
- **Files and tables**: 1 new catalog file (`brands.json`), 2 new tables, new columns on `global_products` and `shop_products`.
- **API shape**: fields added to 007's CatalogProduct.

## Constitution Check

*Version 3.3.0 (amended 2026-11-19: the operator exception added in 3.2.0 for the first version of this feature is removed). Checked before research, after design, and after the analyze pass.*

| Principle | Status | How |
|-----------|--------|-----|
| I. Monorepo boundaries | Pass | Only `shop-keeper-backend/`. No operator "mode" in the merchant app and no admin API. Only the Catalog module changes; Identity and Shops are not touched. |
| II. Mobile experience | N/A | No screen. |
| III. Internationalization | Pass | Stable codes only. The check is what gives products their fr, ar and en names, written in UTF-8 catalog files. |
| IV. Secure backend | Pass | No user reaches another shop's data: there is no operator role, and the queue command, run only on the server, prints no shop data. The catalog changes only through server commands, never through an endpoint (SC-005). The new shop endpoints use `auth:sanctum`, `shop`, policies and Form Requests. The actions run in transactions with row locks. Postman is updated. |
| V. Testability | Pass | Every endpoint is tested for success, validation, signed-out access, no shop and another shop. Every command is tested for success, each refusal and no change on failure (FR-036). Rule tests cover merge, the race and the counter. |
| VI. Offline-first | Pass | Shop product ids never change in a merge or a removal, so offline records keep pointing to them. |
| VII. Product scope | Pass | Roadmap step 1.3, second of four specs. |
| VIII. Data and privacy | Pass | Typed names appear only in the server-side queue command, never with the shop that typed them, and never in the catalog files. Command output and logs carry no names of people or shops; change records keep ids, not names. |
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
├── app/Modules/Catalog/
│   ├── Actions/                 # ListPendingProducts, MergeCatalogProducts, RebuildBrandTerms,
│   │                            # RecordCatalogChange, SyncCatalogNames,
│   │                            # RemoveShopProduct, RestoreShopProduct;
│   │                            # LoadCatalogFile gains brands, formats, locks, records and name sync
│   ├── Support/                 # PendingEntry, Format, CatalogBrandEntry, CatalogMergeError,
│   │                            # MergeResult, InvalidCommandInput; CatalogFileEntry gains brand and format
│   ├── Console/                 # PendingCatalogCommand, MergeCatalogCommand; LoadCatalogCommand gains --brands
│   ├── Controllers/             # CatalogProductController (shop read); ShopProductController gains remove, restore
│   ├── Models/                  # Brand, CatalogChange; CatalogStatus::Merged,
│   │                            # FormatUnit, CatalogChangeAction, CatalogTermKind::Brand
│   ├── database/catalog/        # brands.json (new)
│   ├── database/migrations/     # product columns, brands, changes, shop_products columns
│   ├── routes.php               # + catalog product read, remove, restore (shop group)
│   └── Tests/                   # new tests; 007 tests updated
└── postman/shop-keeper.postman_collection.json   # folder 5 gains the new shop requests
```

**Structure Decision**: The 007 `Catalog` module grows. No other module changes: there is no role, and the queue shows no shop data, so neither Identity nor Shops is involved.

## Design Notes

- **007 stays as it is**: one barcode per product (clarified), so 007's lookup, pending creation and loader keep their code; 008 adds columns, fields and rules.
- **One writer of names**: names, spellings, brands and formats change only through `catalog:load` and the catalog files, so a load never undoes another change, and every change is reviewed in git (research R1).
- **Commands stay thin**: parse, call one action, print. Rules and transactions live in `LoadCatalogFile`, `ListPendingProducts` and `MergeCatalogProducts` (research R15).
- **Locks**: `LoadCatalogFile` reads its targets inside its transaction with `lockForUpdate`; merges lock both products in id order. A load and a merge on the same product never mix (FR-011).
- **The counter**: `linked_shops_count` changes only in `CreateShopProduct`, `RemoveShopProduct`, `RestoreShopProduct` and `MergeCatalogProducts`; a shared test helper compares it with a fresh count after each story's tests.
- **Brand search through terms**: brand names become term rows of kind `brand` on each product of the brand; `FindCloseProducts` skips that kind.
- **Catalog names on shop products**: a stored copy kept in step by `SyncCatalogNames` inside every load and merge; products carrying one have no `identity_key`, so a catalog rename never collides with a merchant's names (research R14).
- **Removed products stay in the unique indexes**: so a re-add finds them and answers `product.removed`, and 007's "one product per name and per catalog product" still holds (research R13).
- **Docs to update in the same PR**:
  - the 007 contract (new CatalogProduct and ShopProduct fields);
  - the backend README (the catalog commands and the brands file);
  - the root `CLAUDE.md` focus line.

  The backend `CLAUDE.md` and the constitution were already updated with the 2026-11-19 decision.

## Spec Changes Made During Planning

The clarify session of 2026-11-16 (5 questions, recorded in the spec) changed the first plan of 2026-11-14:

- One barcode per catalog product: the barcode table and the rework of 007 code are gone; merging two barcoded products is refused (research R2, R6).
- Formats confirmed as whole quantities in five units (research R8).
- (Import through a command and the API, and shop name and city in the queue: both superseded on 2026-11-19.)

Added on 2026-11-16 by the owner during the PR review:

- A merchant can remove a product from sale at any time (User Story 6, FR-029 to FR-035, research R13).
- A product with a checked barcode carries the catalog name and cannot be renamed; this changes spec 007's naming rule for barcoded products (User Story 4, FR-023 to FR-028, research R14).

Decided on 2026-11-19 by the owner:

- **Removed**:
  - the operator role, with its `users` column, middleware, policies, grant and revoke commands, and seeded operator;
  - every operator endpoint and the Postman operator folder;
  - the import by API and by CSV command;
  - the shop name and city in the queue, and the `DescribeShops` action.
- **Constitution**: 3.3.0 removes the 3.2.0 exception to principle IV.
- **Checking**: the queue and merges are server commands; checks, corrections, brands and formats go through the catalog files and `catalog:load` (research R1, R4 to R7, R9, R10, R15).
- **Roles**: platform and shop roles will be designed before spec 014.

## Complexity Tracking

No constitution violations.
