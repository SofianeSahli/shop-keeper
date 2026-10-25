import { randomUUID } from 'node:crypto';
import type * as SQLite from 'expo-sqlite';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { countRows, createTestLedgerDb, insertTestCustomer } from '@/test/ledgerTestDb';
import { ensureWalkInCustomer } from '@/db/migrations';
import { WALK_IN_CUSTOMER_ID } from '../constants';
import { LedgerRepository } from './ledgerRepository';
import { PaymentRepository } from './paymentRepository';
import { recordSale } from './saleService';
import { buildTicketFromDraft, updateTicketFromDraft } from './ticketService';
import { TicketRepository } from './ticketRepository';
import { listCustomers, removeCustomer, updateCustomer } from './customerService';

vi.mock('@/db', () => ({
  getLedgerDatabase: () => {
    throw new Error('Tests pass their database explicitly.');
  },
  withLedgerTransaction: <T>(operation: (db: SQLite.SQLiteDatabase) => T, db: SQLite.SQLiteDatabase): T => {
    let result!: T;
    db.withTransactionSync(() => {
      result = operation(db);
    });
    return result;
  },
}));
vi.mock('@/lib/id', () => ({ createId: () => randomUUID() }));

const line = (unitPriceMillimes: number, quantity = 1) => ({
  id: randomUUID(),
  productId: null,
  productName: 'Lait',
  barcode: null,
  unitPriceMillimes,
  quantity,
});

describe('walk-in customer and at-sale payments', () => {
  let db: SQLite.SQLiteDatabase;
  let tickets: TicketRepository;
  let payments: PaymentRepository;

  beforeEach(() => {
    db = createTestLedgerDb();
    ensureWalkInCustomer(db);
    insertTestCustomer(db, 'ali', 'Ali');
    tickets = new TicketRepository(db);
    payments = new PaymentRepository(db, tickets);
  });

  const walkInSale = () => recordSale(buildTicketFromDraft(WALK_IN_CUSTOMER_ID, [line(1250, 2)]), { kind: 'PAID' }, tickets);

  describe('voidTicket cascade (FR-020)', () => {
    it('voids the at-sale payment with the ticket, logging each void', () => {
      const { ticket, payment } = walkInSale();
      const pendingBefore = countRows(db, 'pending_changes');

      tickets.voidTicket(ticket.id);

      expect(payments.getPayment(payment!.id)?.voidedAt).not.toBeNull();
      expect(countRows(db, 'pending_changes') - pendingBefore).toBe(2);
      expect(countRows(db, 'pending_changes', "entity_type = 'payment' AND operation = 'soft_delete'")).toBe(1);
      expect(countRows(db, 'ledger_audit_events', "entity_type = 'payment' AND action = 'void'")).toBe(1);
    });

    it('keeps later debt-collection payments untouched', () => {
      const { ticket } = recordSale(buildTicketFromDraft('ali', [line(7900)]), { kind: 'PARTIAL', amountMillimes: 3000 }, tickets);
      const later = payments.createPayment({ ticketId: ticket.id, customerId: 'ali', amountMillimes: 1000 });

      tickets.voidTicket(ticket.id);

      expect(payments.getPayment(later.id)?.voidedAt).toBeNull();
      expect(countRows(db, 'payments', "payment_kind = 'AT_SALE' AND voided_at IS NOT NULL")).toBe(1);
    });
  });

  describe('walk-in guards (research R18)', () => {
    it('refuses a plain unpaid ticket for the walk-in customer', () => {
      expect(() => tickets.createTicket(buildTicketFromDraft(WALK_IN_CUSTOMER_ID, [line(1000)]))).toThrow(/walk-in/i);
    });

    it('refuses later payments on a walk-in ticket', () => {
      const { ticket } = walkInSale();
      expect(() => payments.createPayment({ ticketId: ticket.id, customerId: WALK_IN_CUSTOMER_ID, amountMillimes: 100 })).toThrow(
        /walk-in/i,
      );
    });

    it('refuses to edit or void the at-sale payment of a walk-in ticket', () => {
      const { ticket, payment } = walkInSale();
      expect(() => payments.voidPayment(payment!.id)).toThrow(/walk-in/i);
      expect(() =>
        payments.updatePayment(payment!.id, { ticketId: ticket.id, customerId: WALK_IN_CUSTOMER_ID, amountMillimes: 100 }),
      ).toThrow(/walk-in/i);
    });

    it('refuses a walk-in ticket edit that would leave money owed', () => {
      const { ticket } = walkInSale();
      const existing = tickets.getTicket(ticket.id)!;
      expect(() =>
        tickets.updateTicket(ticket.id, updateTicketFromDraft(existing, WALK_IN_CUSTOMER_ID, [line(1250, 3)])),
      ).toThrow(/walk-in/i);
    });

    it('refuses moving a ticket to the walk-in customer', () => {
      const { ticket } = recordSale(buildTicketFromDraft('ali', [line(1000)]), { kind: 'UNPAID' }, tickets);
      const existing = tickets.getTicket(ticket.id)!;
      expect(() => tickets.updateTicket(ticket.id, updateTicketFromDraft(existing, WALK_IN_CUSTOMER_ID, [line(1000)]))).toThrow(
        /walk-in/i,
      );
    });

    it('keeps the at-sale kind when a named customer corrects the payment', () => {
      const { ticket, payment } = recordSale(buildTicketFromDraft('ali', [line(7900)]), { kind: 'PARTIAL', amountMillimes: 3000 }, tickets);
      const updated = payments.updatePayment(payment!.id, { ticketId: ticket.id, customerId: 'ali', amountMillimes: 2000 });
      expect(updated.paymentKind).toBe('AT_SALE');
    });

    it('locks the walk-in customer and hides it from customer lists', () => {
      const customers = new LedgerRepository(db);
      expect(() => updateCustomer(WALK_IN_CUSTOMER_ID, { displayLabel: 'X' }, customers)).toThrow(/walk-in/i);
      expect(() => removeCustomer(WALK_IN_CUSTOMER_ID, customers)).toThrow(/walk-in/i);
      expect(listCustomers('', true, customers).map((customer) => customer.id)).toEqual(['ali']);
      expect(listCustomers('', true, customers, { includeWalkIn: true }).map((customer) => customer.id)).toContain(
        WALK_IN_CUSTOMER_ID,
      );
    });
  });
});
