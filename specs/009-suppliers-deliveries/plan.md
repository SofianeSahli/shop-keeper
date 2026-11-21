# Implementation Plan: Contacts, Transactions and Payments (purchases)

**Branch**: `009-suppliers-deliveries` | **Date**: 2026-11-21 (rework) | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/009-suppliers-deliveries/spec.md`

## Summary

Replace the first version's single `Purchasing` module with three modules and keep `Inventory`:

- **Contacts**: the shop's address book (supplier and customer roles), with a balance read through an interface.
- **Transactions**: one model for exchanges of products, with a kind; this feature delivers `purchase`. Recording writes the transaction, its lines and one stock movement per line in one database transaction; cancelling writes a cancellation and opposite movements, and is refused while payments still count.
- **Payments**: money paid or received, recorded only. Each payment belongs to a contact and/or one transaction; a transaction can have many. Payments implements the interfaces Contacts and Transactions declare for balances and amounts paid.
- **Inventory**: stock movements, kinds renamed to `purchase` and `purchase_cancellation`.

Every record takes a client UUID; resending answers the existing record. Cancellations share one helper and keep a reason and the account. Backend only.

## Technical Context

**Language/Version**: PHP 8.4.27 (Docker image), Laravel 13

**Primary Dependencies**: none new

**Storage**: MySQL 8.4; tests use `shop_keeper_test`

**Testing**: Pest feature tests per endpoint and per rule (stock, amounts paid, balances, cancellation, repeats, isolation), module tests with fake interface bindings; Pint

**Target Platform**: Docker Compose from 006

**Project Type**: REST API

**Performance Goals**: a 200-line purchase in one request under 1 s (SC-001); lists under 200 ms p95 with a few thousand transactions per shop, figures computed per page in grouped queries

**Constraints**: no user-facing text; integer millimes; money and stock records never updated or deleted; shop time zone from configuration; another shop's records answered as not existing; no module reads another module's tables

**Scale/Scope**: 12 endpoints, 3 new modules, 6 new tables, Inventory kept, the first version's Purchasing module removed

## Constitution Check

*Version 3.1.0. Checked before research and again after design.*

| Principle | Status | How |
|-----------|--------|-----|
| I. Monorepo boundaries | Pass | Only `shop-keeper-backend/`. Modules talk through actions and consumer-owned interfaces (research R1). |
| II. Mobile experience | N/A | No screen. Paid at once is two requests the app sends together. |
| III. Internationalization | Pass | Stable codes only; field codes name the line. |
| IV. Secure backend | Pass | `/api/v1`, Form Requests, thin controllers, one action per use case, a policy per model, shop-scoped queries, unique indexes and a CHECK constraint, Postman and contract. |
| V. Testability | Pass | FR-025 tests per endpoint; rule tests for SC-002 to SC-006; interfaces faked in module tests. |
| VI. Offline-first | Pass | Client UUIDs on every record including lines and cancellations (research R4). |
| VII. Product scope | Pass | Roadmap step 1.3; owner rework of 2026-11-21. Customer credit and payment methods as in VII; sales reuse these modules in 011. |
| VIII. Data and privacy | Pass | Contact phone, address and note shown only to their shop and never logged. |
| Money constraint | Pass | Unsigned `BIGINT` millimes; totals capped at the largest exact JavaScript integer. |
| Immutable money and stock records | Pass | No update or delete path; cancellations are rows; `stock_quantity` is a derived value (research R3). |

No violations.

## Project Structure

### Documentation (this feature)

```text
specs/009-suppliers-deliveries/
├── plan.md, research.md, data-model.md, quickstart.md
├── contracts/api.md
├── checklists/requirements.md
└── tasks.md
```

### Source Code

```text
shop-keeper-backend/
├── app/
│   ├── Shared/
│   │   ├── ShopClock.php                          # kept
│   │   └── Records/RecordWithSameId.php, Created.php, CancelOnce.php, CancellationResource.php   # moved from Purchasing + new helpers
│   └── Modules/
│       ├── Catalog/                               # kept: stock_quantity, ChangeStockQuantities, FindProductIdsOfShop
│       ├── Inventory/                             # kept; StockMovementKind: Purchase, PurchaseCancellation
│       ├── Contacts/
│       │   ├── Actions/        # CreateContact, UpdateContact, ContactNameRules, FindContactOfShop, FindContactIdsOfShop,
│       │   │                   # FindContactNamesOfShop
│       │   ├── Contracts/      # ContactBalances
│       │   ├── Controllers/    # ContactController, Requests/, Resources/
│       │   ├── Models/         # Contact
│       │   ├── Policies/       # ContactPolicy
│       │   ├── Support/        # NewContact, ContactChanges
│       │   ├── database/migrations/, factories/
│       │   ├── routes.php, ContactsServiceProvider.php
│       │   └── Tests/
│       ├── Transactions/
│       │   ├── Actions/        # RecordTransaction, CancelTransaction, FindTransactionOfShop,
│       │   │                   # LockTransactionForPayment, SumTransactionTotalsByContact
│       │   ├── Contracts/      # TransactionPayments
│       │   ├── Controllers/    # TransactionController, Requests/, Resources/
│       │   ├── Models/         # Transaction, TransactionLine, TransactionCancellation, TransactionKind, PaymentState
│       │   ├── Policies/       # TransactionPolicy
│       │   ├── Support/        # NewTransaction, NewTransactionLine, TransactionForPayment
│       │   ├── database/migrations/
│       │   ├── routes.php, TransactionsServiceProvider.php
│       │   └── Tests/
│       └── Payments/
│           ├── Actions/        # RecordPayment, CancelPayment, FindPaymentOfShop
│           ├── Support/        # NewPayment, PaymentFigures (implements ContactBalances, TransactionPayments)
│           ├── Controllers/    # PaymentController, Requests/, Resources/
│           ├── Models/         # Payment, PaymentCancellation, PaymentDirection, PaymentMethod
│           ├── Policies/       # PaymentPolicy
│           ├── database/migrations/
│           ├── routes.php, PaymentsServiceProvider.php   # binds both interfaces
│           └── Tests/
├── bootstrap/providers.php      # − Purchasing; + Contacts, Transactions, Payments
└── postman/                     # − Suppliers, Deliveries, Supplier payments; + Contacts, Purchases, Payments
```

**Structure Decision**: three modules as the owner asked on 2026-11-21 (research R1). The interfaces live with the module that needs the figure, so Payments can later move to a shared package without Contacts or Transactions changing.

## Design Notes

- **Recording a purchase**: `RecordTransaction` reads the id, checks the kind, the contact (`FindContactIdsOfShop`) and the products (`FindProductIdsOfShop`), inserts the transaction first (a twin request waits on its key), then the lines, then the movements through `RecordStockMovements`.
- **Recording a payment**: `RecordPayment` checks the contact, then, when a transaction is named, calls `LockTransactionForPayment` (Transactions), which locks the row and returns its kind, contact, total and whether it is cancelled. Payments then sums its own payments for that transaction under the lock and refuses `amount.exceeds_left`. The direction a kind implies is `TransactionKind::paymentDirection()`.
- **Cancelling**: `CancelOnce` locks the record, answers it if already cancelled, refuses a cancellation id used elsewhere, inserts the cancellation with reason and account, then runs the caller's step. `CancelTransaction` first asks `TransactionPayments::hasActivePayments` under the lock, then takes stock back.
- **Figures**: `PaymentFigures` answers `amountsPaid(shopId, transactionIds)`, `hasActivePayments(transactionId)`, `paymentsOf(shopId, transactionId)` (Payment shapes, newest first, for one transaction's answer) and `balances(shopId, contactIds)` with grouped queries; balances add the purchase totals from `SumTransactionTotalsByContact`. Resources ask the interfaces once per page.
- **Contact names**: transactions and payments show a ContactSummary. They never read the `contacts` table: their resources call Contacts' `FindContactNamesOfShop(shopId, contactIds)` once per page. The Cancellation shape is one `App\Shared\Records\CancellationResource`, used by both modules.
- **Cross-shop answers**: every lookup is scoped to the current shop; another shop's id is simply not found. Ids held elsewhere answer `id.taken`.
- **Docs to update in the same PR**: the contract, the Postman collection, the backend guide (`CLAUDE.md`: modules list and stock line), the backend README module tree, and the root `CLAUDE.md` current focus.

## Spec Changes Made During Planning

- Totals above 9 007 199 254 740 991 millimes are refused (research R12).
- Lines answer `product_id`, not the product's name (Transactions does not read Catalog's tables).
- A payment naming a transaction and no contact takes the transaction's contact (research R8).

## Complexity Tracking

No constitution violations.
