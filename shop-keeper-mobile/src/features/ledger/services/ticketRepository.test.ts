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

vi.mock('@/db', () => import('@/test/ledgerDbModule'));
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

  beforeEach(async () => {
    db = createTestLedgerDb();
    await ensureWalkInCustomer(db);
    insertTestCustomer(db, 'ali', 'Ali');
    tickets = new TicketRepository(db);
    payments = new PaymentRepository(db, tickets);
  });

  const walkInSale = () => recordSale(buildTicketFromDraft(WALK_IN_CUSTOMER_ID, [line(1250, 2)]), { kind: 'PAID' }, tickets);

  describe('voidTicket cascade (FR-020)', () => {
    it('voids the at-sale payment with the ticket, logging each void', async () => {
      const { ticket, payment } = await walkInSale();
      const pendingBefore = countRows(db, 'pending_changes');

      await tickets.voidTicket(ticket.id);

      expect((await payments.getPayment(payment!.id))?.voidedAt).not.toBeNull();
      expect(countRows(db, 'pending_changes') - pendingBefore).toBe(2);
      expect(countRows(db, 'pending_changes', "entity_type = 'payment' AND operation = 'soft_delete'")).toBe(1);
      expect(countRows(db, 'ledger_audit_events', "entity_type = 'payment' AND action = 'void'")).toBe(1);
    });

    it('keeps later debt-collection payments untouched', async () => {
      const { ticket } = await recordSale(
        buildTicketFromDraft('ali', [line(7900)]),
        { kind: 'PARTIAL', amountMillimes: 3000 },
        tickets,
      );
      const later = await payments.createPayment({ ticketId: ticket.id, customerId: 'ali', amountMillimes: 1000 });

      await tickets.voidTicket(ticket.id);

      expect((await payments.getPayment(later.id))?.voidedAt).toBeNull();
      expect(countRows(db, 'payments', "payment_kind = 'AT_SALE' AND voided_at IS NOT NULL")).toBe(1);
    });
  });

  describe('walk-in guards (research R18)', () => {
    it('refuses a plain unpaid ticket for the walk-in customer', async () => {
      await expect(tickets.createTicket(buildTicketFromDraft(WALK_IN_CUSTOMER_ID, [line(1000)]))).rejects.toThrow(/walk-in/i);
    });

    it('refuses later payments on a walk-in ticket', async () => {
      const { ticket } = await walkInSale();
      await expect(
        payments.createPayment({ ticketId: ticket.id, customerId: WALK_IN_CUSTOMER_ID, amountMillimes: 100 }),
      ).rejects.toThrow(/walk-in/i);
    });

    it('refuses to edit or void the at-sale payment of a walk-in ticket', async () => {
      const { ticket, payment } = await walkInSale();
      await expect(payments.voidPayment(payment!.id)).rejects.toThrow(/walk-in/i);
      await expect(
        payments.updatePayment(payment!.id, { ticketId: ticket.id, customerId: WALK_IN_CUSTOMER_ID, amountMillimes: 100 }),
      ).rejects.toThrow(/walk-in/i);
    });

    it('refuses a walk-in ticket edit that would leave money owed', async () => {
      const { ticket } = await walkInSale();
      const existing = (await tickets.getTicket(ticket.id))!;
      await expect(
        tickets.updateTicket(ticket.id, updateTicketFromDraft(existing, WALK_IN_CUSTOMER_ID, [line(1250, 3)])),
      ).rejects.toThrow(/walk-in/i);
    });

    it('refuses moving a ticket to the walk-in customer', async () => {
      const { ticket } = await recordSale(buildTicketFromDraft('ali', [line(1000)]), { kind: 'UNPAID' }, tickets);
      const existing = (await tickets.getTicket(ticket.id))!;
      await expect(
        tickets.updateTicket(ticket.id, updateTicketFromDraft(existing, WALK_IN_CUSTOMER_ID, [line(1000)])),
      ).rejects.toThrow(/walk-in/i);
    });

    it('keeps the at-sale kind when a named customer corrects the payment', async () => {
      const { ticket, payment } = await recordSale(
        buildTicketFromDraft('ali', [line(7900)]),
        { kind: 'PARTIAL', amountMillimes: 3000 },
        tickets,
      );
      const updated = await payments.updatePayment(payment!.id, { ticketId: ticket.id, customerId: 'ali', amountMillimes: 2000 });
      expect(updated.paymentKind).toBe('AT_SALE');
    });

    it('locks the walk-in customer and hides it from customer lists', async () => {
      const customers = new LedgerRepository(db);
      await expect(updateCustomer(WALK_IN_CUSTOMER_ID, { displayLabel: 'X' }, customers)).rejects.toThrow(/walk-in/i);
      await expect(removeCustomer(WALK_IN_CUSTOMER_ID, customers)).rejects.toThrow(/walk-in/i);
      expect((await listCustomers('', true, customers)).map((customer) => customer.id)).toEqual(['ali']);
      expect((await listCustomers('', true, customers, { includeWalkIn: true })).map((customer) => customer.id)).toContain(
        WALK_IN_CUSTOMER_ID,
      );
    });
  });
});
