---
description: "Task list for the Kridi Credit Ledger refactor and full CRUD"
---

# Tasks: Kridi Credit Ledger Refactor

**Input**: Design documents from `/specs/001-kridi-credit-ledger/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Organization**: Tasks are grouped by user story to enable incremental implementation and validation.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prepare the mobile project to implement the feature using its mandated styling and state patterns.

- [X] T001 Add NativeWind and its Expo-compatible styling dependencies to `shop-keeper-mobile/package.json` and configure NativeWind processing in `shop-keeper-mobile/babel.config.js`, `shop-keeper-mobile/tailwind.config.js`, and `shop-keeper-mobile/metro.config.js`
- [X] T002 Add Redux Toolkit to `shop-keeper-mobile/package.json` and create the application store and provider in `shop-keeper-mobile/src/store/index.ts` and `shop-keeper-mobile/src/app/_layout.tsx`
- [X] T003 Add Expo Camera barcode scanning support in `shop-keeper-mobile/package.json` and `shop-keeper-mobile/app.json`, keeping the permission prompt localized through `shop-keeper-mobile/src/i18n/translations/en.json` and `shop-keeper-mobile/src/i18n/translations/fr.json`
- [X] T004 Add the mobile type-check and focused validation scripts in `shop-keeper-mobile/package.json` and ensure strict JSON/style typing remains enabled in `shop-keeper-mobile/tsconfig.json`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish safe persistence, auditing, validation, translations, and routing before implementing story flows.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T005 Extend the SQLite schema and migrations for product/customer active flags, ticket/payment void timestamps, and `ledger_audit_events` in `shop-keeper-mobile/src/db/schema.ts` and `shop-keeper-mobile/src/db/migrations.ts`
- [X] T006 Implement database initialization and transaction helpers in `shop-keeper-mobile/src/db/index.ts`; a ledger mutation, its audit event when applicable, and its pending-change entry MUST commit atomically
- [X] T007 Implement shared integer-millime and required-text validators in `shop-keeper-mobile/src/lib/money.ts` and `shop-keeper-mobile/src/utils/validation.ts`; preserve the data-model constraints `selling_price_millimes must be greater than zero`, `quantity must be greater than zero`, and `line_total_millimes must equal unit_price_millimes * quantity`
- [X] T008 Implement typed SQLite row mapping and repository transaction primitives in `shop-keeper-mobile/src/features/ledger/services/ledgerRepository.ts`, preserving exact integer millimes and nullable barcode/identity fields
- [X] T009 Implement append-only audit events and local pending-change persistence in `shop-keeper-mobile/src/features/ledger/services/auditService.ts` and `shop-keeper-mobile/src/features/ledger/services/pendingChangeService.ts`; pending changes MUST remain local and MUST NOT trigger network upload
- [X] T010 Implement shared transient ledger UI state and database refresh actions in `shop-keeper-mobile/src/features/ledger/store/ledgerSlice.ts` and register it in `shop-keeper-mobile/src/store/index.ts`
- [X] T011 Complete Arabic and French translations, persist selected language, and provide direction helpers in `shop-keeper-mobile/src/i18n/index.ts`, `shop-keeper-mobile/src/i18n/translations/en.json`, `shop-keeper-mobile/src/i18n/translations/fr.json`, and `shop-keeper-mobile/src/i18n/translations/ar.json`
- [X] T012 Create the ledger route group and reusable navigation shell in `shop-keeper-mobile/src/app/ledger/_layout.tsx` and `shop-keeper-mobile/src/app/ledger/index.tsx`
- [X] T013 Define reusable class-based styling tokens and ledger styles in `shop-keeper-mobile/src/global.css` and `shop-keeper-mobile/src/features/ledger/styles/ledger.css`; TS/TSX files MUST NOT contain `StyleSheet.create`, inline style objects, or CSS declarations

**Checkpoint**: All user stories can now use the shared store, translations, validation, database, and route shell.

---

## Phase 3: User Story 1 - Manage Products and Customers (Priority: P1)

**Goal**: Complete offline create, view, find, update, and remove flows for products and customers while retaining historical references.

**Independent Test**: Offline, create and find a product and pseudonymous customer, edit both, remove them from active use, restart the app, and verify the active lists and historical ticket references remain correct.

### Implementation for User Story 1

- [X] T014 [P] [US1] Implement product create/read/search/update/soft-remove operations and active barcode duplicate resolution in `shop-keeper-mobile/src/features/ledger/services/productService.ts`; the data-model constraints are `barcode nullable`, `name must be non-empty`, and `selling_price_millimes must be greater than zero`
- [X] T015 [P] [US1] Implement customer create/read/search/update/soft-remove operations and random internal-code generation in `shop-keeper-mobile/src/features/ledger/services/customerService.ts`; `display_label is required and trimmed`, real identity fields are optional, and duplicate labels may be used when they identify different people
- [X] T016 [US1] Build reusable translated form fields, amount input, validation feedback, loading/empty/error states, and destructive confirmation in `shop-keeper-mobile/src/features/ledger/components/forms/LedgerField.tsx`, `shop-keeper-mobile/src/features/ledger/components/forms/MillimeInput.tsx`, `shop-keeper-mobile/src/features/ledger/components/feedback/FeedbackState.tsx`, and `shop-keeper-mobile/src/features/ledger/components/feedback/ConfirmAction.tsx`
- [X] T017 [US1] Build product list, search, create, edit, and deactivate screens using reusable components in `shop-keeper-mobile/src/features/ledger/screens/ProductCatalogScreen.tsx` and `shop-keeper-mobile/src/features/ledger/screens/ProductFormScreen.tsx`
- [X] T018 [US1] Build customer list, search, create, edit, and deactivate screens with optional identity fields in `shop-keeper-mobile/src/features/ledger/screens/CustomerListScreen.tsx` and `shop-keeper-mobile/src/features/ledger/screens/CustomerFormScreen.tsx`
- [X] T019 [US1] Connect catalog/customer screens to the ledger routes and shared state in `shop-keeper-mobile/src/app/ledger/products.tsx`, `shop-keeper-mobile/src/app/ledger/customers.tsx`, and `shop-keeper-mobile/src/features/ledger/store/ledgerSlice.ts`

**Checkpoint**: Product and customer CRUD work offline, show clear validation/recovery states, and preserve historical references.

---

## Phase 4: User Story 2 - Manage Customer Tickets (Priority: P1)

**Goal**: Create, view, edit, and remove customer tickets with barcode/manual product entry, visible totals, snapshots, and auditable financial effects.

**Independent Test**: Create a multi-line ticket offline using scanned or manually selected items, edit quantities and verify totals, save/reopen, then correct or remove it and verify snapshots, audit history, and balances remain consistent.

### Implementation for User Story 2

- [X] T020 [US2] Implement ticket draft line add/remove/update, quantity validation, total calculation, and snapshot creation in `shop-keeper-mobile/src/features/ledger/services/ticketDraftService.ts` and `shop-keeper-mobile/src/features/ledger/services/ticketService.ts`; `Each ticket has one or more lines`, `quantity must be greater than zero`, `unit_price_millimes must be greater than zero`, and `line_total_millimes must equal unit_price_millimes * quantity`
- [X] T021 [US2] Implement atomic ticket create/read/update/void repository operations with immutable line snapshots, audit events, and pending changes in `shop-keeper-mobile/src/features/ledger/services/ticketRepository.ts`; `total_millimes must be greater than zero when saved`
- [X] T022 [US2] Implement reusable ticket line, product picker, barcode scanner result, and live total components in `shop-keeper-mobile/src/features/ledger/components/ticket/TicketLineEditor.tsx`, `shop-keeper-mobile/src/features/ledger/components/ticket/ProductPicker.tsx`, `shop-keeper-mobile/src/features/ledger/components/ticket/BarcodeScanner.tsx`, and `shop-keeper-mobile/src/features/ledger/components/ticket/TicketTotal.tsx`
- [X] T023 [US2] Build the ticket creation/editing flow with customer selection, manual item selection, barcode scan retry, unknown barcode add-product path, cancel, and incomplete-ticket refusal in `shop-keeper-mobile/src/features/ledger/screens/TicketFormScreen.tsx`
- [X] T024 [US2] Build customer ticket history and ticket detail with snapshot values, payment/status summary, correction, and removal confirmation in `shop-keeper-mobile/src/features/ledger/screens/TicketListScreen.tsx` and `shop-keeper-mobile/src/features/ledger/screens/TicketDetailScreen.tsx`
- [X] T025 [US2] Connect ticket list, detail, create, and edit screens to Expo routes and shared refresh state in `shop-keeper-mobile/src/app/ledger/tickets.tsx`, `shop-keeper-mobile/src/app/ledger/ticket-create.tsx`, `shop-keeper-mobile/src/app/ledger/ticket-edit.tsx`, and `shop-keeper-mobile/src/features/ledger/store/ledgerSlice.ts`

**Checkpoint**: Tickets can be created, viewed, corrected, and voided offline; unknown barcode and refusal paths are handled without losing a valid draft.

---

## Phase 5: User Story 3 - Manage Payments and Review Balances (Priority: P1)

**Goal**: Complete payment CRUD, explicit single-ticket allocation from customer balance, payment-derived status, balance sorting, and traceable correction/removal.

**Independent Test**: Create multiple tickets, record partial/full payments both from ticket and customer flows, correct and void payments, and verify ticket status, audit history, and customer balance ordering after every operation.

### Implementation for User Story 3

- [X] T026 [US3] Implement payment creation/update/void validation and ticket status derivation from non-voided payments in `shop-keeper-mobile/src/features/ledger/services/paymentService.ts`; `amount_millimes must be greater than zero`, amount cannot exceed remaining ticket balance, and paid tickets reject new payments
- [X] T027 [US3] Implement atomic payment create/read/update/void persistence with before/after audit events and pending-change records in `shop-keeper-mobile/src/features/ledger/services/paymentRepository.ts`; corrections validate after excluding the payment being edited
- [X] T028 [US3] Implement customer balance calculation and customer-balance payment candidate selection in `shop-keeper-mobile/src/features/ledger/services/customerBalanceService.ts`; balance equals the sum of remaining non-voided ticket amounts, and customers sort from greatest balance to least
- [X] T029 [US3] Build reusable payment editor and one-ticket selection dialog with oldest unpaid ticket preselected in `shop-keeper-mobile/src/features/ledger/components/payment/PaymentForm.tsx` and `shop-keeper-mobile/src/features/ledger/components/payment/TicketSelectionDialog.tsx`
- [X] T030 [US3] Build customer balance-sorted list, customer ledger, payment history, and audit history views in `shop-keeper-mobile/src/features/ledger/screens/CustomerBalanceScreen.tsx`, `shop-keeper-mobile/src/features/ledger/screens/CustomerLedgerScreen.tsx`, and `shop-keeper-mobile/src/features/ledger/screens/LedgerHistoryScreen.tsx`
- [X] T031 [US3] Connect payment, balance, customer ledger, and history views to the Expo routes and Redux refresh flow in `shop-keeper-mobile/src/app/ledger/payments.tsx`, `shop-keeper-mobile/src/app/ledger/balances.tsx`, `shop-keeper-mobile/src/app/ledger/customer-ledger.tsx`, and `shop-keeper-mobile/src/app/ledger/history.tsx`

**Checkpoint**: Payment create/view/correction/removal and balance/status views are independently verifiable offline with no overpayment or history loss.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Verify all specification scenarios and remove only proven unused code.

- [x] T032 [P] Complete every manual scenario in `specs/001-kridi-credit-ledger/quickstart.md` on Android and iOS, including app restart while offline, Arabic RTL, French LTR, cancellation, validation refusal, and correction/removal history — Closed 2026-10-31 by the owner before moving to the phase 1 backend.
- [X] T033 [P] Remove only ledger files, exports, imports, or configuration proven unused after checking Expo routes, platform-specific modules, package scripts, and all references in `shop-keeper-mobile/src/`, `shop-keeper-mobile/app.json`, and `shop-keeper-mobile/package.json`
- [X] T034 Run lint and strict TypeScript checks and fix feature-introduced issues in `shop-keeper-mobile/package.json`, `shop-keeper-mobile/src/`, and `shop-keeper-mobile/tsconfig.json`
- [X] T035 Update the local operation contract and quickstart if final persistence, audit, correction, removal, or user-visible flows differ from the documented behavior in `specs/001-kridi-credit-ledger/contracts/local-operations-contract.md` and `specs/001-kridi-credit-ledger/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies; config and dependency setup must finish before feature wiring.
- **Foundational (Phase 2)**: Depends on Setup and blocks all user stories.
- **User Stories (Phase 3+)**: Depend on the complete database, validation, audit, translation, store, and route foundation.
- **Polish (Phase 6)**: Depends on all three user stories.

### User Story Dependencies

- **User Story 1 (P1)**: Starts after Phase 2. Establishes active customers and products for ticket entry.
- **User Story 2 (P1)**: Starts after Phase 2; its end-to-end acceptance scenarios require the product/customer CRUD capability from US1.
- **User Story 3 (P1)**: Starts after Phase 2; its acceptance scenarios require tickets from US2. Its service calculations may be developed alongside US2 after the shared contracts are stable.
- Delivery order is **US1 → US2 → US3** to provide usable data prerequisites and complete the credit ledger incrementally.

### Within Each User Story

- Domain/service logic precedes screens that consume it.
- Reusable form/feedback components are completed before product/customer screens.
- Persistence operations write entity changes, audit events, and pending changes atomically.
- A story checkpoint must pass before downstream story integration depends on it.

### Parallel Opportunities

- Setup tasks T001, T002, and T003 touch separate dependency/configuration areas and can proceed in parallel.
- Foundational tasks T007, T009, and T011 can proceed in parallel after the foundational schema/DB work is defined.
- US1 product and customer services (T014, T015) can proceed in parallel; their independent forms (T017, T018) can also proceed after shared components T016.
- US2 ticket editor components T022 can proceed alongside repository/service work T020-T021 after model contracts are agreed.
- US3 balance calculations T028 can proceed alongside payment editor UI T029 once service interfaces are stable.
- Polish validation and reference-checked cleanup (T032, T033) are parallelizable; final lint/type-check follows edits.

---

## Parallel Example: User Story 1

```text
Task: "Implement product CRUD and active barcode duplicate resolution in productService.ts"
Task: "Implement customer CRUD and randomized internal-code generation in customerService.ts"

# Once reusable fields/feedback components are agreed:
Task: "Build product catalog and product form screens"
Task: "Build customer list and customer form screens"
```

## Parallel Example: User Story 2

```text
Task: "Implement ticket draft calculations and persistence behavior"
Task: "Build ticket line editor, product picker, scanner result, and live total components"
```

## Parallel Example: User Story 3

```text
Task: "Implement atomic payment CRUD and validation"
Task: "Implement balance aggregation, status calculation, and oldest-ticket candidate selection"
```

---

## Implementation Strategy

- **MVP first**: Complete US1 end-to-end so products and customers can be created, found, edited, and removed offline.
- **Incremental delivery**: Build ticket snapshots and correction/void behavior in US2, then payment CRUD and derived balances in US3.
- **Integrity first**: Treat SQLite as the persisted source of truth; commit mutations, audit entries, and pending changes atomically.
- **No feature drift**: Keep upload, backend sync, accounts, stock, purchase prices, reminders, and dashboards out of this work.
- **Clean up safely**: Preserve unrelated dirty work; only remove files proven unused after route, platform, import, script, and config checks.
- **Tests are not specified as standalone tasks**: Validate through quickstart scenarios, lint, strict TypeScript checks, and focused test coverage if requested during implementation.

---

## Summary

- Total task count: 35
- Task count per user story: US1 = 6, US2 = 6, US3 = 6
- Parallel opportunities identified: 3 setup tasks; 3 foundational work areas after schema; product/customer domains and screens; ticket domain and components; payment and balance domains; final quickstart/cleanup.
- Suggested MVP scope: User Story 1 (product and customer CRUD, offline persistence, soft removal).
- Format validation: All implementation tasks use the required checkbox, sequential task ID, optional `[P]`, required user-story label for story tasks, and explicit file paths.

## Phase 7: Convergence

- [X] T036 Resolve the feature's explicit no-backend, no-authentication, and no-synchronization scope against the mandatory backend, authentication, and reconciliation requirements in Constitution I, IV, and VI through an approved spec/constitution decision before considering the feature compliant (contradicts)
- [x] T037 Bring all ledger interactive controls into compliance with Constitution II's accessible large-touch-target requirements and verify screen-reader operation on Android and iOS (partial) — Closed 2026-10-31 by the owner before moving to the phase 1 backend.
- [X] T038 Apply language-driven RTL/LTR direction throughout the root and ledger layouts and verify language switching against Constitution III, FR-014, and SC-005 (partial)
- [X] T039 Replace raw service and SQLite exception text shown by ledger screens with localized user-facing messages in Arabic and French, preserving actionable failure feedback per Constitution III and FR-016 (contradicts)
- [X] T040 Format monetary values and date/time displays according to the selected Arabic or French locale, including ledger history timestamps, per the constitution's locale-formatting constraint (contradicts)
- [X] T041 Handle SQLite open and migration failures before ledger routes render; show a localized recovery state rather than allowing module-import failure to terminate the app per Constitution II (partial)
- [X] T042 Add focused automated tests for millime validation, ticket/payment calculations, atomic persistence and audit behavior, localization, and the core CRUD acceptance flows per the plan testing decision and Constitution V (missing)
- [X] T043 Make Expo web SQLite initialization and worker loading function in the supported web target, or obtain an approved platform-scope decision consistent with the plan target-platform decision (partial)
