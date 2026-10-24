import { paymentRepository } from './paymentRepository';
import { ticketRepository } from './ticketRepository';
import { sumActivePaymentsByTicket } from './customerBalanceService';
import { getLocalDateKey } from '@/lib/dates';
import { sumMillimeAmounts } from '@/lib/money';
import { Payment } from '../types/payment';
import { Ticket } from '../types/ticket';

export { getLocalDateKey };

export interface DashboardSummary {
  localDate: string;
  /** Every non-voided ticket created today, whatever its payment status (SC-008). */
  todaySalesMillimes: number;
  todayTicketCount: number;
  /** Unpaid remainders of all non-voided tickets. */
  outstandingBalanceMillimes: number;
  openTicketCount: number;
}

export function summariseDashboard(
  tickets: readonly Ticket[],
  payments: readonly Payment[],
  date: Date = new Date(),
): DashboardSummary {
  const localDate = getLocalDateKey(date);
  const paidByTicket = sumActivePaymentsByTicket(payments);
  const summary: DashboardSummary = {
    localDate,
    todaySalesMillimes: 0,
    todayTicketCount: 0,
    outstandingBalanceMillimes: 0,
    openTicketCount: 0,
  };

  for (const ticket of tickets) {
    if (ticket.voidedAt) continue;
    if (getLocalDateKey(ticket.createdAt) === localDate) {
      summary.todaySalesMillimes = sumMillimeAmounts([summary.todaySalesMillimes, ticket.totalMillimes]);
      summary.todayTicketCount += 1;
    }
    const remaining = Math.max(ticket.totalMillimes - (paidByTicket.get(ticket.id) ?? 0), 0);
    if (remaining > 0) {
      summary.outstandingBalanceMillimes = sumMillimeAmounts([summary.outstandingBalanceMillimes, remaining]);
      summary.openTicketCount += 1;
    }
  }
  return summary;
}

export function getDashboardSummary(date: Date = new Date()): DashboardSummary {
  return summariseDashboard(ticketRepository.listTickets(), paymentRepository.listPayments(), date);
}

export function getTodaySalesMillimes(date: Date = new Date()): number {
  return getDashboardSummary(date).todaySalesMillimes;
}

export function getOutstandingBalanceMillimes(): number {
  return getDashboardSummary().outstandingBalanceMillimes;
}
