# Data Model: Ledger Visual Redesign (003)

003 adds **no table and no column**. It adds one seeded row, one enum value, one form-state type and read models. All money values are integer millimes.

## 1. Walk-in Customer (seeded row)

| Field | Value | Rule |
|---|---|---|
| `id` | `'walk-in'` (`WALK_IN_CUSTOMER_ID`) | Fixed and identical on every device. |
| `internal_code` | `'WALK_IN'` | Satisfies `UNIQUE NOT NULL`. |
| `display_label` | `'walk-in'` | Never displayed; the UI shows translation `walkInCustomer` ("Client de passage" / "حريف عابر"). |
| `real_name`, `phone_number` | `NULL` | |
| `is_active` | `1` | Must stay active; cannot be removed. |
| `created_at`, `updated_at` | migration time (ISO) | |

**Created by**: `ensureWalkInCustomer(db)` in `src/db/migrations.ts`, called at the end of `migrateLedgerDatabase`. It runs `INSERT OR IGNORE`. When `changes = 1`, it also writes one `pending_changes` row (`entity_type='customer'`, `operation='create'`) inside one transaction. Re-running it is a no-op.

**Invariants** (enforced in services, see [contracts/sale-recording.md](./contracts/sale-recording.md)):
- Only tickets paid in full at sale (`AT_SALE` payment amount = ticket total) may reference it.
- Its outstanding balance is always 0.
- It is excluded from customer lists, debtor summaries, filter counts and pickers.
- It cannot be edited, removed, receive a later payment, or have a ticket reassigned to it.

## 2. Payment (existing) — new kind

`PaymentKind = 'TICKET' | 'CUSTOMER_BALANCE' | 'AT_SALE'`

| Field | At-sale value |
|---|---|
| `payment_kind` | `'AT_SALE'` |
| `ticket_id` / `customer_id` | The new ticket and its customer (walk-in or named). |
| `amount_millimes` | Paid: ticket total. Partial: the amount received, `0 < amount < total`. |
| `applied_at` | Equal to `ticket.created_at`. |
| `voided_at` | Set when the ticket is voided (cascade) or when the owner voids it (named customers only). |

Exactly zero or one `AT_SALE` payment exists per ticket, and only one created by `recordSale`. Later `TICKET` payments follow the 001 rules unchanged.

## 3. Ticket (existing) — behaviour change only

- New tickets are created through `recordSale`. `status` stays derived from active payments (`getTicketStatus`). Its value right after save is: Paid → `PAID`, Partial → `PARTIALLY_PAID`, Kridi → `UNPAID`.
- `voidTicket` cascades to the active `AT_SALE` payment.
- Walk-in tickets: an update must keep `total === totalPaid`.

## 4. Sale Payment Choice (form state, not stored) — `features/ledger/types/sale.ts`

```ts
type SalePaymentChoice =
  | { kind: 'PAID' }
  | { kind: 'PARTIAL'; amountMillimes: number | null }
  | { kind: 'UNPAID' };
```

| Rule | Error key |
|---|---|
| Default `{ kind: 'PAID' }` on new tickets; selector hidden on edit. | — |
| `PARTIAL`/`UNPAID` require a customer other than walk-in. | `kridiNeedsCustomer` |
| `PARTIAL.amountMillimes` is a safe integer with `0 < amount < total`. | `partialAmountInvalid` |
| `PARTIAL.amountMillimes === total` | `partialEqualsTotal` (suggests "Payé") |
| No customer chosen + `PAID` → walk-in. | — |

Derived values: `getSalePaymentAmount` (total / amount / 0) and remaining to kridi (`total − paymentAmount`).

## 5. Read models (derived, not stored)

### DebtorSummary — `customerBalanceService.summariseDebtors`
| Field | Type | Derivation |
|---|---|---|
| `customerId`, `displayLabel`, `phoneNumber` | string / string \| null | Active customers except walk-in. |
| `balanceMillimes` | integer ≥ 0 | Σ over non-voided tickets of max(total − active payments, 0). |
| `openTicketCount` | integer ≥ 0 | Non-voided tickets with remaining > 0. |
| `oldestOpenTicketAt` | ISO \| null | Min `created_at` of open tickets. |
| `lastPaymentAt` | ISO \| null | Max `applied_at` of active payments ("Tout payé le …"). |

Sort: `balanceMillimes` descending, then `displayLabel` (case-insensitive).

### CustomerFilterCounts
`{ owes: count(balance > 0), upToDate: count(balance = 0), all }`.

### DashboardSummary (extended)
| Field | Rule |
|---|---|
| `localDate` | Unchanged. |
| `todaySalesMillimes` | Σ totals of non-voided tickets created on the local date, **all statuses** (SC-008). |
| `todayTicketCount` | Count of the same tickets. |
| `outstandingBalanceMillimes` | Σ remaining over non-voided tickets (walk-in contributes 0 by invariant). |
| `openTicketCount` | Non-voided tickets with remaining > 0. |

### LedgerHistoryEntry / DayGroup — `components/ui/logic/dayGroups.ts`
`groupByLocalDay<T>(items, getDate, now)` returns `{ dayKey, label: 'today' | 'yesterday' | 'date', date, items }[]`, newest first. It uses `getLocalDateKey` from `dashboardService`. Customer-ledger entries are a union of `{ type: 'ticket', ticket, remaining }` and `{ type: 'payment', payment, ticketCreatedAt }`.

### QuickAmountOption — `components/ui/logic/quickAmounts.ts`
`{ id: 'whole' | 10000 | 20000 | 50000, amountMillimes, enabled }`. Fixed chips are enabled only when `remaining ≥ 10 000`. The applied amount is `min(chip, remaining)`.

## 6. Design Tokens (configuration)

Defined in `tailwind.config.js` `theme.extend`. Values, names and usage are given in [design-reference.md §1](./design-reference.md):
- `colors`: 22 tokens incl. `primary-faint`, `debt-line`, `partial-line`.
- `borderRadius`: `card 18`, `tile 20`, `control 14`, `control-lg 16`.
- `fontFamily`: `plex`, `plex-medium`, `plex-semibold`, `plex-bold`.
- `fontSize`: `display 30`, `display-lg 34`, `title 19`, `heading 16`, `body 15`, `label 13`, `caption 12.5`, `tab 12`.
- `minHeight`/`minWidth`: `touch 44`, `button 52`, `button-lg 56`, `row 60`.

## 7. Migration summary

| Step | Idempotency | Operation log |
|---|---|---|
| `ensureWalkInCustomer` | `INSERT OR IGNORE` on fixed id | One `pending_changes` `customer/create` row, only on first insert. Same transaction. |

There is no other schema step. Existing 001/002 migrations are unchanged.
