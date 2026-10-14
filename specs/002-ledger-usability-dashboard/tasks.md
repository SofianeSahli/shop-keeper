---
description: "Task list for the ledger usability dashboard feature"
---

# Tasks: Ledger Usability Dashboard

**Input**: Design documents from `/specs/002-ledger-usability-dashboard/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Status**: Complete (2026-10-14). Items still open are listed under "Carried Forward" at the end.

**Organization**: Tasks are grouped by user story, using the story numbering of spec.md, to enable incremental implementation and validation.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prepare the existing ledger app for the navigation shell and dashboard work without changing the offline local-only scope.

- [x] T001 Audit existing ledger screens and route structure in `shop-keeper-mobile/src/app/` and `shop-keeper-mobile/src/features/ledger/` and classify each route as a main area, a non-form detail screen, or a form screen (including ticket detail), so the shell can match sections and hide the tab bar.
- [x] T002 Install `@expo/vector-icons` with `npx expo install` and confirm `react-native-safe-area-context` is available for the shell.
- [x] T003 [P] Add locale keys for Home, dashboard summary, quick actions, duplicate warnings, inline creation, and shell controls (back, menu, language, ledger) in `shop-keeper-mobile/src/i18n/translations/en.json`, `fr.json`, and `ar.json`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Build the app-wide navigation core, dashboard calculations, and dirty-form tracking that every story depends on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T004 Define the feature and tab item types, the feature registry, and the route-matching helpers (active feature, active item, form-route check) in `shop-keeper-mobile/src/navigation/features.ts`.
- [x] T005 [P] Define the ledger feature descriptor (five tab items with routes, label keys, icons, section path fragments, and form-route fragments) in `shop-keeper-mobile/src/features/ledger/navigation.ts` and register it in the registry.
- [x] T006 Implement `shop-keeper-mobile/src/navigation/useAppNavigation.ts` with tab switching via navigate, drill-down via push, and back with a Home fallback, all passing through a single dirty-form confirmation.
- [x] T007 [P] Add the dashboard summary service in `shop-keeper-mobile/src/features/ledger/services/dashboardService.ts`, deriving totals from current persisted records in integer millimes.
- [x] T008 Track dirty state in the product, customer, ticket, and payment forms so the flag is set only on real unsaved changes and cleared on save, cancel, or unmount.

**Checkpoint**: Navigation core, dashboard totals, and dirty-form tracking are ready for story-level work.

---

## Phase 3: User Story 1 - Navigate the App (Priority: P1)

**Goal**: Provide an app-wide shell with a bottom tab bar for the five main areas, a top bar with back, title, and language menu, and a tab bar that is hidden on form screens.

**Independent Test**: Open each main area from the tab bar, verify the active tab is highlighted, open a form from a list and verify the tab bar is hidden and back returns to the list, and switch language from the top bar.

### Implementation for User Story 1

- [x] T009 [P] [US1] Build `shop-keeper-mobile/src/navigation/BottomTabs.tsx`: icon and label per item, active highlight including child non-form screens, hidden on form routes, with accessible labels and selected state.
- [x] T010 [P] [US1] Build `shop-keeper-mobile/src/navigation/TopBar.tsx`: screen title, mirrored back button on form and detail screens only, language menu, and a feature switcher that appears only with two or more registered features.
- [x] T011 [P] [US1] Build `shop-keeper-mobile/src/navigation/LanguageMenu.tsx`, wired to the existing language setter and direction handling, preserving form values across a switch.
- [x] T012 [US1] Mount the top bar, stack, and tab bar in `shop-keeper-mobile/src/app/_layout.tsx` inside the provider and database gate.
- [x] T013 [US1] Open forms and details from list screens with push so back returns to the list, and confirm leaving a form with unsaved changes through the back button.

**Checkpoint**: Users can move between main areas, drill into forms and back, and change language from any screen.

---

## Phase 4: User Story 2 - Review the Daily Dashboard (Priority: P1)

**Goal**: Show today's sales and the total outstanding balance, correct for empty, paid, partial, unpaid, corrected, and voided data.

**Independent Test**: Compare the dashboard against dated ticket lines and payments, then make a sale, edit or void a ticket, and change a payment, verifying both totals each time.

### Implementation for User Story 2

- [x] T014 [P] [US2] Complete the summary calculations in `shop-keeper-mobile/src/features/ledger/services/dashboardService.ts` and validate them against tickets and payments.
- [x] T015 [US2] Render the Home dashboard with sales and outstanding-balance cards in `shop-keeper-mobile/src/app/ledger/index.tsx`, refreshing after ledger mutations and when the screen regains focus.

**Checkpoint**: The dashboard shows correct totals without an app restart.

---

## Phase 5: User Story 3 - Complete Common Actions from Home (Priority: P1)

**Goal**: Start adding a product, creating a ticket, or recording a payment from the dashboard and return to Home after a successful save.

**Independent Test**: Start each action from Home, save valid data, and confirm the app returns to the dashboard with refreshed totals.

### Implementation for User Story 3

- [x] T016 [US3] Add Add Product, New Ticket, and Record Payment quick actions to `shop-keeper-mobile/src/app/ledger/index.tsx`, opened with push.
- [x] T017 [US3] Return to Home after a successful save from a dashboard-started action, and give clear feedback on cancel or failure without presenting an unsaved change as completed.

**Checkpoint**: The three common actions are one tap from Home and return to updated totals.

---

## Phase 6: User Story 4 - Add a Product by Scanning or Typing (Priority: P2)

**Goal**: Allow product-code scanning with duplicate warning and a manual fallback when the camera cannot read a code.

**Independent Test**: Scan a valid code, deny camera access, and type a duplicate or unique code; confirm the outcome is a warning or a creation-ready product with no duplicate active products.

### Implementation for User Story 4

- [x] T018 [P] [US4] Add duplicate-code lookup and the open-existing-product flow in `shop-keeper-mobile/src/features/ledger/screens/ProductFormScreen.tsx`.
- [x] T019 [US4] Reuse the camera scanner and keep manual entry enabled when permission is denied or scanning fails, in `shop-keeper-mobile/src/features/ledger/components/ticket/BarcodeScanner.tsx` and `ProductFormScreen.tsx`.
- [x] T020 [US4] Add validation and feedback for duplicate and manual-entry outcomes in `shop-keeper-mobile/src/i18n/translations/*.json` and the product form view.

**Checkpoint**: Product creation supports scan and manual entry without silent duplicates.

---

## Phase 7: User Story 5 - Add Customers and Products Within a Ticket (Priority: P2)

**Goal**: Create missing customer and product records while editing a ticket without losing the current draft.

**Independent Test**: Start a ticket, add lines, create a customer inline and verify it is selected, create a product inline and verify the line is added without losing prior values.

### Implementation for User Story 5

- [x] T021 [P] [US5] Add inline customer creation to `shop-keeper-mobile/src/features/ledger/screens/TicketFormScreen.tsx`, selecting the new customer and keeping current lines intact.
- [x] T022 [US5] Add inline product creation to `TicketFormScreen.tsx`, appending a one-quantity draft line and preserving all existing items.
- [x] T023 [US5] Confirm that cancel, validation failure, and persistence failure leave the ticket state and draft totals unchanged.

**Checkpoint**: Ticket editing recovers from missing customer or product data without abandoning the draft.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Final validation, cleanup, and confidence checks across the new UI behavior.

- [x] T024 [P] Run the focused validation workflow in `shop-keeper-mobile`: TypeScript, lint, and the existing ledger test suite.
- [x] T025 [P] Review the shell, Home, duplicate-code, and inline-creation flows for Arabic RTL and French LTR behavior, including the mirrored back button, and accessible touch targets.
- [x] T026 Run the quickstart validation scenarios from `specs/002-ledger-usability-dashboard/quickstart.md` and confirm the requirement set is satisfied.

---

## Phase 9: Convergence

- [x] T027 CRITICAL: Enforce actual dirty-form detection before back and any other in-app navigation so product, customer, and ticket forms prompt only on real unsaved changes and discard only when the owner confirms, per Clarification / US1/AC4 / FR-018 / US5/AC1-3
- [x] T028 HIGH: Validate the dashboard totals against no-data, paid, partially paid, unpaid, and voided cases to confirm the saved ledger state is correct after ticket and payment changes, per US2/AC1-4
- [x] T029 HIGH: Re-check the barcode, duplicate-product, manual fallback, and inline creation flows for save and cancel edge cases so camera denial, unreadable scans, existing-product warnings, and draft preservation match the Quickstart and acceptance scenarios, per US4/AC1-4 / US5/AC1-3

---

## Dependencies & Execution Order

- Setup (Phase 1) precedes all feature work.
- Foundational tasks (Phase 2) block user-story implementation.
- User Story 1 (the shell) comes first because every other story renders inside it. User Stories 2 and 3 follow, then 4 and 5, which are independent of each other.
- Final validation and convergence depend on all user stories being complete.

### Parallel Opportunities

- All tasks marked [P] can be executed in parallel when multiple files are being edited.
- `BottomTabs`, `TopBar`, and `LanguageMenu` are independent once the registry and `useAppNavigation` exist.
- Dashboard calculations, translation keys, and the ledger descriptor are independent once the shell types are in place.
- Inline customer creation and scanning work can proceed together once the ticket draft state is stable.

---

## Carried Forward

Not part of the completion of 002. Confirm each is done, or move it to the next spec.

- [ ] Guard the Android system back action on forms with unsaved changes so it asks for the same confirmation as the on-screen back button (spec assumption).
- [ ] Remove inline style objects from the shell's safe-area handling and use `SafeAreaView` with `edges` and NativeWind classes instead (constitution: no inline style objects).
- [ ] Hide the shell on the language-selection entry route by returning no active feature for routes outside a registered feature.
- [ ] Confirm the registry routes match the generated typed routes under `/ledger/...`.