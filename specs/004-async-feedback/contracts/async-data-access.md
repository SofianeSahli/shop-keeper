# Async Data Access Contract (004)

Changes to the service layer under `shop-keeper-mobile/src/features/ledger/services/` and `src/db/`. Business rules, validation messages, transactions and operation-log entries are **unchanged** from 001–003. Only the calling convention changes.

## Rule

Every function that reads or writes the database returns a `Promise` and is awaited. Pure helpers (`validateSaleChoice`, `summariseDebtors`, `summariseDashboard`, `buildCustomerLedgerEntries`, `filterTicketsByStatus`, money and date formatters…) stay synchronous.

## `src/db`

| Function | Before | After |
|---|---|---|
| `openLedgerDatabase()` | `Promise<db>` (sync migrations inside) | `Promise<db>`; migrations and `ensureWalkInCustomer` use async calls |
| `withLedgerTransaction(op, db)` | `T` | `Promise<T>`; `op` is `async (db) => T`; runs inside `runQueued` + `withTransactionAsync` |
| `runQueued(task)` | — | New. Serial queue for every database task (research R2), with the dev-only delay (R6). |

## Repositories and services (all `async`)

| Module | Functions |
|---|---|
| `ledgerRepository` | `listProducts`, `getProduct`, `findActiveProductByBarcode`, `saveProduct`, `listCustomers`, `getCustomer`, `saveCustomer` |
| `ticketRepository` | `listTickets`, `getTicket`, `totalPaid`, `saveTicket`, `insertTicketRecords`, `createTicket`, `updateTicket`, `voidTicket` |
| `paymentRepository` | `listPayments`, `getPayment`, `createPayment`, `updatePayment`, `voidPayment` |
| `paymentRecords` | `writePaymentRow`, `insertPaymentRecords`, `voidPaymentRecords`, `listActiveSalePayments` |
| `auditService` / `pendingChangeService` | `appendAuditEvent`, `listAuditEvents`, `persistPendingChange`, `listPendingChanges` |
| `customerService` / `productService` | `listCustomers`, `getCustomer`, `createCustomer`, `updateCustomer`, `removeCustomer`; `listProducts`, `getProduct`, `createProduct`, `updateProduct`, `removeProduct` |
| `saleService` | `recordSale` |
| `dashboardService` / `ledgerReadService` | `getDashboardSummary`, `listDebtorSummaries`, `listTopDebtors`, `getCustomerLedgerEntries` |
| `i18n/languageStorage` | `getSavedLanguage`, `saveLanguage` |

Inside a transaction, functions keep receiving the transaction's database handle and **must not** call `runQueued` again (that would deadlock the queue). Only the public entry points enqueue.

## Errors

Thrown errors keep their 001–003 English messages, so `getLedgerErrorMessage` mappings stay valid. A rejected promise rolls the transaction back.
