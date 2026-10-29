import type * as SQLite from 'expo-sqlite';
import { getLedgerDatabase, accessLedger, withLedgerTransaction } from '@/db';
import { isWalkInCustomer } from '../constants';
import { Payment, PaymentInput } from '../types/payment';
import { appendAuditEvent } from './auditService';
import { createPendingChange, persistPendingChange } from './pendingChangeService';
import { createPaymentRecord, validateTicketPayment } from './paymentService';
import { insertPaymentRecords, mapPayment, PaymentRow, voidPaymentRecords, writePaymentRow } from './paymentRecords';
import { TicketRepository, ticketRepository } from './ticketRepository';

export { insertPaymentRecords };

const ACTIVE_PAYMENT_NOT_FOUND = 'Active payment not found.';

async function findPayment(db: SQLite.SQLiteDatabase, id: string): Promise<Payment | null> {
  const row = await db.getFirstAsync<PaymentRow>('SELECT * FROM payments WHERE id = ?;', id);
  return row ? mapPayment(row) : null;
}

function assertNotWalkInSalePayment(payment: Payment): void {
  if (payment.paymentKind === 'AT_SALE' && isWalkInCustomer(payment.customerId)) {
    throw new Error('Void the walk-in ticket instead.');
  }
}

export class PaymentRepository {
  constructor(
    private readonly db?: SQLite.SQLiteDatabase,
    private readonly tickets: TicketRepository = ticketRepository,
  ) {}

  private get database(): SQLite.SQLiteDatabase {
    return this.db ?? getLedgerDatabase();
  }

  private transaction<T>(operation: (db: SQLite.SQLiteDatabase, tickets: TicketRepository) => Promise<T>): Promise<T> {
    return withLedgerTransaction((db) => operation(db, this.tickets.withinTransaction(db)), this.database);
  }

  async listPayments(customerId?: string, includeVoided = false): Promise<Payment[]> {
    const rows = await accessLedger(
      (db) =>
        db.getAllAsync<PaymentRow>(
          `SELECT * FROM payments
           WHERE (? IS NULL OR customer_id = ?)
             AND (? = 1 OR voided_at IS NULL)
           ORDER BY applied_at DESC;`,
          customerId ?? null,
          customerId ?? null,
          includeVoided ? 1 : 0,
        ),
      this.database,
    );
    return rows.map(mapPayment);
  }

  getPayment(id: string): Promise<Payment | null> {
    return accessLedger((db) => findPayment(db, id), this.database);
  }

  private async validate(input: PaymentInput, tickets: TicketRepository, excludePaymentId?: string): Promise<void> {
    const ticket = await tickets.getTicket(input.ticketId);
    if (!ticket) {
      throw new Error('Ticket not found or voided.');
    }
    if (isWalkInCustomer(ticket.customerId)) {
      throw new Error('Walk-in tickets cannot receive payments.');
    }
    if (ticket.customerId !== input.customerId) {
      throw new Error('Payment customer must match the selected ticket.');
    }
    const paid = await tickets.totalPaid(input.ticketId, excludePaymentId);
    const error = validateTicketPayment(ticket, input.amountMillimes, paid);
    if (error) {
      throw new Error(error);
    }
  }

  createPayment(input: PaymentInput): Promise<Payment> {
    return this.transaction(async (db, tickets) => {
      await this.validate(input, tickets);
      return insertPaymentRecords(createPaymentRecord(input), db);
    });
  }

  updatePayment(id: string, input: PaymentInput): Promise<Payment> {
    return this.transaction(async (db, tickets) => {
      const before = await findPayment(db, id);
      if (!before || before.voidedAt) {
        throw new Error(ACTIVE_PAYMENT_NOT_FOUND);
      }
      assertNotWalkInSalePayment(before);
      await this.validate(input, tickets, id);
      const after = {
        ...createPaymentRecord({ ...input, id, appliedAt: before.appliedAt, paymentKind: before.paymentKind }),
        updatedAt: new Date().toISOString(),
      };
      await writePaymentRow(after, db);
      await appendAuditEvent({ entityType: 'payment', entityId: id, action: 'update', before: { ...before }, after: { ...after } }, db);
      await persistPendingChange(createPendingChange('payment', id, 'update', { before, after }), db);
      return after;
    });
  }

  voidPayment(id: string): Promise<Payment> {
    return this.transaction(async (db) => {
      const before = await findPayment(db, id);
      if (!before || before.voidedAt) {
        throw new Error(ACTIVE_PAYMENT_NOT_FOUND);
      }
      assertNotWalkInSalePayment(before);
      return voidPaymentRecords(before, db);
    });
  }
}

export const paymentRepository = new PaymentRepository();
