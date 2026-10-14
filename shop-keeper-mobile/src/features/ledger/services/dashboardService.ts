import { paymentRepository } from './paymentRepository';
import { ticketRepository } from './ticketRepository';

export interface DashboardSummary {
  localDate: string;
  todaySalesMillimes: number;
  outstandingBalanceMillimes: number;
}

export function getLocalDateKey(input: string | Date): string {
  const date = typeof input === 'string' ? new Date(input) : input;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function getTodaySalesMillimes(date: Date = new Date()): number {
  const targetDate = getLocalDateKey(date);
  const tickets = ticketRepository.listTickets();
  return tickets
    .filter((ticket) => !ticket.voidedAt && getLocalDateKey(ticket.createdAt) === targetDate)
    .reduce((sum, ticket) => sum + ticket.totalMillimes, 0);
}

export function getOutstandingBalanceMillimes(): number {
  const tickets = ticketRepository.listTickets();
  const payments = paymentRepository.listPayments(undefined, true);

  return tickets
    .filter((ticket) => !ticket.voidedAt)
    .reduce((sum, ticket) => {
      const paid = payments
        .filter((payment) => payment.ticketId === ticket.id && !payment.voidedAt)
        .reduce((total, payment) => total + payment.amountMillimes, 0);
      return sum + Math.max(ticket.totalMillimes - paid, 0);
    }, 0);
}

export function getDashboardSummary(date: Date = new Date()): DashboardSummary {
  return {
    localDate: getLocalDateKey(date),
    todaySalesMillimes: getTodaySalesMillimes(date),
    outstandingBalanceMillimes: getOutstandingBalanceMillimes(),
  };
}
