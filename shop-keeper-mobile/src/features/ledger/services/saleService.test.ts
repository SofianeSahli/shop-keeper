import { randomUUID } from 'node:crypto';
import type * as SQLite from 'expo-sqlite';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { countRows, createTestLedgerDb, insertTestCustomer } from '@/test/ledgerTestDb';
import { ensureWalkInCustomer } from '@/db/migrations';
import { WALK_IN_CUSTOMER_ID } from '../constants';
import { SalePaymentChoice } from '../types/sale';
import { getSalePaymentAmount, getSaleSaveLabel, recordSale, validateSaleChoice } from './saleService';
import { buildTicketFromDraft } from './ticketService';
import { TicketRepository } from './ticketRepository';
import * as paymentRecords from './paymentRecords';

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

const PAID: SalePaymentChoice = { kind: 'PAID' };
const UNPAID: SalePaymentChoice = { kind: 'UNPAID' };
const partial = (amountMillimes: number | null): SalePaymentChoice => ({ kind: 'PARTIAL', amountMillimes });

describe('validateSaleChoice', () => {
  it('accepts a paid sale with or without a customer', () => {
    expect(validateSaleChoice(PAID, 7900, null)).toBeNull();
    expect(validateSaleChoice(PAID, 7900, WALK_IN_CUSTOMER_ID)).toBeNull();
    expect(validateSaleChoice(PAID, 7900, 'ali')).toBeNull();
  });

  it('requires a real customer for credit', () => {
    expect(validateSaleChoice(UNPAID, 7900, null)).toBe('kridiNeedsCustomer');
    expect(validateSaleChoice(UNPAID, 7900, WALK_IN_CUSTOMER_ID)).toBe('kridiNeedsCustomer');
    expect(validateSaleChoice(partial(3000), 7900, '')).toBe('kridiNeedsCustomer');
    expect(validateSaleChoice(UNPAID, 7900, 'ali')).toBeNull();
    expect(validateSaleChoice(partial(3000), 7900, 'ali')).toBeNull();
  });

  it.each([null, 0, -5, 1.5, 8000])('rejects the partial amount %s', (amount) => {
    expect(validateSaleChoice(partial(amount), 7900, 'ali')).toBe('partialAmountInvalid');
  });

  it('suggests "Paid" when the partial amount equals the total', () => {
    expect(validateSaleChoice(partial(7900), 7900, 'ali')).toBe('partialEqualsTotal');
  });
});

describe('sale amounts and labels', () => {
  it('derives the amount received at the counter', () => {
    expect(getSalePaymentAmount(PAID, 7900)).toBe(7900);
    expect(getSalePaymentAmount(partial(3000), 7900)).toBe(3000);
    expect(getSalePaymentAmount(partial(null), 7900)).toBe(0);
    expect(getSalePaymentAmount(UNPAID, 7900)).toBe(0);
  });

  it('builds the save label for each choice', () => {
    expect(getSaleSaveLabel(PAID, 2750)).toEqual({ key: 'collectAmount', params: { amount: 2750 } });
    expect(getSaleSaveLabel(partial(3000), 7900)).toEqual({
      key: 'collectWithRemainder',
      params: { paid: 3000, rest: 4900 },
    });
    expect(getSaleSaveLabel(UNPAID, 1000)).toEqual({ key: 'putOnKridi', params: { amount: 1000 } });
  });
});

describe('recordSale', () => {
  let db: SQLite.SQLiteDatabase;
  let tickets: TicketRepository;

  const draft = (unitPriceMillimes: number, quantity = 1) => [
    { id: randomUUID(), productId: null, productName: 'Huile', barcode: null, unitPriceMillimes, quantity },
  ];
  const pending = (entityType: string) => countRows(db, 'pending_changes', "entity_type = ? AND status = 'pending'", entityType);

  beforeEach(() => {
    db = createTestLedgerDb();
    ensureWalkInCustomer(db);
    insertTestCustomer(db, 'ali', 'Ali');
    tickets = new TicketRepository(db);
  });

  it('saves a paid walk-in sale with an AT_SALE payment dated at the ticket', () => {
    const ticket = buildTicketFromDraft(WALK_IN_CUSTOMER_ID, draft(1250, 2));
    const result = recordSale(ticket, PAID, tickets);

    expect(result.ticket.status).toBe('PAID');
    expect(result.payment).toMatchObject({
      amountMillimes: 2500,
      paymentKind: 'AT_SALE',
      appliedAt: ticket.createdAt,
      customerId: WALK_IN_CUSTOMER_ID,
    });
    expect(countRows(db, 'tickets')).toBe(1);
    expect(countRows(db, 'payments', "payment_kind = 'AT_SALE'")).toBe(1);
  });

  it('logs every write for the future sync and the audit trail (FR-021, SC-009)', () => {
    recordSale(buildTicketFromDraft('ali', draft(7900)), partial(3000), tickets);
    expect(pending('ticket')).toBe(1);
    expect(pending('payment')).toBe(1);
    expect(countRows(db, 'ledger_audit_events', "action = 'create'")).toBe(2);
  });

  it('leaves a partial sale partially paid with the remainder owed', () => {
    const result = recordSale(buildTicketFromDraft('ali', draft(7900)), partial(3000), tickets);
    expect(result.ticket.status).toBe('PARTIALLY_PAID');
    expect(tickets.totalPaid(result.ticket.id)).toBe(3000);
  });

  it('creates no payment for kridi', () => {
    const result = recordSale(buildTicketFromDraft('ali', draft(1000)), UNPAID, tickets);
    expect(result.ticket.status).toBe('UNPAID');
    expect(result.payment).toBeNull();
    expect(countRows(db, 'payments')).toBe(0);
    expect(pending('payment')).toBe(0);
  });

  it('refuses credit for the walk-in customer and writes nothing', () => {
    expect(() => recordSale(buildTicketFromDraft(WALK_IN_CUSTOMER_ID, draft(1000)), UNPAID, tickets)).toThrow(
      /credit needs a customer/i,
    );
    expect(countRows(db, 'tickets')).toBe(0);
  });

  it('keeps nothing when the payment write fails (US8-AS6)', () => {
    const spy = vi.spyOn(paymentRecords, 'insertPaymentRecords').mockImplementation(() => {
      throw new Error('disk full');
    });
    expect(() => recordSale(buildTicketFromDraft('ali', draft(7900)), PAID, tickets)).toThrow('disk full');
    spy.mockRestore();
    expect(countRows(db, 'tickets')).toBe(0);
    expect(countRows(db, 'ticket_lines')).toBe(0);
    expect(countRows(db, 'pending_changes', "entity_type = 'ticket'")).toBe(0);
    expect(countRows(db, 'ledger_audit_events')).toBe(0);
  });
});
