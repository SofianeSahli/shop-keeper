# Local Operations Contract

This feature does not expose a backend API in the initial release. The contract in scope is the local mobile app's persistence contract for ledger data and the pending-change log used for eventual upload.

## Database contract

### Product rows

```json
{
  "id": "uuid",
  "barcode": "code-123",
  "name": "Tomatoes",
  "selling_price_millimes": 2500,
  "is_active": true,
  "created_at": "2026-10-10T12:00:00Z",
  "updated_at": "2026-10-10T12:00:00Z"
}
```

### Customer rows

```json
{
  "id": "uuid",
  "internal_code": "c9d2d1f9-7f68-4af6-b5f5-1d0cb17b2cd4",
  "display_label": "Ali",
  "real_name": null,
  "phone_number": null,
  "is_active": true,
  "created_at": "2026-10-10T12:00:00Z",
  "updated_at": "2026-10-10T12:00:00Z"
}
```

### Ticket rows

```json
{
  "id": "uuid",
  "customer_id": "uuid",
  "created_at": "2026-10-10T12:00:00Z",
  "updated_at": "2026-10-10T12:00:00Z",
  "total_millimes": 9500,
  "status": "PARTIALLY_PAID",
  "voided_at": null
}
```

### Ticket line rows

```json
{
  "id": "uuid",
  "ticket_id": "uuid",
  "product_id": "uuid or null",
  "product_name_snapshot": "Tomatoes",
  "barcode_snapshot": "code-123",
  "unit_price_millimes": 2500,
  "quantity": 2,
  "line_total_millimes": 5000
}
```

### Payment rows

```json
{
  "id": "uuid",
  "ticket_id": "uuid",
  "customer_id": "uuid",
  "amount_millimes": 3000,
  "payment_kind": "TICKET",
  "applied_at": "2026-10-10T12:05:00Z",
  "updated_at": "2026-10-10T12:05:00Z",
  "voided_at": null
}
```

### Audit event rows

```json
{
  "id": "uuid",
  "entity_type": "payment",
  "entity_id": "uuid",
  "action": "update",
  "before_json": {"amount_millimes": 3000},
  "after_json": {"amount_millimes": 2500},
  "created_at": "2026-10-10T12:10:00Z"
}
```

## Pending change log contract

```json
{
  "id": "uuid",
  "entity_type": "ticket",
  "entity_id": "uuid",
  "operation": "create",
  "payload_json": {"ticket": {"...": "..."}},
  "status": "pending",
  "created_at": "2026-10-10T12:00:00Z",
  "updated_at": "2026-10-10T12:00:00Z"
}
```

## Contract guarantees

- Product/customer active flags and ticket/payment void timestamps determine availability; removal does not physically delete the row.
- All monetary amounts are stored as positive integers in millimes.
- Ticket status is derived from the ticket total and its non-voided payments; the stored status is refreshed as part of successful payment changes.
- Every change is written locally before any UI success state is shown.
- Ticket totals and customer balances are derived from stored records instead of inferred from transient UI state.
- Product and customer removal marks the record inactive; existing historical references remain readable.
- Ticket and payment corrections append before/after audit events. Removal voids the financial record instead of hard-deleting it; voided financial records are excluded from current balances and status calculations.
- A mutation, its audit event when applicable, and its pending-change entry are committed atomically. If any write fails, none of those writes may be reported as successful.
- Pending changes are local-only in this feature. No network transmission is attempted.
- Future upload is intentionally not implemented in this release; the pending-change records remain the synchronization contract for later work.
