import { describe, expect, it, vi } from 'vitest';
import { Payment } from '../types/payment';
import { Ticket } from '../types/ticket';
import { summariseDashboard } from './dashboardService';

vi.mock('./ticketRepository', () => ({ ticketRepository: {} }));
vi.mock('./paymentRepository', () => ({ paymentRepository: {} }));
vi.mock('@/lib/id', () => ({ createId: vi.fn(() => 'generated-id') }));

const local = (day: number, hour: number) => new Date(2026, 9, day, hour).toISOString();

const ticket = (id: string, totalMillimes: number, createdAt: string, voidedAt: string | null = null): Ticket => ({
  id,
  customerId: 'c',
  createdAt,
  updatedAt: createdAt,
  totalMillimes,
  status: 'UNPAID',
  voidedAt,
  lines: [],
});

const payment = (ticketId: string, amountMillimes: number, voidedAt: string | null = null): Payment => ({
  id: `p-${ticketId}-${amountMillimes}`,
  ticketId,
  customerId: 'c',
  amountMillimes,
  paymentKind: 'TICKET',
  appliedAt: local(24, 12),
  updatedAt: local(24, 12),
  voidedAt,
});

describe('summariseDashboard', () => {
  const now = new Date(2026, 9, 24, 18);
  const tickets = [
    ticket('paid-today', 2750, local(24, 9)),
    ticket('partial-today', 7900, local(24, 0)),
    ticket('unpaid-yesterday', 1000, local(23, 23)),
    ticket('voided-today', 5000, local(24, 10), local(24, 11)),
  ];
  const payments = [
    payment('paid-today', 2750),
    payment('partial-today', 3000),
    payment('unpaid-yesterday', 400, local(24, 8)),
    payment('voided-today', 5000),
  ];
  const summary = summariseDashboard(tickets, payments, now);

  it('counts every non-voided ticket of the local day as a sale, whatever its status', () => {
    expect(summary.localDate).toBe('2026-10-24');
    expect(summary.todaySalesMillimes).toBe(2750 + 7900);
    expect(summary.todayTicketCount).toBe(2);
  });

  it('sums only unpaid remainders of non-voided tickets as outstanding', () => {
    expect(summary.outstandingBalanceMillimes).toBe(4900 + 1000);
    expect(summary.openTicketCount).toBe(2);
  });

  it('returns zeros without data', () => {
    expect(summariseDashboard([], [], now)).toEqual({
      localDate: '2026-10-24',
      todaySalesMillimes: 0,
      todayTicketCount: 0,
      outstandingBalanceMillimes: 0,
      openTicketCount: 0,
    });
  });
});
