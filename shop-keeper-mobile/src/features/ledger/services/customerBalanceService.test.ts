import { describe, expect, it, vi } from 'vitest';
import { Customer } from '../types/customer';
import { Payment } from '../types/payment';
import { Ticket } from '../types/ticket';
import { WALK_IN_CUSTOMER_ID } from '../constants';
import { buildCustomerLedgerEntries, getCustomerFilterCounts, summariseDebtors } from './customerBalanceService';

vi.mock('@/lib/id', () => ({ createId: vi.fn(() => 'generated-id') }));

const customer = (id: string, displayLabel: string, phoneNumber: string | null = null): Customer => ({
  id,
  internalCode: id,
  displayLabel,
  realName: null,
  phoneNumber,
  isActive: true,
  createdAt: '2026-10-01T08:00:00.000Z',
  updatedAt: '2026-10-01T08:00:00.000Z',
});

const ticket = (id: string, customerId: string, totalMillimes: number, createdAt: string, voidedAt: string | null = null): Ticket => ({
  id,
  customerId,
  createdAt,
  updatedAt: createdAt,
  totalMillimes,
  status: 'UNPAID',
  voidedAt,
  lines: [],
});

const payment = (id: string, ticketId: string, customerId: string, amountMillimes: number, appliedAt: string, voidedAt: string | null = null): Payment => ({
  id,
  ticketId,
  customerId,
  amountMillimes,
  paymentKind: 'TICKET',
  appliedAt,
  updatedAt: appliedAt,
  voidedAt,
});

describe('summariseDebtors', () => {
  const customers = [
    customer('ali', 'Ali', '20123456'),
    customer('sonia', 'Sonia'),
    customer('hedi', 'Hédi'),
    customer('amel', 'amel'),
    customer(WALK_IN_CUSTOMER_ID, 'walk-in'),
  ];
  const tickets = [
    ticket('t1', 'ali', 7900, '2026-10-20T10:00:00.000Z'),
    ticket('t2', 'ali', 5000, '2026-10-22T10:00:00.000Z'),
    ticket('t3', 'sonia', 1000, '2026-10-23T10:00:00.000Z'),
    ticket('t4', 'hedi', 3000, '2026-10-18T10:00:00.000Z'),
    ticket('t5', 'sonia', 9000, '2026-10-10T10:00:00.000Z', '2026-10-11T10:00:00.000Z'),
    ticket('t6', WALK_IN_CUSTOMER_ID, 2750, '2026-10-24T10:00:00.000Z'),
  ];
  const payments = [
    payment('p1', 't1', 'ali', 3000, '2026-10-21T10:00:00.000Z'),
    payment('p2', 't4', 'hedi', 3000, '2026-10-19T10:00:00.000Z'),
    payment('p3', 't2', 'ali', 5000, '2026-10-23T09:00:00.000Z', '2026-10-23T09:30:00.000Z'),
    payment('p4', 't6', WALK_IN_CUSTOMER_ID, 2750, '2026-10-24T10:00:00.000Z'),
  ];
  const summaries = summariseDebtors(customers, tickets, payments);

  it('computes balance, open tickets, oldest open ticket and last payment', () => {
    expect(summaries[0]).toEqual({
      customerId: 'ali',
      displayLabel: 'Ali',
      phoneNumber: '20123456',
      balanceMillimes: 4900 + 5000,
      openTicketCount: 2,
      oldestOpenTicketAt: '2026-10-20T10:00:00.000Z',
      lastPaymentAt: '2026-10-21T10:00:00.000Z',
    });
  });

  it('sorts by balance, highest first, then by label', () => {
    expect(summaries.map((summary) => summary.customerId)).toEqual(['ali', 'sonia', 'amel', 'hedi']);
  });

  it('ignores voided tickets and voided payments', () => {
    const sonia = summaries.find((summary) => summary.customerId === 'sonia');
    expect(sonia?.balanceMillimes).toBe(1000);
    expect(sonia?.openTicketCount).toBe(1);
  });

  it('reports fully paid customers with zero balance and no open ticket', () => {
    expect(summaries.find((summary) => summary.customerId === 'hedi')).toMatchObject({
      balanceMillimes: 0,
      openTicketCount: 0,
      oldestOpenTicketAt: null,
      lastPaymentAt: '2026-10-19T10:00:00.000Z',
    });
  });

  it('never includes the walk-in customer', () => {
    expect(summaries.some((summary) => summary.customerId === WALK_IN_CUSTOMER_ID)).toBe(false);
  });

  it('counts customers per filter', () => {
    expect(getCustomerFilterCounts(summaries)).toEqual({ owes: 2, upToDate: 2, all: 4 });
  });
});

describe('buildCustomerLedgerEntries', () => {
  const tickets = [
    ticket('t1', 'ali', 7900, '2026-10-20T10:00:00.000Z'),
    ticket('t2', 'ali', 5000, '2026-10-22T10:00:00.000Z'),
    ticket('t3', 'ali', 1000, '2026-10-23T10:00:00.000Z', '2026-10-23T11:00:00.000Z'),
    ticket('t4', 'sonia', 1000, '2026-10-24T10:00:00.000Z'),
  ];
  const payments = [
    { ...payment('p1', 't1', 'ali', 3000, '2026-10-21T10:00:00.000Z'), paymentKind: 'TICKET' as const },
    { ...payment('p2', 't2', 'ali', 5000, '2026-10-22T10:00:00.000Z'), paymentKind: 'AT_SALE' as const },
    payment('p3', 't1', 'ali', 100, '2026-10-24T09:00:00.000Z', '2026-10-24T09:30:00.000Z'),
  ];
  const entries = buildCustomerLedgerEntries('ali', tickets, payments);

  it('lists the customer tickets and payments newest first, payment before its ticket at the same instant', () => {
    expect(entries.map((entry) => entry.id)).toEqual(['p2', 't2', 'p1', 't1']);
  });

  it('gives each ticket its remaining amount and each payment its ticket date and kind', () => {
    expect(entries.find((entry) => entry.id === 't1')).toMatchObject({ type: 'ticket', remainingMillimes: 4900 });
    expect(entries.find((entry) => entry.id === 't2')).toMatchObject({ type: 'ticket', remainingMillimes: 0 });
    expect(entries.find((entry) => entry.id === 'p1')).toMatchObject({
      type: 'payment',
      ticketCreatedAt: '2026-10-20T10:00:00.000Z',
    });
    expect(entries.find((entry) => entry.id === 'p2')).toMatchObject({ type: 'payment', payment: { paymentKind: 'AT_SALE' } });
  });

  it('leaves out voided entries and other customers', () => {
    expect(entries.some((entry) => ['t3', 'p3', 't4'].includes(entry.id))).toBe(false);
  });
});
