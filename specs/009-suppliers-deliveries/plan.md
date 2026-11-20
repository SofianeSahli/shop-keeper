# Implementation Plan: Suppliers and Deliveries

**Branch**: `009-suppliers-deliveries` | **Date**: 2026-11-20 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/009-suppliers-deliveries/spec.md`

## Summary

Add a `Purchasing` module (suppliers, deliveries with their lines, payments to suppliers, and cancellations of deliveries and payments) and an `Inventory` module (stock movements). Recording a delivery writes the delivery, its lines, one stock movement per line and, optionally, a payment, in one transaction; each product's cached `stock_quantity` grows. Cancelling writes a cancellation and opposite movements. Every supplier shows a balance computed from its non-cancelled deliveries and payments. Product prices are never touched by deliveries. Every record takes a client UUID, and resending a creation answers the existing record. Backend only; `shop-keeper-mobile/` is not touched.

## Technical Context

**Language/Version**: PHP 8.4.27 (Docker image), Laravel 13

**Primary Dependencies**: none new

**Storage**: MySQL 8.4; tests use `shop_keeper_test`

**Testing**: Pest feature tests per endpoint and per rule (stock, balance, cancellation, repeats, isolation); Pint

**Target Platform**: Docker Compose from 006

**Project Type**: REST API

**Performance Goals**: a 200-line delivery recorded in one request under 1 s (SC-001); lists under 200 ms p95 with a few thousand deliveries per shop

**Constraints**: no user-facing text; integer millimes; money and stock records never updated or deleted; the shop's time zone from configuration; another shop's records answered as not existing

**Scale/Scope**: 12 endpoints, 2 new modules, 7 new tables, 1 new column on `shop_products`

## Constitution Check

*Version 3.1.0. Checked before research and again after design.*

| Principle | Status | How |
|-----------|--------|-----|
| I. Monorepo boundaries | Pass | Only `shop-keeper-backend/`. Purchasing reaches products and stock only through Catalog and Inventory actions (research R1). |
| II. Mobile experience | N/A | No screen. A delivery with its payment is one request, which keeps the future flow short. |
| III. Internationalization | Pass | Stable codes only; field codes name the line (`lines.1.quantity`). |
| IV. Secure backend | Pass | `/api/v1`, Form Requests, thin controllers, one action per use case, a policy per model, shop-scoped queries, unique indexes, Postman collection and contract. |
| V. Testability | Pass | Success, validation, unauthenticated, no-shop and cross-shop tests per endpoint (FR-020); rule tests for stock (SC-002), balance (SC-008), repeats (SC-003) and immutability (SC-005). |
| VI. Offline-first | Pass | Client UUIDs on every record, including lines, payments and cancellations; resending is harmless (research R4), ready for `sync/push` in 013. |
| VII. Product scope | Pass | Roadmap step 1.3, suppliers and deliveries; supplier debt added by the owner on 2026-11-20. Payment methods as in VII. |
| VIII. Data and privacy | Pass | Supplier phone shown only to its shop and never logged. |
| Money constraint | Pass | Unsigned `BIGINT` millimes, JSON integers, totals capped at the largest exact JavaScript integer (research R9). |
| Immutable money and stock records | Pass | Deliveries, lines, payments, cancellations and movements have no update or delete path; cancellations are new rows (research R5). The cached `stock_quantity` is a derived value (research R3). |

No violations, so Complexity Tracking is empty.

## Project Structure

### Documentation (this feature)

```text
specs/009-suppliers-deliveries/
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
│   │   └── ShopClock.php                      # today() in the shop's time zone
│   └── Modules/
│       ├── Catalog/
│       │   ├── Actions/ChangeStockQuantities.php, FindProductIdsOfShop.php
│       │   ├── database/migrations/2026_11_20_000001_add_stock_quantity_to_shop_products_table.php
│       │   └── Controllers/Resources/ShopProductResource.php   # + stock_quantity
│       ├── Inventory/
│       │   ├── Actions/RecordStockMovements.php
│       │   ├── Models/StockMovement.php, StockMovementKind.php
│       │   ├── Support/NewStockMovement.php
│       │   ├── database/migrations/
│       │   ├── InventoryServiceProvider.php
│       │   └── Tests/
│       └── Purchasing/
│           ├── Actions/        # CreateSupplier, UpdateSupplier, RecordDelivery, CancelDelivery,
│           │                   # RecordSupplierPayment, CancelSupplierPayment
│           ├── Support/        # NewSupplier, SupplierChanges, NewDelivery, NewDeliveryLine,
│           │                   # NewSupplierPayment, Created
│           ├── Controllers/    # SupplierController, DeliveryController, SupplierPaymentController,
│           │                   # Requests/, Resources/
│           ├── Models/         # Supplier, Delivery, DeliveryLine, DeliveryCancellation,
│           │                   # SupplierPayment, SupplierPaymentCancellation, PaymentMethod
│           ├── Policies/       # SupplierPolicy, DeliveryPolicy, SupplierPaymentPolicy
│           ├── database/migrations/, factories/
│           ├── routes.php
│           ├── PurchasingServiceProvider.php
│           └── Tests/          # feature tests, Support/PurchasingScenario.php
├── bootstrap/providers.php      # + InventoryServiceProvider, PurchasingServiceProvider
├── config/shop_keeper.php       # + shop_time_zone
├── .env.example                 # + SHOP_TIME_ZONE
└── postman/                     # + Suppliers, Deliveries, Supplier payments; new variables
```

**Structure Decision**: Module layout from `shop-keeper-backend/CLAUDE.md`. Two modules because stock grows into its own area in 010 while suppliers, deliveries and debt are read together (research R1).

## Design Notes

- **One transaction per delivery**: `RecordDelivery` reads the id, checks line and payment ids, the supplier and the products, inserts the delivery first (so a twin request waits on its key), then lines, the payment through `RecordSupplierPayment`, and the movements through `RecordStockMovements`, which calls `ChangeStockQuantities` in product id order to avoid deadlocks.
- **Cancellation**: `CancelDelivery` and `CancelSupplierPayment` lock the record row, answer it if already cancelled, else insert the cancellation (and movements for a delivery).
- **Balance**: `Supplier::scopeWithBalance` adds two `withSum` columns that skip cancelled records; the resource shows their difference.
- **Cross-shop answers**: records are looked up with the current shop's scope, so another shop's id is simply not found; policies are still applied. Ids held elsewhere answer `id.taken`, never the record.
- **Docs to update in the same PR**: this spec's contract, the Postman collection and environment, `.env.example`, the backend guide's line on stock (now `stock_quantity` and `stock_movements`), and the root `CLAUDE.md` current focus.

## Spec Changes Made During Planning

- A delivery total above 9 007 199 254 740 991 millimes is refused with `lines.total_too_high` (research R9), so amounts stay exact in the app.
- Delivery lines answer `product_id`, not the product's name: Purchasing does not read Catalog's tables, and the app holds its products locally.
- The shop's time zone is one setting for all shops, `Africa/Tunis` by default (research R8).

## Complexity Tracking

No constitution violations.
