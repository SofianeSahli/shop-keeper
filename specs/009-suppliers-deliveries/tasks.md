---

description: "Task list for spec 009, contacts, transactions and payments (purchases)"
---

# Tasks: Contacts, Transactions and Payments (purchases)

**Input**: Design documents from `specs/009-suppliers-deliveries/` (reworked 2026-11-21)

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Required. FR-025: success, validation, unauthenticated, no-shop and cross-shop tests on every endpoint, plus rule tests for stock, amounts paid, balances, cancellations and repeated requests. Write each story's tests first and see them fail.

**Rework note**: The first 009 version (module `Purchasing`) is on this branch but was never merged. Phase 1 removes it and keeps what still fits (research R15).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: US1 to US8 as in spec.md
- Paths are relative to `shop-keeper-backend/` unless they start with `specs/`

## Path Conventions

- Modules: `app/Modules/Contacts/`, `app/Modules/Transactions/`, `app/Modules/Payments/`, `app/Modules/Inventory/`, each with `Actions/`, `Support/`, `Controllers/` (`Requests/`, `Resources/`), `Models/`, `Policies/`, `database/`, `Tests/`, and `Contracts/` where plan.md lists one
- Run every command in the container: `docker compose exec app <command>`
- Follow `shop-keeper-backend/CLAUDE.md`: thin controllers, one public `handle` per action, no comments, named constants, no call inside an argument, no module reads another module's tables, `vendor/bin/pint` before a task is done

---

## Phase 1: Remove the first version and move shared pieces

**Purpose**: Start from a clean base; keep Inventory, the stock column, `ShopClock` and the Catalog helpers

- [X] T001 Delete `app/Modules/Purchasing/` entirely (actions, controllers, requests, resources, models, policies, support, tests, factories, routes, provider and its three migrations) and remove `PurchasingServiceProvider` from `bootstrap/providers.php`
- [X] T002 Move `RecordWithSameId` and `Created` from the deleted `Purchasing/Support/` to `app/Shared/Records/` (namespace `App\Shared\Records`), unchanged in behavior; recover their content from git (`git show HEAD:shop-keeper-backend/app/Modules/Purchasing/Support/RecordWithSameId.php`)
- [X] T003 In `app/Modules/Inventory/Models/StockMovementKind.php`, rename the cases to `Purchase` (`purchase`) and `PurchaseCancellation` (`purchase_cancellation`); update `app/Modules/Inventory/Tests/RecordStockMovementsTest.php` and any reference
- [X] T004 Remove the "5. Suppliers", "6. Deliveries" and "7. Supplier payments" folders and their variables from `postman/shop-keeper.postman_collection.json` and `postman/shop-keeper.postman_environment.json`
- [X] T005 Run `php artisan migrate:fresh` and `php artisan test`: everything from 006, 007 and Inventory passes

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: No user story starts before this phase is done

- [X] T006 Create `ContactsServiceProvider`, `TransactionsServiceProvider` and `PaymentsServiceProvider` (each loads its migrations and its `routes.php` under `api/v1` with the `api` middleware, as `CatalogServiceProvider` does) and three `routes.php` files with one group using `auth:sanctum` then `RequireShop::ALIAS`; register the providers in `bootstrap/providers.php` in the order Contacts, Transactions, Payments
- [X] T007 Add migration `app/Modules/Contacts/database/migrations/2026_11_21_000001_create_contacts_table.php` exactly as data-model.md
- [X] T008 Add migrations in `app/Modules/Transactions/database/migrations/` for `transactions`, `transaction_lines` (no `shop_id`, research R16) and `transaction_cancellations` exactly as data-model.md (generated `sort_key` with `storedAs('lower(hex(`id`))')`, `created_by` and `cancelled_by` FK `users` restrict delete)
- [X] T009 Add migrations in `app/Modules/Payments/database/migrations/` for `payments` (with the CHECK constraint `contact_id IS NOT NULL OR transaction_id IS NOT NULL`) and `payment_cancellations` exactly as data-model.md
- [X] T010 [P] Create `Contact` model in `app/Modules/Contacts/Models/` (derives `compared_name` and `identity_key` on save with Catalog's `NameComparison`, `scopeOfShop`, boolean casts, `HasBinaryUuidKey`, `uuid()`) and its factory
- [X] T011 [P] Create in `app/Modules/Transactions/Models/`: `Transaction` (lines and cancellation relations, no relation to Contacts' model; no `updated_at`), `TransactionLine`, `TransactionCancellation`, enum `TransactionKind` (`Purchase`; method `paymentDirection()` returning the Payments-agnostic string `paid`), enum `PaymentState` (`unpaid`, `partly_paid`, `paid`, with `of(int $total, int $paid)`)
- [X] T012 [P] Create in `app/Modules/Payments/Models/`: `Payment`, `PaymentCancellation`, enums `PaymentDirection` (`paid`, `received`) and `PaymentMethod` (`cash`, `cheque`, `card`, `transfer`)
- [X] T013 Create the interfaces `App\Modules\Contacts\Contracts\ContactBalances` (`balances(int $shopId, array $contactIds): array`) and `App\Modules\Transactions\Contracts\TransactionPayments` (`amountsPaid(int $shopId, array $transactionIds): array`, `hasActivePayments(string $transactionId): bool`, `paymentsOf(int $shopId, string $transactionId): array` returning Payment shapes newest first)
- [X] T014 Create `App\Shared\Records\CancelOnce` (research R5): locks the record row, answers it unchanged when a cancellation exists, refuses a cancellation id used elsewhere with `id.taken`, inserts the cancellation with `reason` and `cancelled_by`, runs a callback the caller passes, returns `Created`; with unit-level feature tests in `tests/Feature/Shared/CancelOnceTest.php` on a test table
- [X] T015 Create `FindContactIdsOfShop` (which of the given ids are contacts of the shop) and `FindContactNamesOfShop` (id to name for the given contacts of the shop, for ContactSummary) in `app/Modules/Contacts/Actions/` and `SumTransactionTotalsByContact` in `app/Modules/Transactions/Actions/` (non-cancelled purchase totals per contact for a shop)
- [X] T016 [P] Create test helpers `app/Modules/Contacts/Tests/Support/ContactsScenario.php`, `app/Modules/Transactions/Tests/Support/TransactionsScenario.php` (purchase body builder, `stockOf`) and `app/Modules/Payments/Tests/Support/PaymentsScenario.php` (payment body builder, `balanceOf`, `amountLeftOf`)

**Checkpoint**: Migrations run, models load, interfaces exist (not yet bound)

---

## Phase 3: User Story 1 - Keep my address book of contacts (P1) 🎯 MVP part 1

**Independent Test**: create, refuse a duplicate name, update roles, list by role (spec US1)

- [X] T017 [P] [US1] Write `app/Modules/Contacts/Tests/ContactTest.php`: create with every field and with name only; `contact.name_taken` with `existing_contact_id` for another case; repeat with the same id answers 200; every field code of data-model.md; update each field, `null` clears optional text, `fields.required`, `id.prohibited`; list ordered by name, `role=supplier`, `role=customer`, `role.invalid`; `balance` is 0 with a fake `ContactBalances` binding; unauthenticated and no-shop
- [X] T018 [US1] Create `ContactNameRules` (port of the first version's supplier name rule), `CreateContact`, `UpdateContact`, `FindContactOfShop` and `Support/NewContact`, `Support/ContactChanges` in `app/Modules/Contacts/`
- [X] T019 [US1] Create `CreateContactRequest`, `UpdateContactRequest`, `ListContactsRequest`, `ContactResource` (balance from `ContactBalances`, one call per page), `ContactSummary`, `ContactPolicy` and `ContactController` (`index`, `store`, `show`, `update`); add the four routes

**Checkpoint**: US1 works alone

---

## Phase 4: User Story 2 - Record a purchase (P1) 🎯 MVP part 2

**Independent Test**: purchase two products, read stock and prices, repeat the request (spec US2)

- [X] T020 [P] [US2] Write `app/Modules/Transactions/Tests/RecordPurchaseTest.php`: lines, amounts, total, `payment_state` `unpaid`, `recorded_by`; stock grows per product; prices unchanged; default and past dates, `transaction_date.in_future`; no contact; another shop's contact gives `contact_id.unknown`; `kind.required`, `kind.invalid` for `sale`; every line field code with its line index; 201 lines `lines.too_many`; `lines.total_too_high`; repeat answers 200 and stock changes once; one wrong line records nothing; with a fake `TransactionPayments`
- [X] T021 [US2] Create `Support/NewTransaction`, `Support/NewTransactionLine` and `RecordTransaction` in `app/Modules/Transactions/` (research and plan Design Notes: insert the transaction first, then lines, then `RecordStockMovements` with kind `Purchase`)
- [X] T022 [US2] Create `FindTransactionOfShop`, `RecordTransactionRequest`, `TransactionResource` (amount paid, left and state from `TransactionPayments`, one call per page; `lines` and `payments` only on a single transaction), `TransactionLineResource`, `App\Shared\Records\CancellationResource` (shared with Payments), `TransactionPolicy` and `TransactionController` (`store`, `show`); add the two routes

**Checkpoint**: A merchant can record purchases; stock is right

---

## Phase 5: User Story 3 - Record what I paid (P1)

**Independent Test**: pay a purchase in two times and on account; read figures (spec US3)

- [X] T023 [P] [US3] Write `app/Modules/Payments/Tests/RecordPaymentTest.php`: linked payment lowers amount left and sets `partly_paid` then `paid`; several payments on one purchase; `amount.exceeds_left`; on account changes no purchase and may go below zero; purchase with no contact paid with `contact` null; contact taken from the transaction; `contact_id.required`, `contact_id.mismatch`, `direction.mismatch` (`received` on a purchase), `transaction_id.unknown`, `transaction_id.cancelled`, every other field code; default and future dates; repeat answers 200; two payments racing for the last amount left: exactly one accepted
- [X] T024 [US3] Create `LockTransactionForPayment` and `Support/TransactionForPayment` in `app/Modules/Transactions/` (locks the row; returns kind, contact id, total, cancelled)
- [X] T025 [US3] Create `Support/NewPayment`, `RecordPayment` and `FindPaymentOfShop` in `app/Modules/Payments/` (plan Design Notes)
- [X] T026 [US3] Create `Support/PaymentFigures` in `app/Modules/Payments/` implementing `TransactionPayments`, bind it in `PaymentsServiceProvider`; the transaction answer now shows real amounts and its `payments`
- [X] T027 [US3] Create `RecordPaymentRequest`, `PaymentResource`, `PaymentPolicy` and `PaymentController` (`store`, `show`); add the two routes

---

## Phase 6: User Story 4 - Know what I owe each contact (P1)

- [X] T028 [P] [US4] Write `app/Modules/Payments/Tests/BalanceTest.php`: purchases minus `paid` plus `received`, cancelled records ignored, records with no contact ignored, below zero allowed, list and single contact agree, for any series of records (SC-003)
- [X] T029 [US4] Make `PaymentFigures` also implement `ContactBalances` (with `SumTransactionTotalsByContact`) and bind it; remove the fake bindings from the Contacts and Transactions feature tests that now run with the real one

---

## Phase 7: User Story 5 - Correct a mistake (P1)

- [X] T030 [P] [US5] Write `app/Modules/Transactions/Tests/CancelTransactionTest.php`: cancellation with and without reason, `reason.too_long`, account and time shown; stock taken back; second cancel answers 200 and changes nothing; `transaction.has_payments` while a payment counts, allowed after it is cancelled; cancellation id reused gives `id.taken`; no route updates or deletes a transaction or line
- [X] T031 [P] [US5] Write `app/Modules/Payments/Tests/CancelPaymentTest.php`: same cases for a payment; amount left and balance go back up
- [X] T032 [US5] Create `CancelTransaction` in `app/Modules/Transactions/Actions/` with `CancelOnce` (checks `hasActivePayments` under the lock, then `RecordStockMovements` with `PurchaseCancellation`) and `CancelPayment` in `app/Modules/Payments/Actions/` with `CancelOnce`
- [X] T033 [US5] Create `CancelRequest` in each of Transactions and Payments (`id`, `reason`), the `cancel` controller methods and both cancellation routes

---

## Phase 8: User Story 6 - Look back (P2)

- [X] T034 [P] [US6] Write `app/Modules/Transactions/Tests/ListTransactionsTest.php` and `app/Modules/Payments/Tests/ListPaymentsTest.php`: latest date first, ties by `sort_key`, cursor pages, filters `kind`, `contact_id`, `transaction_id`, another shop's id gives an empty page, figures right on every row, query count does not grow with the page size
- [X] T035 [US6] Add `ListTransactionsRequest`, `ListPaymentsRequest` and the `index` methods and routes

---

## Phase 9: User Story 7 - Only my shop's records (P1)

- [X] T036 [P] [US7] Write `app/Modules/Payments/Tests/IsolationTest.php` covering contacts, transactions and payments: read, update, cancel and filter another shop's records give not found or an empty page; using another shop's contact, product or transaction gives `…unknown`; reusing another shop's id for any record, line or cancellation gives `id.taken`; nothing revealed (SC-005)

---

## Phase 10: User Story 8 - Try it with standard tools (P3)

- [X] T037 [US8] Add the folders "Contacts", "Purchases" and "Payments" to `postman/shop-keeper.postman_collection.json` and the environment, following quickstart.md scenarios 1 to 16, with scripts that create ids and carry them over; `tests/Feature/Shared/ApiCollectionTest.php` passes. The owner runs the collection by hand

---

## Phase 11: Polish & Cross-Cutting Concerns

- [X] T038 [P] Extend `tests/Feature/Shared/NoSecretsInLogsTest.php` with a contact's phone, address and note
- [X] T039 [P] Add a rule test that every product's `stock_quantity` equals the sum of its movements after random purchases and cancellations (SC-002), and that no update or delete query ever touches the immutable tables (SC-006)
- [X] T040 [P] Update `shop-keeper-backend/CLAUDE.md` (module list: Contacts, Transactions, Payments, Inventory; the stock line; the Data rule on `shop_id`: child rows reached only through a shop-scoped parent, such as `transaction_lines`, carry none, research R16), the module tree in `shop-keeper-backend/README.md`, and the root `CLAUDE.md` current focus
- [X] T041 Compare `specs/009-suppliers-deliveries/contracts/api.md` with the routes, responses and codes; fix any drift in the same change
- [X] T042 Run `php artisan test` and `vendor/bin/pint --test`, then every quickstart.md scenario on the Docker stack

---

## Dependencies & Execution Order

- **Phase 1** first, then **Phase 2**, which blocks every story.
- **US1** (contacts) and **US2** (purchases) can run in parallel after Phase 2; US2 tests use a fake `TransactionPayments`.
- **US3** needs US1 and US2. **US4** needs US3. **US5** needs US2 and US3. **US6** needs US2 and US3. **US7** and **US8** need every other story.
- Within a story: tests first and failing, then support classes, actions, requests and resources, controller, routes.

## Implementation Strategy

1. Phases 1 and 2.
2. US1, then US2: an address book and purchases with stock (MVP).
3. US3 and US4: payments and balances.
4. US5, US6, US7, then US8 and Polish.

All of it ships in one pull request with one commit, as the repository rules require.

## Notes

- [P] tasks touch different files and depend on no unfinished task.
- Any endpoint change updates `contracts/api.md`, the tests and the collection in the same change.
- Log no personal data; no secret in code or git.
