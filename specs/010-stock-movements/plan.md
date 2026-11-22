# Implementation Plan: Stock Movements and Low-Stock Warning

**Branch**: `010-stock-movements` | **Date**: 2026-11-21 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/010-stock-movements/spec.md`

## Summary

Extend the Inventory module of 009 and add one Catalog setting:

- **Inventory**: a **stock adjustment** (opening stock, count or loss) with 1 to 200 lines, each line the source of one stock movement, recorded in one database transaction through the existing `RecordStockMovements`. A count line keeps what was counted and what the app showed, and records the difference. A loss line keeps a reason from a fixed list and an optional note. Opening stock is unique per product through a unique column. A product's history lists its movements newest first with the stock after each, using a new server `sequence` on `stock_movements`.
- **Catalog**: a `low_stock_level` per product, set through the existing product update, and a list of products at or under their level.
- **Transactions**: implements Inventory's new `PurchaseLineSources` interface so the history can show a purchase's id, date and account.

Every adjustment and line takes a client UUID; resending answers the existing adjustment. Nothing is ever updated, deleted or cancelled. Backend only.

## Technical Context

**Language/Version**: PHP 8.4.27 (Docker image), Laravel 13

**Primary Dependencies**: none new

**Storage**: MySQL 8.4; tests use `shop_keeper_test`

**Testing**: Pest feature tests per endpoint (success, validation, unauthenticated, no shop, another shop) and per rule (stock sums, opening once, count with movements in between, stock after each history entry, low-stock list, repeats, races); a fake `PurchaseLineSources` in Inventory tests; Pint

**Target Platform**: Docker Compose from 006

**Project Type**: REST API

**Performance Goals**: a 200-line adjustment in one request under 1 s (SC-001); a history page and the low-stock list under 200 ms p95 with a few thousand movements per product and a few thousand products per shop

**Constraints**: no user-facing text; stock movements never updated or deleted; `stock_quantity` changed only through `ChangeStockQuantities`; purchase kinds of 009 unchanged; shop time zone from configuration; another shop's records answered as not existing; no module reads another module's tables; migrations of 007 and 009 not edited

**Scale/Scope**: 5 new endpoints, 1 changed endpoint, 2 new tables, 2 new columns, 3 new movement kinds

## Constitution Check

*Version 3.1.0. Checked before research and again after design.*

| Principle | Status | How |
|-----------|--------|-----|
| I. Monorepo boundaries | Pass | Only `shop-keeper-backend/`. Inventory reaches Catalog through its actions and Transactions through a consumer-owned interface (research R1). |
| II. Mobile experience | N/A | No screen. A count needs only the number on the shelf; the app adds the stock it showed. |
| III. Internationalization | Pass | Stable codes only, loss reasons included; field codes name the line. |
| IV. Secure backend | Pass | `/api/v1`, one Form Request per kind, thin controllers, one action, shop-scoped queries, unique indexes for repeats and opening stock, Postman and contract. |
| V. Testability | Pass | FR-020 tests per endpoint and per rule (SC-002 to SC-007); the interface is faked in Inventory tests. |
| VI. Offline-first | Pass | Client UUIDs on adjustments and lines; the count rule keeps movements that arrive in between (research R6). |
| VII. Product scope | Pass | Roadmap step 1.3, last part; owner answers of 2026-11-21. |
| VIII. Data and privacy | Pass | Loss notes shown only to their shop and never logged. |
| Immutable money and stock records | Pass | No update, delete or cancel path for adjustments, lines or movements; `sequence` is set once on insert; the low-stock level is a product setting, not a stock record. |

No violations.

## Project Structure

### Documentation (this feature)

```text
specs/010-stock-movements/
├── plan.md, research.md, data-model.md, quickstart.md
├── contracts/api.md
├── checklists/requirements.md
└── tasks.md             # speckit-tasks, not this command
```

### Source Code

```text
shop-keeper-backend/
├── app/Modules/
│   ├── Inventory/
│   │   ├── Actions/        # RecordStockMovements (kept), RecordStockAdjustment, ListStockMovements
│   │   ├── Contracts/      # PurchaseLineSources
│   │   ├── Controllers/    # StockAdjustmentController (openings, counts, losses), StockMovementController (history)
│   │   │   ├── Requests/   # RecordOpeningStockRequest, RecordCountRequest, RecordLossRequest, ListStockMovementsRequest,
│   │   │   │               # AdjustmentFieldRules (id, date, lines, product)
│   │   │   └── Resources/  # StockAdjustmentResource, StockAdjustmentLineResource, StockMovementResource
│   │   ├── Models/         # StockMovement (+ sequence), StockMovementKind (+ 3 cases), StockAdjustment,
│   │   │                   # StockAdjustmentLine, StockAdjustmentKind, LossReason
│   │   ├── Support/        # NewStockMovement (kept), NewStockAdjustment, NewStockAdjustmentLine, PurchaseLineSource
│   │   ├── database/migrations/   # create stock_adjustments, create stock_adjustment_lines, add sequence to stock_movements
│   │   ├── routes.php, InventoryServiceProvider.php (+ routes)
│   │   └── Tests/
│   ├── Catalog/
│   │   ├── Actions/        # UpdateShopProduct (+ level), ListLowStockProducts
│   │   ├── Controllers/    # ShopProductController (+ lowStock), UpdateShopProductRequest (+ low_stock_level),
│   │   │                   # ListLowStockProductsRequest, ShopProductResource (+ low_stock_level)
│   │   ├── Models/         # ShopProduct (+ fillable, cast)
│   │   ├── database/migrations/   # add low_stock_level to shop_products
│   │   ├── routes.php      # products/low-stock before products/{product}
│   │   └── Tests/
│   └── Transactions/
│       ├── Support/        # DescribePurchaseLines implements Inventory\Contracts\PurchaseLineSources
│       └── TransactionsServiceProvider.php (binds it)
└── postman/                # + folder "8. Stock"; "Change the prices" also sets low_stock_level
```

**Structure Decision**: extend the existing Inventory and Catalog modules; no new module (research R1). Inventory gets its first routes and controllers.

## Design Notes

- **Recording an adjustment**: `RecordStockAdjustment::handle(NewStockAdjustment)` runs in one database transaction: `RecordWithSameId` on the adjustment id (same shop and kind answers the existing one; another kind answers `id.taken`), refuse used line ids, check products with `FindProductIdsOfShop`, for opening stock refuse products that already have one, insert the adjustment first (a twin request waits on its key, as `RecordTransaction`), insert the lines, then call `RecordStockMovements` with the adjustment's movement kind and one `NewStockMovement` per line (source = line id). The line's signed `quantity` is computed by `NewStockAdjustmentLine` from the kind.
- **Opening-stock race**: a unique violation on `opening_product_id` from a twin with another id re-reads the opening lines of the products and throws the field errors (research R5).
- **Count**: `quantity = counted − shown`, no lock, no read of the current stock (research R6).
- **History**: `ListStockMovements` checks the product with `FindProductIdsOfShop` (`product.not_found` otherwise), pages `stock_movements` by `sequence` descending, sums the movements up to the oldest on the page in one query and fills `stock_after` upwards, loads the page's adjustment lines and adjustments in one query each, and asks `PurchaseLineSources` once for the page's purchase lines (research R8, R9).
- **Low stock**: `ListLowStockProducts` in Catalog, cursor pages ordered by `stock_quantity`, id (research R11). `UpdateShopProduct` applies `low_stock_level` without the name checks when the name is not changing.
- **Account**: `created_by` on the adjustment from the signed-in account, answered as `recorded_by`.
- **Docs to update in the same PR**: the contract, the Postman collection and environment, `shop-keeper-backend/CLAUDE.md` (the `stock_movements` line and the low-stock level in Catalog), the backend README module tree, the root `CLAUDE.md` current focus, and README's specs table when closed.

## Spec Changes Made During Planning

- Each request also carries its own id (the adjustment), in addition to the id of each entry (research R2).
- The history shows, for a purchase cancellation, the day it was cancelled and the account that cancelled it; for a purchase, its date and the account that recorded it (research R9).
- The low-stock level is set through the existing product update (research R11).

## Complexity Tracking

No constitution violations.
