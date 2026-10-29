import { listCustomers } from './customerService';
import { buildCustomerLedgerEntries, CustomerLedgerEntry, DebtorSummary, summariseDebtors } from './customerBalanceService';
import { paymentRepository } from './paymentRepository';
import { ticketRepository } from './ticketRepository';

export async function listDebtorSummaries(): Promise<DebtorSummary[]> {
  const customers = await listCustomers();
  const tickets = await ticketRepository.listTickets();
  const payments = await paymentRepository.listPayments();
  return summariseDebtors(customers, tickets, payments);
}

export async function listTopDebtors(limit = 3): Promise<DebtorSummary[]> {
  const summaries = await listDebtorSummaries();
  return summaries.filter((summary) => summary.balanceMillimes > 0).slice(0, limit);
}

export async function getCustomerLedgerEntries(customerId: string): Promise<CustomerLedgerEntry[]> {
  const tickets = await ticketRepository.listTickets(customerId);
  const payments = await paymentRepository.listPayments(customerId);
  return buildCustomerLedgerEntries(customerId, tickets, payments);
}
