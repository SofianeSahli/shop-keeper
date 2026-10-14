# Feature Specification: Ledger Usability Dashboard

**Feature Branch**: `002-ledger-usability-dashboard`

**Created**: 2026-10-11

**Updated**: 2026-10-14

**Status**: Draft

**Input**: User description: "Building on feature 001 (ledger: customers, products, tickets, payments), reorganize the app into a more usable experience:

1. A persistent navigation shell shared by the whole app: a slim top bar (back button on form and detail screens, current screen title, language menu, and a feature switcher that appears only once the app has more than one feature) and a bottom tab bar for the main areas (Home, Products, Tickets, Customers, Payments) with the current section highlighted. The tab bar is hidden on form screens.
2. The new-product form lets the user scan a barcode/QR code with the device camera to fill in the product code. If the code already exists, warn the user and offer to open the existing product. If camera permission is denied or scanning fails, the user can still type the code manually.
3. The home screen opens on a dashboard showing today's total sales (sum of products sold today) and the total of unpaid tickets (remaining balance only, counting partially paid tickets). Both show zero when there is no data. Totals update after any sale, ticket, or payment change.
4. The dashboard provides one-tap quick actions to add a product, create a ticket, or record a payment. After finishing one, the user returns to the dashboard with updated totals.
5. The ticket form lets the user create a new customer and add a new product inline, without leaving the ticket being edited. The new customer or product is automatically selected on the ticket, and no ticket data already entered is lost."

## Clarifications

### Session 2026-10-11

- Q: If the owner uses the persistent toolbar to leave a form with unsaved changes, what should the application do? → A: Ask for confirmation and discard the changes only if the owner confirms leaving.

### Session 2026-10-14

- Q: Should the main areas live in a top toolbar or a bottom navigation bar? → A: A bottom tab bar holds the five main areas (reachable with one thumb, active area always visible). A slim top bar holds back, screen title, language, and the future feature switcher.
- Q: Should the tab bar stay visible on form screens? → A: No. It is hidden on form screens (product, ticket, customer, payment) and on ticket detail, which frees space for the keyboard and prevents accidental navigation away from unsaved data. The back button is the way out.
- Q: Should switching between main areas build up a back stack? → A: No. Main areas are peers, so selecting a tab does not stack screens. Opening a form or detail from a list does keep the stack, so back returns to where the owner came from.
- Q: Where does language selection live? → A: In the top bar, as a compact menu showing the current language. It is app-wide, not part of the ledger tabs.
- Q: How does the shell handle future features? → A: Features are registered in one place; the feature switcher in the top bar appears only when two or more features exist. Adding a feature does not change the top bar, tab bar, or back behavior.
- Amendment to the 2026-10-11 answer: because the tab bar is hidden on forms, the confirmation applies to the back button and to any other in-app control that leaves a form with unsaved changes (including the feature switcher once present).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Navigate the App (Priority: P1)

A shop owner can move among Home, Products, Tickets, Customers, and Payments from a bottom tab bar, while a slim top bar shows the current screen, a back button when applicable, and a language menu. The active area is visibly distinguished, and Home opens to the dashboard. On form screens the tab bar is hidden and the back button is the way out.

**Why this priority**: Predictable navigation makes all existing ledger workflows easier to find and return to.

**Independent Test**: Open each main area using the tab bar, verify the selected tab is highlighted, open a form from a list and verify the tab bar is hidden and back returns to the list, and change the language from the top bar.

**Acceptance Scenarios**:

1. **Given** the owner is on the dashboard, a list screen, or a non-form detail screen, **When** they select a main area in the bottom tab bar, **Then** that area opens and its tab is highlighted.
2. **Given** the owner launches or selects Home, **When** the Home area opens, **Then** the dashboard is displayed.
3. **Given** the owner is viewing a non-form detail screen within a main area (such as a customer ledger, balances, or history), **When** the screen is displayed, **Then** the tab bar remains visible and highlights that screen's main area.
4. **Given** the owner opens a form screen or ticket detail, **When** the screen is displayed, **Then** the tab bar is hidden, the top bar shows a back button and the screen title, and selecting back returns to the previous screen.
5. **Given** the owner is on a main area's root screen, **When** the screen is displayed, **Then** no back button is shown.
6. **Given** the owner has visited several main areas, **When** they select back from a screen opened from a list, **Then** they return to that list, not to a previously visited tab.
7. **Given** the owner opens the language menu in the top bar, **When** they select Arabic or French, **Then** the app's text and layout direction update accordingly and the current language is shown in the top bar.
8. **Given** the app has only one feature, **When** the top bar is displayed, **Then** no feature switcher is shown.

### User Story 2 - Review the Daily Dashboard (Priority: P1)

A shop owner can see today's sales and the total amount still owed across unpaid and partially paid tickets at a glance. Both totals are zero when no qualifying records exist and refresh after relevant ledger changes.

**Why this priority**: A reliable daily overview helps the owner understand sales activity and outstanding credit without manually totaling tickets.

**Independent Test**: Compare the dashboard against a set of dated ticket lines and payments containing paid, partially paid, unpaid, voided, and no-data cases; make a sale, edit or void a ticket, and change a payment, verifying the totals each time.

**Acceptance Scenarios**:

1. **Given** active tickets were created today, **When** the owner opens the dashboard, **Then** today's sales equals the sum of their ticket line totals, regardless of how much has been paid.
2. **Given** active unpaid or partially paid tickets exist, **When** the owner views the dashboard, **Then** unpaid tickets total equals the sum of each ticket's remaining balance, including partially paid tickets and excluding fully paid or voided tickets.
3. **Given** there are no qualifying sales or outstanding balances, **When** the dashboard is shown, **Then** each corresponding total displays zero.
4. **Given** a sale, ticket, or payment is created, corrected, or voided, **When** the owner returns to the dashboard, **Then** both totals reflect the saved ledger state without requiring an app restart.

### User Story 3 - Complete Common Actions from Home (Priority: P1)

A shop owner can begin adding a product, creating a ticket, or recording a payment directly from the dashboard. After a successful save, the app returns to Home and shows refreshed totals.

**Why this priority**: Direct actions reduce navigation and support the most frequent shop tasks.

**Independent Test**: Start each of the three actions from Home, save valid data, and confirm the app returns to the dashboard with the resulting totals.

**Acceptance Scenarios**:

1. **Given** the dashboard is open, **When** the owner selects Add Product, New Ticket, or Record Payment, **Then** the corresponding entry flow opens directly.
2. **Given** the owner completes and saves one of these actions, **When** saving succeeds, **Then** the app returns to the dashboard and refreshes the totals.
3. **Given** the owner cancels an action or saving fails, **When** the entry flow ends, **Then** no unsaved ledger change is presented as completed and the owner receives clear feedback.

### User Story 4 - Add a Product by Scanning or Typing (Priority: P2)

A shop owner can fill a new product's code by scanning a barcode or QR code with the device camera, or by entering the code manually. Duplicate codes are clearly identified and lead to the existing product instead of creating a duplicate.

**Why this priority**: Scanning speeds up catalog entry, while manual entry keeps the workflow usable when camera access or scanning is unavailable.

**Independent Test**: Scan a supported code, deny camera access, simulate an unreadable scan, and manually enter both unique and existing codes; verify the correct product outcome in each case.

**Acceptance Scenarios**:

1. **Given** the new-product form is open and camera access is available, **When** the owner scans a barcode or QR code, **Then** its value is placed in the product code field.
2. **Given** camera permission is denied, scanning is cancelled, or no readable code is detected, **When** scanning cannot provide a code, **Then** the owner can continue by typing a code or saving without one.
3. **Given** a scanned or manually entered code already belongs to an active product, **When** the code is checked, **Then** the owner is warned, can open that existing product, and cannot silently create a duplicate.
4. **Given** a unique code is scanned or entered, **When** the owner saves a valid product, **Then** the product is created with that code.

### User Story 5 - Add Customers and Products Within a Ticket (Priority: P2)

A shop owner can create a customer or product from within an in-progress ticket. The new customer becomes the ticket's selected customer; the new product is added to the ticket. Existing ticket lines, quantities, and other entered values are preserved.

**Why this priority**: Owners can continue recording a sale without abandoning or rebuilding ticket details when a customer or product is missing.

**Independent Test**: Enter a customer and multiple ticket lines, add a customer inline and verify it is selected, then add a product inline and verify it is added while all prior lines and quantities remain unchanged.

**Acceptance Scenarios**:

1. **Given** a ticket has unsaved customer selection or product lines, **When** the owner creates a customer inline, **Then** the ticket remains open, all entered lines and quantities remain unchanged, and the new customer is selected.
2. **Given** a ticket has unsaved customer selection or product lines, **When** the owner creates a product inline, **Then** the ticket remains open, all prior ticket data remains unchanged, and the new product is added as a ticket line.
3. **Given** inline creation is cancelled or validation fails, **When** the owner returns to the ticket, **Then** the draft is preserved and the new record is not treated as created.

### Edge Cases

- The device has no camera, camera permission is denied, or the camera cannot read the displayed code; manual code entry remains available.
- A scanned or typed code belongs to an inactive product or is already present on an active product; active duplicates are not created, while inactive records do not block a new active catalog item unless the existing product is reactivated.
- A code is scanned repeatedly before the owner responds to the duplicate warning; only one duplicate prompt is shown at a time.
- There are no sales today, all tickets are paid, all tickets are voided, or no ledger records exist; the applicable dashboard totals remain zero.
- A ticket is created near local midnight or the device's local date changes; a ticket counts toward the day indicated by its recorded creation date in the device's local calendar.
- A ticket created today is later corrected or voided; its latest saved total contributes to today's sales while active, and a voided ticket contributes nothing.
- A payment correction, payment void, ticket correction, or ticket void changes an outstanding balance; the dashboard reflects the recalculated remaining amounts.
- Inline customer or product creation fails, is cancelled, or returns after validation feedback; the unsaved ticket draft remains intact.
- A user opens an edit or detail screen from a main area; the top bar shows that screen's title, and when the tab bar is visible it highlights the parent area rather than implying a separate main section.
- The owner leaves a form with unsaved changes using the back button; the application asks for confirmation and keeps the owner on the form unless leaving is confirmed.
- The owner selects back on a screen with no previous screen in the navigation history (for example after a direct link or restart); the app goes to Home instead of doing nothing.
- The owner selects the tab of the area they are already in; nothing changes and no duplicate screen is added to the history.
- The owner switches the language while a form has unsaved changes; the form values are preserved and only text and layout direction change.
- The keyboard is open on a form; the tab bar is not shown above it, because tab bars are hidden on form screens.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The application MUST provide a bottom tab bar for the main areas Home, Products, Tickets, Customers, and Payments, each with an icon and a text label, and a slim top bar shared across all screens.
- **FR-002**: The tab bar MUST visually distinguish the currently active main area, including when the owner is on a child detail screen of that area where the tab bar remains visible.
- **FR-003**: The Home area MUST open on a dashboard that displays today's sales total and total outstanding balance on unpaid tickets.
- **FR-004**: Today's sales MUST equal the sum of line totals for non-voided tickets whose recorded creation date falls on the device's current local calendar date; payments MUST NOT reduce this sales total.
- **FR-005**: Total outstanding balance MUST equal the sum of remaining balances for non-voided tickets that have a positive remaining balance, including partially paid tickets and excluding fully paid tickets.
- **FR-006**: The dashboard MUST display zero for either total when no qualifying records exist and MUST recalculate both totals after a sale or a ticket or payment creation, correction, or removal is saved.
- **FR-007**: The dashboard MUST provide direct actions to add a product, create a ticket, and record a payment.
- **FR-008**: After a dashboard-started action is successfully saved, the application MUST return the owner to the dashboard and display refreshed totals. Cancelling or failing an action MUST NOT be presented as a successful save.
- **FR-009**: The new-product flow MUST allow the owner to request a camera scan and MUST place a successfully read barcode or QR code value into the product code field.
- **FR-010**: Camera permission denial, unavailable camera hardware, cancellation, or an unreadable scan MUST NOT prevent manual product-code entry or prevent saving a valid product without a code.
- **FR-011**: The application MUST check scanned and manually entered codes against active products. If a match exists, it MUST warn the owner, offer to open the existing product, and prevent creation of a duplicate active product.
- **FR-012**: The ticket flow MUST allow creation of a customer without navigating away from the current unsaved ticket and MUST select that customer for the ticket after successful creation.
- **FR-013**: The ticket flow MUST allow creation of a product without navigating away from the current unsaved ticket and MUST add that product as a ticket line after successful creation.
- **FR-014**: Inline customer or product creation MUST preserve the rest of the in-progress ticket, including all existing line items, quantities, and current selections.
- **FR-015**: Cancelling or failing inline creation MUST leave the in-progress ticket intact and MUST NOT select or add a record that was not successfully created.
- **FR-016**: The new navigation, dashboard, barcode, and inline-entry flows MUST support the application's existing Arabic and French languages and their corresponding right-to-left and left-to-right layouts, including mirrored placement and direction of the back button and tab order.
- **FR-017**: All dashboard sales and balance values MUST use the same currency and amount precision as the existing ledger.
- **FR-018**: If the owner uses the back button, or any other in-app navigation control, to leave a form with unsaved changes, the application MUST ask for confirmation, retain the form and its values if leaving is cancelled, and discard the unsaved changes only if leaving is confirmed.
- **FR-019**: The tab bar MUST be hidden on form screens (product, ticket, customer, and payment forms) and on ticket detail, and MUST be visible on the dashboard, list screens, and non-form detail screens.
- **FR-020**: The top bar MUST show the current screen's title on every screen, and MUST show a back button on form screens and detail screens but not on a main area's root screen.
- **FR-021**: The back button MUST return to the previous screen. If there is no previous screen, it MUST go to Home.
- **FR-022**: Selecting a main area in the tab bar MUST NOT add to the back history in a way that makes back step through previously visited tabs; selecting the already-active area MUST do nothing. Opening a form or detail from a list MUST keep the list in the history so back returns to it.
- **FR-023**: The top bar MUST provide a language menu that shows the current language and lets the owner switch between the supported languages (Arabic and French) in one selection from any screen.
- **FR-024**: The navigation shell MUST be app-wide and independent of any single feature. Features MUST be registered in one place with their main areas, destinations, and form screens, and the top bar's feature switcher MUST appear only when two or more features are registered.
- **FR-025**: All navigation controls MUST have accessible labels, report their selected state, and meet the minimum touch target size used elsewhere in the app.

### Key Entities

- **Dashboard Summary**: Today's active-ticket sales total and the sum of remaining balances on active, non-voided tickets.
- **Feature**: A registered area of the app (currently only the ledger) with its own main areas, destinations, and form screens. The shell reads from the registered features to build the tab bar and feature switcher.
- **Navigation Section**: One of Home, Products, Tickets, Customers, or Payments, with an icon, a label, and the screens that belong to it. The active section is reflected in the tab bar.
- **Form Screen**: A create or edit screen (and ticket detail) on which the tab bar is hidden and the back button is the exit.
- **Product Code Match**: The relationship between a scanned or manually entered code and an existing active catalog product.
- **Ticket Draft**: The unsaved customer selection and product lines, quantities, and amounts that must survive inline customer or product creation.
- **Inline Customer or Product**: A newly created ledger record initiated from an in-progress ticket and immediately associated with that ticket draft.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In usability checks, owners can reach each of the five main areas from the dashboard, any list screen, or any non-form detail screen in one tab selection, and the active area is correctly indicated in every tested route.
- **SC-002**: Dashboard sales and outstanding-balance totals match independently calculated expected values for 100% of test cases covering empty data, unpaid, partial, paid, corrected, and voided tickets.
- **SC-003**: After saving any sale, ticket, or payment change, dashboard totals show the corresponding saved state without an application restart.
- **SC-004**: Owners can begin each dashboard quick action with one selection and return to the dashboard immediately after a successful save.
- **SC-005**: In usability checks, owners can add a product code using a readable scan or manual entry, and can complete product entry manually when scanning is unavailable.
- **SC-006**: In 100% of inline-entry tests, successfully added customers are selected, successfully added products become ticket lines, and ticket data entered before the inline action remains unchanged.
- **SC-007**: Owners can complete navigation, dashboard, product-code, and inline-entry flows in both Arabic and French, with the correct text direction.
- **SC-008**: In 100% of tested form screens, the tab bar is hidden, the back button is shown, and leaving with unsaved changes asks for confirmation; on 100% of tested main area root screens, no back button is shown.
- **SC-009**: Owners can switch language from any screen in one menu selection, and form values entered before the switch remain unchanged.
- **SC-010**: Adding a second feature to the registry makes the feature switcher appear and requires no changes to the top bar, tab bar, or back behavior.

## Assumptions

- This feature builds on and reorganizes the existing feature 001 ledger; it does not replace its product, customer, ticket, or payment records or alter their offline and financial-history rules.
- Today's sales means the sum of ticket line totals on non-voided tickets created on the device's current local calendar date; it is not a cash-collection total and is unaffected by payment timing.
- The unpaid-ticket total means the sum of each non-voided ticket's remaining balance after active payments, so partially paid tickets contribute only what remains.
- A saved ticket's creation date determines the sales day; a later edit changes its current sales amount for that day, and voiding it removes it from that day's sales.
- The device's local calendar and timezone determine which tickets were created today.
- A manually entered product code is subject to the same active-duplicate check as a scanned code. Inactive catalog entries are not treated as active duplicates.
- The inline add-product flow creates a catalog product and adds one line with quantity one to the draft; the owner may change the quantity before saving the ticket.
- A dashboard-started action returns Home only after a successful save; cancellation remains in the originating flow or returns according to the existing cancellation behavior.
- The tab bar targets phone-sized screens. Tablet or web layouts (for example a side or top navigation) are out of scope for this feature.
- Five main areas is the maximum for the tab bar. If more areas are needed later, one moves into an overflow tab rather than adding a sixth tab.
- Switching between right-to-left and left-to-right languages may require the app to refresh its layout; form values are preserved across the switch.
- The system back action on Android is expected to follow the same unsaved-changes confirmation as the on-screen back button; this is tracked separately from the navigation shell's initial delivery.
- Existing access, privacy, localization, currency, offline-storage, and audit behavior from feature 001 remains in force.
- This approved feature is a local-only usability extension to `001-kridi-credit-ledger`; it inherits feature 001's explicit single-shop, single-device exception to backend, authentication, and synchronization requirements.