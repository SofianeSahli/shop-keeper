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
  todaySalesMillimes: number;
  todayTicketCount: number;
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

export async function getDashboardSummary(date: Date = new Date()): Promise<DashboardSummary> {
  const tickets = await ticketRepository.listTickets();
  const payments = await paymentRepository.listPayments();
  return summariseDashboard(tickets, payments, date);
}

export async function getTodaySalesMillimes(date: Date = new Date()): Promise<number> {
  return (await getDashboardSummary(date)).todaySalesMillimes;
}

export async function getOutstandingBalanceMillimes(): Promise<number> {
  return (await getDashboardSummary()).outstandingBalanceMillimes;
}
