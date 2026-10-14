# Data Model: Ledger Usability Dashboard

Feature 002 introduces derived and in-progress UI state only. Existing products, customers, tickets, ticket lines, and payments remain the persisted source of truth; no schema migration or new persistent entity is required.

## Dashboard Summary

Represents totals derived for the dashboard at the time it is opened or refreshed.

| Field | Type | Required | Rules |
|---|---|---:|---|
| `localDate` | Local calendar date | Yes | The current date on the device when the summary is calculated. |
| `salesMillimes` | Non-negative safe integer | Yes | Sum of saved line totals on non-voided tickets whose creation instant falls on `localDate` in device-local time. Paid status does not affect this value. |
| `outstandingMillimes` | Non-negative safe integer | Yes | Sum across non-voided tickets of `max(ticket total - active payment total, 0)`. Fully paid tickets contribute zero; partially paid tickets contribute only their remainder. |

### Derivation and lifecycle

- Both fields are derived from current saved records; they are not independently editable or persisted.
- A ticket contributes to sales on its recorded creation date. An edit contributes its current saved line totals to that same date; voiding excludes the ticket.
- A payment create, edit, or void affects outstanding balance only. It never changes the sales total.
- A ticket create, edit, or void affects sales when its creation date is today, and affects outstanding balance while it is not voided.
- Missing qualifying records produce zero.
- Amounts use integer millimes and existing ledger summation/overflow validation.

## Navigation Section

Represents the main area selected in the persistent toolbar.

| Field | Type | Required | Rules |
|---|---|---:|---|
| `section` | `Home \| Products \| Tickets \| Customers \| Payments` | Yes | Resolved from the active route. |
| `route` | Application route | Yes | Home opens the dashboard; existing product, ticket, customer, and payment routes remain available under their corresponding section. |
| `isActive` | Boolean | Yes | Exactly one main area is active for a rendered ledger route. Child detail and form routes resolve to their parent area. |

Section state is navigation state, not a new ledger database record. Toolbar order and direction-aware visual arrangement are defined in [the UI navigation and entry contract](./contracts/ui-navigation-and-entry-contract.md).

## Product Code Lookup

Represents a transient scan or manually entered catalog lookup.

| Field | Type | Required | Rules |
|---|---|---:|---|
| `code` | String | No | Trimmed input from a barcode scan or manual entry; may remain absent. |
| `source` | `scan \| manual` | Yes | Identifies how the current code was supplied. |
| `matchedProductId` | Product identifier or null | Yes | Identifies an active product whose trimmed code exactly matches the input, if one exists. |
| `outcome` | `empty \| unique \| duplicate \| unavailable` | Yes | Duplicate outcome offers the existing product; empty/unavailable outcomes leave manual entry available. |

No product is created by lookup alone. A new product is created only after the user completes and saves a valid product form. Existing product-service duplicate validation remains authoritative for persistence.

## Ticket Draft

Represents the unsaved ticket currently being edited. It remains in the ticket-form state while an inline create dialog is open.

| Field | Type | Required | Rules |
|---|---|---:|---|
| `customerId` | Customer identifier or empty | Yes | References the chosen active customer; becomes the newly created customer's identifier after successful inline creation. |
| `items` | Ticket draft item list | Yes | Existing lines, order, quantities, and values remain unchanged when an inline dialog is cancelled or fails. |
| `selectedProductId` | Product identifier or null | No | A successfully created inline product is immediately represented as a new draft line with quantity one. |

The draft is not persisted as a saved ticket until the owner explicitly saves. The existing ticket creation service continues to snapshot product name, barcode, and unit price at save time.

## Inline Creation Result

Transient result returned to the ticket draft after an inline entity is successfully persisted.

| Entity | Success effect | Cancel/failure effect |
|---|---|---|
| Customer | Add it to available customer selection and select it for this ticket draft. | Keep the existing selection and all ticket lines unchanged. |
| Product | Add it to the available products and append one ticket draft line at quantity one. | Keep all ticket lines and current selections unchanged. |

## Existing Entity References

- `Product`: existing active/inactive catalog record with optional barcode and integer-millime selling price.
- `Customer`: existing active/inactive shop-local identity and display label.
- `Ticket`: existing creation date, saved total, and void state.
- `TicketLine`: existing product-name/barcode/price snapshot, quantity, and line total.
- `Payment`: existing applied amount and void state; only non-voided payment values reduce a ticket balance.
