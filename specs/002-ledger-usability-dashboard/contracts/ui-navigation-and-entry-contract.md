# UI Navigation and Entry Contract

This feature exposes user-facing navigation and local ledger workflows only. It introduces no remote API, new shared service, or storage schema.

## Main Toolbar

| Main area | Home route | Active for child routes |
|---|---|---|
| Home | `/ledger` | Dashboard |
| Products | `/ledger/products` | Product catalog, product form, and product detail/edit routes |
| Tickets | `/ledger/tickets` | Ticket list, ticket form, and ticket detail |
| Customers | `/ledger/customers` | Customer list, customer form, and customer ledger |
| Payments | `/ledger/payments` | Payment history and payment form |

- The toolbar is visible on ledger routes and stays outside route-specific scrolling content.
- Exactly one item is exposed as selected and visibly highlighted.
- Arabic and French labels come from translation resources; RTL/LTR direction follows the active language.
- Toolbar items are accessible buttons or links with accessible labels and selected state.
- The existing `/` language-selection and entry screen remains available and links into the ledger. `/ledger` opens the Home dashboard rather than the former area-selection hub.
- When a toolbar navigation would remove a form with unsaved values, the app presents a localized confirmation. Cancel keeps the form and its values; confirm completes the requested navigation and discards the unsaved form data.

## Dashboard

- Shows two non-negative integer-millime aggregates: sales today and outstanding unpaid balance.
- Sales today is the sum of saved ticket-line totals for non-voided tickets created on the device-local calendar date. Payments do not reduce sales.
- Outstanding balance is the sum of each non-voided ticket's total minus its active payments, floored at zero.
- Empty results are displayed as zero.
- After a relevant saved mutation or return to Home, both values reflect current saved ledger state without restarting the app.
- Provides direct actions to Add Product, New Ticket, and Record Payment.
- A successful dashboard-started action returns to Home and refreshes the summary. A validation failure or cancellation is not shown as a success.

## Product Code Entry

1. The owner can request a camera scan for barcode or QR formats supported by the app.
2. A successful scan populates the editable product-code field.
3. Camera denial, unavailable hardware, cancelled scanning, or an unreadable scan leaves manual entry available; a code is optional.
4. Scanned and manually entered codes use the same trimmed exact-match lookup against active products.
5. If a matching active product exists, warn the owner and offer to open it; do not create a duplicate active product.
6. If there is no active match, continue normal product validation and creation.

## Inline Ticket Entry

- Opening inline customer or product creation must not remove or replace the ticket form route.
- Customer success adds/selects the new customer only after its create operation succeeds.
- Product success adds the product to the in-memory choices and appends a quantity-one ticket line only after its create operation succeeds.
- Cancel, validation failure, or persistence failure closes or reports on the inline flow without changing the prior draft customer, lines, quantities, or totals.
- The ticket itself remains unsaved until the owner explicitly saves it.

## Shared Feedback and Accessibility

- Permission denial, duplicate product code, save failure, empty dashboard, and unsaved-change confirmation have clear localized outcomes in Arabic and French.
- Primary toolbar and dialog actions have accessible names, roles, and large touch targets.
- Camera access is requested in context; denial does not block manual input.
