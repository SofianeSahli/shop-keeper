# Quickstart: Kridi Credit Ledger Validation

## Prerequisites

- `shop-keeper-mobile` is the active feature work area.
- Expo dependencies are installed (`npm install` inside `shop-keeper-mobile`).
- A simulator or device is available for Android or iOS.
- No internet connection is required for core validation scenarios.

## Start the Expo frontend

From the repository root:

```sh
cd shop-keeper-mobile
npm install
npx expo start
```

Open the QR code with Expo Go, or press `a` in the Expo terminal to launch an Android emulator. For a connected iOS simulator on macOS, press `i`.

> Expo SQLite's web support is experimental. The app configures the required cross-origin isolation headers for Expo Router hosting. Any other static web host must also return `Cross-Origin-Embedder-Policy: credentialless` and `Cross-Origin-Opener-Policy: same-origin`; browser persistence should be tested on the actual deployment host.

## Validate the offline creation flow

1. Launch the app in the simulator or Expo Go.
2. Open the Kridi ledger screen.
3. Add a product with a valid barcode and selling price.
4. Add a customer with a display label only.
5. Create a ticket by selecting the customer and scanning or manually adding two or more products.
6. Adjust quantities and confirm the total updates immediately.
7. Save the ticket and close/reopen the app.

Expected outcome:
- The customer, product, and ticket remain available after restart.
- The saved total matches the sum of line totals in integer millimes.
- No upload attempt is triggered while offline.

## Validate barcode fallback and duplicate handling

1. Start a new ticket.
2. Scan a barcode that is not in the catalog.
3. Choose the add-product path.
4. Add the missing product name and selling price.
5. Try adding a second product with the same barcode.

Expected outcome:
- The app offers a clear resolution path for an unknown barcode.
- Duplicate barcodes are not silently created.
- The ticket can continue with the newly added item.

## Validate payment logic and ticket status

1. Create a customer with an unpaid ticket.
2. Record a partial payment against the ticket.
3. Record a second payment that fully settles the ticket.
4. Open the customer balance screen.

Expected outcome:
- Ticket status changes from `UNPAID` to `PARTIALLY_PAID` to `PAID` in order.
- The remaining amount updates after each payment.
- Customer balance totals reflect the remaining unpaid amount.

## Validate full CRUD and financial history

1. Create a customer with a display label only and add a product with a name and price.
2. Edit the customer label and product price, then create a ticket using the product.
3. Confirm the existing ticket keeps its original product name and price snapshot.
4. Remove the product and customer from active use.
5. Confirm neither is offered for new entries and the customer's saved ticket remains understandable.
6. Record a payment against the ticket and note its remaining balance.
7. Correct the payment amount and confirm the app requests confirmation where required, recalculates the balance, and retains the original payment values in history.
8. Void/remove the payment and confirm it no longer contributes to the balance but remains auditable.
9. Void/remove the ticket and confirm it no longer contributes to the current customer balance but its historical values and audit events remain available.

Expected outcome:
- Product/customer removal does not erase financial history.
- Corrected or voided ticket/payment records retain before/after audit history.
- Current balances and ticket statuses use only active, non-voided financial records.
- Each successful change remains locally persisted and recorded for future reconciliation without upload.

## Validate customer-balance payments with ticket selection

1. Create two unpaid tickets for the same customer.
2. Record a payment against the customer balance.
3. Confirm the ticket-selection popup appears.
4. Observe the oldest unpaid ticket is selected by default.
5. Change the selection to the other ticket and confirm the payment is applied only to that ticket.

Expected outcome:
- The popup appears before payment confirmation.
- Only one ticket is selected at a time.
- The selected ticket receives the payment and its remaining balance decreases accordingly.

## Validate Arabic and French UX

1. Switch the app language to Arabic.
2. Verify the layout becomes RTL and prices remain legible.
3. Switch to French.
4. Verify the layout becomes LTR and all UI labels use translated strings.

Expected outcome:
- Text strings come from translation resources, not hardcoded labels.
- The layout direction matches the active language.
- Ticket, product, customer, and payment screens remain understandable and legible.

## Validate validation and refusal paths

1. Attempt to save a product with a zero or negative price.
2. Attempt to pay more than the selected ticket balance.
3. Try to add a payment to an already paid ticket.
4. Create a product with an empty name or blank barcode.

Expected outcome:
- Each invalid action is blocked.
- A clear explanation is shown.
- No financial record is changed by rejected inputs.
- Cancelling a correction/removal confirmation leaves both the record and balance unchanged.
