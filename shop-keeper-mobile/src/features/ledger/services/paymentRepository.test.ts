import type * as SQLite from 'expo-sqlite';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PaymentRepository } from './paymentRepository';
import { TicketRepository } from './ticketRepository';
import { Ticket } from '../types/ticket';

const mocks = vi.hoisted(() => ({
  inTransaction: false,
  events: [] as string[],
  appendAuditEvent: vi.fn(() => {
    if (!mocks.inTransaction) throw new Error('Audit event was written outside transaction.');
    mocks.events.push('audit');
  }),
  createPendingChange: vi.fn(() => ({ id: 'pending-1' })),
  persistPendingChange: vi.fn(() => {
    if (!mocks.inTransaction) throw new Error('Pending change was written outside transaction.');
    mocks.events.push('pending');
  }),
}));

vi.mock('@/db', () => import('@/test/ledgerDbModule'));
vi.mock('./auditService', () => ({ appendAuditEvent: mocks.appendAuditEvent }));
vi.mock('./pendingChangeService', () => ({
  createPendingChange: mocks.createPendingChange,
  persistPendingChange: mocks.persistPendingChange,
}));
vi.mock('@/lib/id', () => ({ createId: vi.fn(() => 'payment-1') }));

const ticket: Ticket = {
  id: 'ticket-1',
  customerId: 'customer-1',
  createdAt: '2025-01-01T00:00:00.000Z',
  updatedAt: '2025-01-01T00:00:00.000Z',
  totalMillimes: 5000,
  status: 'UNPAID',
  voidedAt: null,
  lines: [],
};

describe('PaymentRepository.createPayment', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.events = [];
    mocks.inTransaction = false;
  });

  it('persists the payment, audit entry and pending record within one transaction', async () => {
    const db = {
      async withTransactionAsync(callback: () => Promise<void>) {
        mocks.inTransaction = true;
        try {
          await callback();
        } finally {
          mocks.inTransaction = false;
        }
      },
      async runAsync() {
        if (!mocks.inTransaction) throw new Error('Payment was written outside transaction.');
        mocks.events.push('payment');
      },
    } as unknown as SQLite.SQLiteDatabase;
    const tickets = {
      getTicket: vi.fn(async () => ticket),
      totalPaid: vi.fn(async () => 0),
    } as unknown as TicketRepository;
    Object.assign(tickets, { withinTransaction: () => tickets });
    const repository = new PaymentRepository(db, tickets);

    const payment = await repository.createPayment({
      ticketId: ticket.id,
      customerId: ticket.customerId,
      amountMillimes: 2000,
    });

    expect(payment.id).toBe('payment-1');
    expect(mocks.events).toEqual(['payment', 'audit', 'pending']);
    expect(mocks.appendAuditEvent).toHaveBeenCalledOnce();
    expect(mocks.persistPendingChange).toHaveBeenCalledOnce();
  });
});
