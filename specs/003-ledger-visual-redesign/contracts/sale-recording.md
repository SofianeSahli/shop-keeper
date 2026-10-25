# Sale Recording and Read Helpers Contract (003)

Local service contract between screens and `features/ledger/services/`. There is no network API. All writes run in one `withLedgerTransaction` and append `ledger_audit_events` + `pending_changes` in the same transaction (001 rule). Thrown `Error` messages are English developer messages, mapped to translation keys by `src/i18n/errorMessages.ts`.

## 1. `recordSale(ticket: Ticket, choice: SalePaymentChoice): { ticket: Ticket; payment: Payment | null }`

File: `saleService.ts`. `ticket` comes from `buildTicketFromDraft(customerId || WALK_IN_CUSTOMER_ID, items)`.

Steps (one transaction):
1. `validateSaleChoice(choice, ticket.totalMillimes, ticket.customerId)`; throw on error.
2. Customer must exist and be active. Walk-in is allowed only with `PAID`.
3. `insertTicketRecords(ticket, db)`: ticket + lines + audit `create` + pending `ticket/create`.
4. When `getSalePaymentAmount(choice, total) > 0`: `insertPaymentRecords(createPaymentRecord({ ticketId, customerId, amountMillimes, paymentKind: 'AT_SALE', appliedAt: ticket.createdAt }), db)`: payment + audit `create` + pending `payment/create`.
5. Return the ticket re-read with its derived `status`, and the payment.

If any step fails, nothing is kept (US8-AS6). The form keeps its draft and shows the mapped message.

| Choice | Customer | Payment written | Status after | Dashboard effect |
|---|---|---|---|---|
| PAID | none → walk-in | AT_SALE = total | PAID | sales +total, kridi +0 |
| PAID | named | AT_SALE = total | PAID | sales +total, kridi +0 |
| PARTIAL (a) | named | AT_SALE = a | PARTIALLY_PAID | sales +total, kridi +(total − a) |
| UNPAID | named | none | UNPAID | sales +total, kridi +total |
| PARTIAL / UNPAID | none / walk-in | — (rejected) | — | — |

## 2. Pure helpers (`saleService.ts`)

```ts
validateSaleChoice(choice, totalMillimes, customerId: string | null):
  null | 'kridiNeedsCustomer' | 'partialAmountInvalid' | 'partialEqualsTotal'
getSalePaymentAmount(choice, totalMillimes): number
getSaleSaveLabel(choice, totalMillimes): { key: 'collectAmount' | 'collectWithRemainder' | 'putOnKridi'; params: { amount?: number; paid?: number; rest?: number } }
```
The screen formats the `params` with `formatMillimeAmount` and `formatMessage`.

## 3. Changed write behaviour

| Function | New rule | Error message (→ key) |
|---|---|---|
| `TicketRepository.createTicket` | Kept for compatibility; rejects walk-in (sales must use `recordSale`). | `Walk-in tickets must be paid at sale.` → `walkInLocked` |
| `TicketRepository.updateTicket` | Walk-in ticket: new total must equal the amount paid. Reassignment **to** walk-in is rejected. The 001 rules are unchanged otherwise. | `Walk-in tickets must stay fully paid.` → `walkInLocked` |
| `TicketRepository.voidTicket` | Also voids each active `AT_SALE` payment of the ticket (audit `void` + pending `payment/soft_delete` each). | — |
| `PaymentRepository.createPayment` | Rejects walk-in tickets. | `Walk-in tickets cannot receive payments.` → `walkInLocked` |
| `PaymentRepository.updatePayment` / `voidPayment` | Rejects the `AT_SALE` payment of a walk-in ticket. | `Void the walk-in ticket instead.` → `walkInLocked` |
| `updateCustomer` / `removeCustomer` | Rejects walk-in. | `The walk-in customer cannot be changed.` → `walkInLocked` |
| `listCustomers(search, includeInactive, repo, { includeWalkIn = false })` | Excludes walk-in by default. | — |
| `validateSaleChoice` errors thrown by `recordSale` | — | `Credit needs a customer.` → `kridiNeedsCustomer`; `Partial amount must be above 0 and below the total.` → `partialAmountInvalid` |

Add the error rules to `errorMessages.ts` **before** the generic `/ticket|line|total/` and `/amount/` rules so they match first.

## 4. Read helpers

| Function | File | Returns |
|---|---|---|
| `summariseDebtors(customers, tickets, payments)` | `customerBalanceService.ts` | `DebtorSummary[]` (see data-model §5), walk-in excluded, sorted by balance descending. |
| `getCustomerFilterCounts(summaries)` | `customerBalanceService.ts` | `{ owes, upToDate, all }` |
| `listDebtorSummaries()` / `listTopDebtors(limit = 3)` | `ledgerReadService.ts` | Repository-backed wrappers; `listTopDebtors` returns only balance > 0. |
| `getDashboardSummary(date)` | `dashboardService.ts` | `+ todayTicketCount`, `+ openTicketCount`; outstanding computed in one pass. |
| `getCustomerLedgerEntries(customerId)` | `ledgerReadService.ts` (pure part: `buildCustomerLedgerEntries` in `customerBalanceService.ts`) | Ticket and payment entries (incl. `ticketCreatedAt` for "sur ticket du …", `paymentKind` for "Payé à la vente"), newest first, ready for `groupByLocalDay`. |

Existing exports keep their signatures (`summariseCustomerBalances`, `getCustomerPaymentCandidates`), so existing tests stay green (SC-005).

## 5. Migration

`ensureWalkInCustomer(db: SQLiteDatabase): Promise<void>`, called last in `migrateLedgerDatabase`. It is idempotent and writes the pending change only on first insert (data-model §1 and §7).
