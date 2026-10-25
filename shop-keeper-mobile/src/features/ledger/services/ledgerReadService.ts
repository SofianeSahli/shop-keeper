import { listCustomers } from './customerService';
import { buildCustomerLedgerEntries, CustomerLedgerEntry, DebtorSummary, summariseDebtors } from './customerBalanceService';
import { paymentRepository } from './paymentRepository';
import { ticketRepository } from './ticketRepository';

/** Repository-backed read helpers for screens; the calculations live in pure services. */
export function listDebtorSummaries(): DebtorSummary[] {
  return summariseDebtors(listCustomers(), ticketRepository.listTickets(), paymentRepository.listPayments());
}

/** Customers who owe money, highest balance first (Home "Who owes the most"). */
export function listTopDebtors(limit = 3): DebtorSummary[] {
  return listDebtorSummaries()
    .filter((summary) => summary.balanceMillimes > 0)
    .slice(0, limit);
}

export function getCustomerLedgerEntries(customerId: string): CustomerLedgerEntry[] {
  return buildCustomerLedgerEntries(
    customerId,
    ticketRepository.listTickets(customerId),
    paymentRepository.listPayments(customerId),
  );
}
