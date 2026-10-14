# Quickstart: Ledger Usability Dashboard Validation

## Prerequisites

- Work from the `shop-keeper-mobile` directory with dependencies installed.
- Use a supported Android or iOS device/simulator for camera and permission checks.
- The web runtime requires a host that serves WebAssembly assets and the `Cross-Origin-Embedder-Policy: credentialless` and `Cross-Origin-Opener-Policy: same-origin` headers. Expo SQLite web support is experimental.
- No network connection is required for ledger workflows.

## Start and automated validation

From the repository root:

```sh
cd shop-keeper-mobile
npm install
npm run validate
npx expo start
```

Expected outcome:

- Lint, strict TypeScript checks, and focused Vitest suites pass.
- The existing root language-selection/entry screen remains available; continue to `/ledger` to open the Home dashboard.
- A web export can be produced with `npx expo export --platform web`; browser runtime validation is performed on a host with the required SQLite isolation headers.

## Validate persistent toolbar and unsaved-change guard

1. Continue from the root entry screen to `/ledger`, then open Products, Tickets, Customers, and Payments from the top toolbar.
2. Open a form under each relevant section and verify the toolbar remains visible and highlights the parent section.
3. Enter values in a form without saving and select another toolbar area.
4. Cancel the discard prompt and verify the owner remains in the form with values intact.
5. Repeat and confirm leaving; verify navigation proceeds and unsaved values are discarded.
6. Repeat the navigation and direction checks in Arabic and French.

Expected outcome:

- Every main section is reachable in one toolbar selection.
- Exactly the correct parent section is highlighted on list, detail, and form routes.
- Cancel preserves the active form; confirming discard performs the requested navigation.
- Arabic layout is RTL and French layout is LTR.

## Validate dashboard totals and refresh

1. With no tickets, verify sales today and outstanding balance both show zero.
2. Create today's unpaid ticket with two lines; verify sales equals the full line total and outstanding equals the same amount.
3. Add a partial payment; verify sales stays the same and outstanding decreases by exactly the payment.
4. Settle the ticket; verify outstanding becomes zero.
5. Create another ticket, then edit it and void it; verify sales reflects the latest saved active ticket total and no longer includes a voided ticket.
6. Create a ticket dated before today and verify it contributes to outstanding balance if unpaid but not to today's sales.
7. Return to Home after each successful write and verify totals refresh without restarting.

Expected outcome:

- Totals match the [data model](./data-model.md) exactly in integer millimes.
- Paid/voided tickets do not inflate outstanding debt; payments do not reduce sales.
- Empty result sets display zero, and a ticket created before today is excluded from today's sales.

## Validate dashboard quick actions

1. Start Add Product, New Ticket, and Record Payment separately from Home.
2. Complete each flow with valid data.
3. Verify each successful save returns to Home and refreshes the dashboard.
4. Cancel a flow and cause one validation failure; verify neither is presented as a successful save.

## Validate product scanning, duplicates, and manual fallback

1. Open the new-product flow on a device with camera access and scan a supported barcode.
2. Verify the scanned value appears in the editable product code field.
3. Enter an existing active product's code manually; verify a warning and an action to open the existing product.
4. Repeat with a scanned duplicate.
5. Deny camera permission or test with no camera/readable code, then enter a unique code manually and save.
6. Save a product without a code.

Expected outcome:

- Scanning and manual entry use the same duplicate check.
- No duplicate active product is created.
- Camera denial or unreadable scans do not block manual entry or a valid product without a code.

## Validate inline customer and product creation

1. Start a ticket and select a customer.
2. Add two product lines and change one quantity.
3. Open inline customer creation, save a customer, and verify it is selected; confirm existing lines and quantity remain unchanged.
4. Open inline product creation, save a product, and verify it appears as one new line with quantity one.
5. Cancel another inline creation and trigger a validation/persistence failure; verify existing customer, line order, values, quantities, and total remain intact.
6. Save the ticket and verify saved snapshots and total match the preserved draft.

## References

- Interaction rules: [UI navigation and entry contract](./contracts/ui-navigation-and-entry-contract.md)
- Derived values and draft data: [data model](./data-model.md)
- Existing offline CRUD/history scenarios: [feature 001 quickstart](../001-kridi-credit-ledger/quickstart.md)
