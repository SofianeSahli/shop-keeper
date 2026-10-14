import { Customer } from '../types/customer';
import { Payment } from '../types/payment';
import { Ticket } from '../types/ticket';
import { getTicketStatus } from './ticketService';
import { sumMillimeAmounts } from '@/lib/money';

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
