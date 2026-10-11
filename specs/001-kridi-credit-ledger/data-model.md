# Data Model: Kridi Credit Ledger

## Overview

The feature uses a local SQLite schema inside the mobile app. The design keeps customer private data local to the shop, stores monetary values in integer millimes, and records each create/update/remove action in a pending-change log for eventual upload without implementing upload in this feature.

## Entity Definitions

### Product

| Field | Type | Notes |
|---|---|---|
| `id` | UUID | Internal database key |
| `barcode` | string? | Optional; unique among active products when present; duplicates are rejected and existing product offered |
| `name` | string | Owner-facing product name; trimmed and non-empty |
| `selling_price_millimes` | int | Positive integer; stored in millimes |
| `is_active` | bool | False when removed from active catalog |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last modification timestamp |

Relationships:
- A product can appear on many ticket lines as a historical snapshot.
- A product can be soft-deleted by setting `is_active = false` without altering historical data.

Validation rules:
- `barcode` nullable; when present it must be non-empty after trimming and unique among active products.
- `name` must be non-empty.
- `selling_price_millimes` must be greater than zero.
- Duplicate barcodes are rejected during creation and surfaced as a duplicate-match flow.
- Product removal is a soft removal (`is_active = false`); historical ticket lines are not changed.

### Customer

| Field | Type | Notes |
|---|---|---|
| `id` | UUID | Internal customer ID |
| `internal_code` | string | Randomized, shop-local identifier; not user-facing |
| `display_label` | string | Required, owner-chosen label such as a nickname or alias |
| `real_name` | string? | Optional; should remain private and not required |
| `phone_number` | string? | Optional; should remain private and not required |
| `is_active` | bool | False when removed from active customer lists |
| `created_at` | datetime | Customer creation timestamp |
| `updated_at` | datetime | Last modification timestamp |

Relationships:
- A customer has many tickets.
- A customer has many payments.

Validation rules:
- `display_label` is required and trimmed; duplicate labels may be used when they identify different people.
- Real identity fields are optional and not required for creation.
- The internal code is generated randomly and stored only for stable references.
- Customer removal is a soft removal; historical tickets and payment references remain readable.

### Ticket

| Field | Type | Notes |
|---|---|---|
| `id` | UUID | Ticket record identifier |
| `customer_id` | UUID | Owning customer |
| `created_at` | datetime | Ticket creation time |
| `total_millimes` | int | Sum of ticket-line totals at creation time |
| `status` | enum | Derived as `UNPAID`, `PARTIALLY_PAID`, `PAID` |
| `deleted_at` | datetime? | Set when the ticket is voided; retained for history |

Relationships:
- A ticket belongs to exactly one customer.
- A ticket has many lines and many payments.

Validation rules:
- `total_millimes` must be greater than zero when saved.
- `status` is not manually entered; it is derived from payment totals.
- A ticket can be created even when no payment has been recorded yet.
- A ticket correction or removal that affects financial history records an audit event and pending change in the same transaction.
- Voided tickets are excluded from customer balances but remain available in history.

### TicketLine

| Field | Type | Notes |
|---|---|---|
| `id` | UUID | Line identifier |
| `ticket_id` | UUID | Owning ticket |
| `product_id` | UUID? | Optional reference to catalog product if still present |
| `product_name_snapshot` | string | The product name as it existed at ticket creation |
| `barcode_snapshot` | string? | Optional barcode snapshot as recorded at the time of sale |
| `unit_price_millimes` | int | Immutable selling price at the time of sale |
| `quantity` | int | Must be positive integer |
| `line_total_millimes` | int | `unit_price_millimes * quantity` |

Relationships:
- Each ticket has one or more lines.
- The line stores immutable historical data regardless of later product edits.

Validation rules:
- `quantity` must be greater than zero.
- `unit_price_millimes` must be greater than zero.
- `line_total_millimes` must equal `unit_price_millimes * quantity`.

### Payment

| Field | Type | Notes |
|---|---|---|
| `id` | UUID | Payment record |
| `ticket_id` | UUID | The ticket being paid |
| `customer_id` | UUID | Owning customer for lookup and balance logic |
| `amount_millimes` | int | Positive integer amount |
| `payment_kind` | enum | `TICKET` or `CUSTOMER_BALANCE` |
| `applied_at` | datetime | Payment timestamp |
| `updated_at` | datetime | Last correction timestamp |
| `voided_at` | datetime? | Set when the payment is removed; original row is retained |

Relationships:
- Each payment is assigned to a single ticket.
- A payment belongs to a customer through the ticket relationship.

Validation rules:
- `amount_millimes` must be greater than zero.
- `amount_millimes` cannot exceed the selected ticket's unpaid amount when the owner is creating a payment.
- No payment may be added to a ticket already fully paid.
- Payment corrections validate against the ticket's remaining balance after excluding the payment being corrected.
- Correcting or voiding a payment appends an audit event; voided payments do not contribute to status or balance calculations.

### LedgerAuditEvent

| Field | Type | Notes |
|---|---|---|
| `id` | UUID | Event identifier |
| `entity_type` | enum | `product`, `customer`, `ticket`, or `payment` |
| `entity_id` | UUID | Changed entity identifier |
| `action` | enum | `create`, `update`, `remove`, or `restore` |
| `before_json` | json? | Snapshot before an update/removal |
| `after_json` | json? | Snapshot after a create/update/removal |
| `created_at` | datetime | Event timestamp |

Relationships:
- Each event refers to one ledger entity; an entity can have many audit events.
- Events are append-only and are not removed when an entity is corrected or voided.

Validation rules:
- The event and corresponding entity mutation must be written in the same database transaction.
- Audit snapshots must not include data from outside the ledger record being changed.
- Financial correction/removal events retain the before/after values needed to explain the current balance.

### PendingChange

| Field | Type | Notes |
|---|---|---|
| `id` | UUID | Operation log entry |
| `entity_type` | enum | `product`, `customer`, `ticket`, `payment` |
| `entity_id` | UUID | Changed record identifier |
| `operation` | enum | `create`, `update`, `delete`, `soft_delete`, `void` |
| `payload_json` | json | Full record snapshot or delta |
| `status` | enum | `pending`, `applied`, `rejected` |
| `created_at` | datetime | Operation timestamp |
| `updated_at` | datetime | Last mutation timestamp |

Relationships:
- The operation log records every local create/update/remove action.
- It is used for future upload and reconciliation without being part of this feature's UI flow.

Validation rules:
- `operation` and `entity_type` must be non-empty.
- `payload_json` must be valid JSON.
- Pending change records must remain after app restart until future upload or explicit cleanup.
- Each successful entity mutation and its pending change record must commit atomically.
- Pending changes are retained locally; no upload or synchronization is implemented in this feature.

## Derived Logic

### Customer balance

`customer_balance_millimes = sum(ticket.remaining_amount_millimes for non-voided tickets)`

where `remaining_amount_millimes = ticket.total_millimes - sum(non-voided payments for that ticket)`.

The app must keep balance at zero or above and never allow a payment to reduce a balance below zero.

### Ticket status

- `UNPAID` when `total_paid_millimes = 0`
- `PARTIALLY_PAID` when `0 < total_paid_millimes < ticket.total_millimes`
- `PAID` when `total_paid_millimes >= ticket.total_millimes`
- Voided tickets do not appear as active debts; their audit history remains viewable.
- Voided payments are excluded from `total_paid_millimes`.

## State Transitions

### Product life cycle

- `draft` -> `active` as soon as the barcode and price are confirmed.
- `active` -> `inactive` when the owner removes or disables it from current catalog.
- Historical ticket lines remain intact even when a product is no longer active.

### Customer life cycle

- `created` -> `active` when saved locally.
- `active` -> `active` while tickets and payments are added.
- `active` -> `inactive` when removed from active customer lists; existing ledger records remain accessible.

### Ticket life cycle

- `draft` -> `saved` when the ticket is created.
- `saved` -> `partially_paid` when a payment is accepted.
- `saved` -> `paid` when accumulated payments cover the total.
- `partially_paid` -> `paid` when remaining balance is cleared.
- `saved` or `partially_paid` -> `voided` when the owner confirms removal; the ticket is excluded from balances while its original values remain in audit history.

### Payment life cycle

- `entered` -> `validated` when positive and within ticket limit.
- `validated` -> `stored` when written to local persistence.
- `stored` -> `pending_upload` when the operation is logged for future upload.
- An existing payment correction updates the current payment values and appends a before/after event.
- A payment removal sets `voided_at` and appends a before/after event; it does not hard-delete the payment row.
