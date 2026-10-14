---
description: "Task list for the ledger usability dashboard feature"
---

# Tasks: Ledger Usability Dashboard

**Input**: Design documents from `/specs/002-ledger-usability-dashboard/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Organization**: Tasks are grouped by user story to enable incremental implementation and validation.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prepare the existing ledger app for the dashboard and toolbar work without changing the offline local-only scope.

- [ ] T001 Audit existing ledger screens and route structure in `shop-keeper-mobile/src/app/` and `shop-keeper-mobile/src/features/ledger/` to align the shared toolbar and dashboard with the current app shell.
- [ ] T002 Add the missing ledger dashboard/summary service in `shop-keeper-mobile/src/features/ledger/services/dashboardService.ts` and keep totals derived from current persisted records and integer millimes.
- [ ] T003 [P] Extend the shared ledger UI shell in `shop-keeper-mobile/src/features/ledger/components/LedgerPage.tsx` and create a reusable `shop-keeper-mobile/src/features/ledger/components/LedgerToolbar.tsx` for the five-section top navigation.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Make the dashboard, global navigation, and product-scanning flows reliable before implementing ticket and form enhancements.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T004 Implement the Home dashboard layout in `shop-keeper-mobile/src/app/ledger/index.tsx` with sales-today and outstanding-balance cards plus quick actions.
- [ ] T005 [P] Add locale/translation keys for Home, Dashboard summary, quick actions, duplicate warnings, and inline creation labels in `shop-keeper-mobile/src/i18n/translations/en.json`, `fr.json`, and `ar.json`.
- [ ] T006 Add product duplicate and open-existing-product handling in `shop-keeper-mobile/src/features/ledger/screens/ProductFormScreen.tsx` and reuse the existing barcode scanner for manual and camera entry fallback.
- [ ] T007 Add the in-place customer/product creation flow in `shop-keeper-mobile/src/features/ledger/screens/TicketFormScreen.tsx` without dropping existing ticket draft data.
- [ ] T008 Add the unsaved-change confirmation behavior when leaving a form via the toolbar in `shop-keeper-mobile/src/features/ledger/components/LedgerPage.tsx` and the affected form screens.

**Checkpoint**: Navigation, dashboard totals, and inline creation are ready for story-level validation.

---

## Phase 3: User Story 1 - Navigate the Ledger and Review the Daily Dashboard (Priority: P1)

**Goal**: Provide a persistent ledger toolbar and a live dashboard of today's sales and unpaid balance.

**Independent Test**: Open the app to Home, verify the dashboard totals are zero or current values, switch between the five toolbar sections, and confirm the selected section is highlighted.

### Implementation for User Story 1

- [ ] T009 [P] [US1] Build the dashboard summary and calculation functions in `shop-keeper-mobile/src/features/ledger/services/dashboardService.ts` and validate totals against tickets and payments.
- [ ] T010 [US1] Update the ledger root screen to render the home dashboard and refresh totals after ledger mutations in `shop-keeper-mobile/src/app/ledger/index.tsx`.
- [ ] T011 [US1] Render the persistent five-item toolbar and highlight the active section in `shop-keeper-mobile/src/features/ledger/components/LedgerToolbar.tsx` and `shop-keeper-mobile/src/features/ledger/components/LedgerPage.tsx`.
- [ ] T012 [US1] Add quick actions for Add Product, New Ticket, and Record Payment from the dashboard and ensure they return to Home after save.

**Checkpoint**: Users can navigate the app and see correct daily totals from the dashboard.

---

## Phase 4: User Story 2 - Add a Product by Scanning or Typing (Priority: P2)

**Goal**: Allow product-code scanning with duplicate warning and manual fallback when the camera cannot read a code.

**Independent Test**: Scan a valid code, deny camera access, and type a duplicate or unique code; ensure the result is warning-only or creation-ready without creating duplicate active products.

### Implementation for User Story 2

- [ ] T013 [P] [US2] Add duplicate-code lookup and open-existing-product flow in `shop-keeper-mobile/src/features/ledger/screens/ProductFormScreen.tsx`.
- [ ] T014 [US2] Reuse the camera scanner when available and keep manual entry enabled when camera permission is denied or scanning fails in `shop-keeper-mobile/src/features/ledger/components/ticket/BarcodeScanner.tsx` and `shop-keeper-mobile/src/features/ledger/screens/ProductFormScreen.tsx`.
- [ ] T015 [US2] Add validation and feedback for duplicate and manual scan outcomes in `shop-keeper-mobile/src/i18n/translations/*.json` and the product form view.

**Checkpoint**: Product creation supports both scan and manual entry without silent duplicates.

---

## Phase 5: User Story 3 - Add Customers and Products Within a Ticket (Priority: P2)

**Goal**: Let the owner create missing customer and product records while editing a ticket without losing current draft data.

**Independent Test**: Start a ticket, add lines, create a customer inline and verify selection, create a product inline and verify the line is added without losing prior values.

### Implementation for User Story 3

- [ ] T016 [P] [US3] Add inline customer creation controls to `shop-keeper-mobile/src/features/ledger/screens/TicketFormScreen.tsx`, assign the new customer to the ticket draft, and keep current lines intact.
- [ ] T017 [US3] Add inline product creation controls to `shop-keeper-mobile/src/features/ledger/screens/TicketFormScreen.tsx`, append a one-quantity draft line, and preserve all existing items.
- [ ] T018 [US3] Validate that cancel, validation failure, and persistence failure do not lose the existing ticket state or draft totals.

**Checkpoint**: Ticket editing can recover from missing customer or product data without abandoning the current draft.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final validation, cleanup, and confidence checks across the new UI behavior.

- [ ] T019 [P] Run the focused validation workflow in `shop-keeper-mobile`: TypeScript, lint, and the existing ledger test suite.
- [ ] T020 [P] Review the Home, toolbar, duplicate-code, and inline-creation flows for Arabic RTL/French LTR behavior and accessible touch targets.
- [ ] T021 Run the quickstart validation scenarios from `specs/002-ledger-usability-dashboard/quickstart.md` and confirm the requirement set is satisfied.

---

## Dependencies & Execution Order

- Setup (Phase 1) precedes all feature work.
- Foundational tasks (Phase 2) block user-story implementation.
- User Story 1 is the MVP path; User Stories 2 and 3 can be delivered after the dashboard and navigation shell are stable.
- Final validation depends on all required user stories being complete.

### Parallel Opportunities

- All tasks marked [P] can be executed in parallel when multiple files are being edited.
- Dashboard calculations, translation keys, and route changes are independent once the shared shell is in place.
- Inline customer and product creation work can be implemented together once the ticket draft state is stable.

## Phase 7: Convergence

- [ ] T022 CRITICAL: Enforce actual dirty-form detection before toolbar navigation so product, customer, and ticket forms only prompt on real unsaved changes and only discard when the owner confirms per Clarification / US1/AC1 / US5/AC1-3 (partial)
- [ ] T023 HIGH: Validate the dashboard totals against no-data, paid, partially paid, unpaid, and voided cases to confirm the saved ledger state is correct after ticket and payment changes per US2/AC1-4 (partial)
- [ ] T024 HIGH: Re-check the barcode, duplicate-product, manual fallback, and inline creation flows for save/cancel edge cases so camera denial, unreadable scans, existing-product warnings, and draft preservation all match the Quickstart and acceptance scenarios per US4/AC1-4 / US5/AC1-3 (partial)
