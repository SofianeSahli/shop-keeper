import { Customer } from '../types/customer';
import { Payment } from '../types/payment';
import { Ticket } from '../types/ticket';
import { getTicketStatus } from './ticketService';
import { sumMillimeAmounts } from '@/lib/money';
import { isWalkInCustomer } from '../constants';

export interface CustomerBalanceSummary {
  customerId: string;
  displayLabel: string;
  outstandingBalanceMillimes: number;
}

export function summariseCustomerBalances(
  customers: Customer[],
  tickets: Ticket[],
  payments: Payment[],
): CustomerBalanceSummary[] {
  const paymentTotalsByTicket = new Map<string, number>();

  for (const payment of payments) {
    if (payment.voidedAt) {
      continue;
    }
    paymentTotalsByTicket.set(
      payment.ticketId,
      sumMillimeAmounts([paymentTotalsByTicket.get(payment.ticketId) ?? 0, payment.amountMillimes]),
    );
  }

  const balancesByCustomer = new Map<string, number>();

  for (const ticket of tickets) {
    if (ticket.voidedAt) {
      continue;
    }
    const paid = paymentTotalsByTicket.get(ticket.id) ?? 0;
    const remaining = Math.max(ticket.totalMillimes - paid, 0);
    balancesByCustomer.set(
      ticket.customerId,
      sumMillimeAmounts([balancesByCustomer.get(ticket.customerId) ?? 0, remaining]),
    );
  }

  return customers
    .map((customer) => ({
      customerId: customer.id,
      displayLabel: customer.displayLabel,
      outstandingBalanceMillimes: balancesByCustomer.get(customer.id) ?? 0,
    }))
    .sort((left, right) => right.outstandingBalanceMillimes - left.outstandingBalanceMillimes);
}

export function getCustomerPaymentCandidates(
  customerId: string,
  tickets: Ticket[],
  payments: Payment[],
): Ticket[] {
  const paidByTicket = new Map<string, number>();
  for (const payment of payments) {
    if (!payment.voidedAt) {
      paidByTicket.set(
        payment.ticketId,
        sumMillimeAmounts([paidByTicket.get(payment.ticketId) ?? 0, payment.amountMillimes]),
      );
    }
  }
  return tickets
    .filter((ticket) =>
      ticket.customerId === customerId &&
      !ticket.voidedAt &&
      getTicketStatus(paidByTicket.get(ticket.id) ?? 0, ticket.totalMillimes) !== 'PAID',
    )
    .sort((left, right) => left.createdAt.localeCompare(right.createdAt));
}

export interface DebtorSummary {
  customerId: string;
  displayLabel: string;
  phoneNumber: string | null;
  balanceMillimes: number;
  openTicketCount: number;
  oldestOpenTicketAt: string | null;
  lastPaymentAt: string | null;
}

export interface CustomerFilterCounts {
  owes: number;
  upToDate: number;
  all: number;
}

/** Sum of active (non-voided) payments per ticket id. */
export function sumActivePaymentsByTicket(payments: readonly Payment[]): Map<string, number> {
  const paidByTicket = new Map<string, number>();
  for (const payment of payments) {
    if (payment.voidedAt) continue;
    paidByTicket.set(
      payment.ticketId,
      sumMillimeAmounts([paidByTicket.get(payment.ticketId) ?? 0, payment.amountMillimes]),
    );
  }
  return paidByTicket;
}

/**
 * One pass over tickets and payments: balance, open tickets, oldest open ticket and last payment per
 * customer. The walk-in customer never appears. Sorted by balance (highest first), then label.
 */
export function summariseDebtors(
  customers: readonly Customer[],
  tickets: readonly Ticket[],
  payments: readonly Payment[],
): DebtorSummary[] {
  const paidByTicket = sumActivePaymentsByTicket(payments);
  const summaries = new Map<string, DebtorSummary>();
  for (const customer of customers) {
    if (isWalkInCustomer(customer.id)) continue;
    summaries.set(customer.id, {
      customerId: customer.id,
      displayLabel: customer.displayLabel,
      phoneNumber: customer.phoneNumber ?? null,
      balanceMillimes: 0,
      openTicketCount: 0,
      oldestOpenTicketAt: null,
      lastPaymentAt: null,
    });
  }

  for (const ticket of tickets) {
    const summary = summaries.get(ticket.customerId);
    if (!summary || ticket.voidedAt) continue;
    const remaining = Math.max(ticket.totalMillimes - (paidByTicket.get(ticket.id) ?? 0), 0);
    if (remaining === 0) continue;
    summary.balanceMillimes = sumMillimeAmounts([summary.balanceMillimes, remaining]);
    summary.openTicketCount += 1;
    if (!summary.oldestOpenTicketAt || ticket.createdAt < summary.oldestOpenTicketAt) {
      summary.oldestOpenTicketAt = ticket.createdAt;
    }
  }

  for (const payment of payments) {
    const summary = summaries.get(payment.customerId);
    if (!summary || payment.voidedAt) continue;
    if (!summary.lastPaymentAt || payment.appliedAt > summary.lastPaymentAt) {
      summary.lastPaymentAt = payment.appliedAt;
    }
  }

  return [...summaries.values()].sort(
    (left, right) =>
      right.balanceMillimes - left.balanceMillimes ||
      left.displayLabel.localeCompare(right.displayLabel, undefined, { sensitivity: 'base' }),
  );
}

export function getCustomerFilterCounts(summaries: readonly DebtorSummary[]): CustomerFilterCounts {
  const owes = summaries.filter((summary) => summary.balanceMillimes > 0).length;
  return { owes, upToDate: summaries.length - owes, all: summaries.length };
}

export type CustomerLedgerEntry =
  | { type: 'ticket'; id: string; at: string; ticket: Ticket; remainingMillimes: number }
  | { type: 'payment'; id: string; at: string; payment: Payment; ticketCreatedAt: string | null };

/**
 * A customer's history for the ledger screen (spec US5): active tickets with their remaining amount
 * and active payments with the date of the ticket they paid. Newest first; at the same instant the
 * payment comes first, since it follows the sale it pays.
 */
export function buildCustomerLedgerEntries(
  customerId: string,
  tickets: readonly Ticket[],
  payments: readonly Payment[],
): CustomerLedgerEntry[] {
  const customerTickets = tickets.filter((ticket) => ticket.customerId === customerId && !ticket.voidedAt);
  const customerPayments = payments.filter((payment) => payment.customerId === customerId && !payment.voidedAt);
  const paidByTicket = sumActivePaymentsByTicket(customerPayments);
  const ticketDates = new Map(customerTickets.map((ticket) => [ticket.id, ticket.createdAt]));

  const entries: CustomerLedgerEntry[] = [
    ...customerTickets.map((ticket) => ({
      type: 'ticket' as const,
      id: ticket.id,
      at: ticket.createdAt,
      ticket,
      remainingMillimes: Math.max(ticket.totalMillimes - (paidByTicket.get(ticket.id) ?? 0), 0),
    })),
    ...customerPayments.map((payment) => ({
      type: 'payment' as const,
      id: payment.id,
      at: payment.appliedAt,
      payment,
      ticketCreatedAt: ticketDates.get(payment.ticketId) ?? null,
    })),
  ];
  return entries.sort(
    (left, right) =>
      right.at.localeCompare(left.at) || (left.type === right.type ? 0 : left.type === 'payment' ? -1 : 1),
  );
}
