import type * as SQLite from 'expo-sqlite';
import { getLedgerDatabase, withLedgerTransaction } from '@/db';
import { isWalkInCustomer } from '../constants';
import { Payment, PaymentInput } from '../types/payment';
import { appendAuditEvent } from './auditService';
import { createPendingChange, persistPendingChange } from './pendingChangeService';
import { createPaymentRecord, validateTicketPayment } from './paymentService';
import { insertPaymentRecords, mapPayment, PaymentRow, voidPaymentRecords, writePaymentRow } from './paymentRecords';
import { TicketRepository, ticketRepository } from './ticketRepository';

export { insertPaymentRecords };

export class PaymentRepository {
  constructor(
    private readonly db?: SQLite.SQLiteDatabase,
    private readonly tickets: TicketRepository = ticketRepository,
  ) {}

  private get database(): SQLite.SQLiteDatabase {
    return this.db ?? getLedgerDatabase();
  }

  private transaction<T>(operation: (db: SQLite.SQLiteDatabase) => T): T {
    return withLedgerTransaction(operation, this.database);
  }

  listPayments(customerId?: string, includeVoided = false): Payment[] {
    const rows = this.database.getAllSync<PaymentRow>(
      `SELECT * FROM payments
       WHERE (? IS NULL OR customer_id = ?)
         AND (? = 1 OR voided_at IS NULL)
       ORDER BY applied_at DESC;`,
      customerId ?? null,
      customerId ?? null,
      includeVoided ? 1 : 0,
    );
    return rows.map(mapPayment);
  }

  getPayment(id: string): Payment | null {
    const row = this.database.getFirstSync<PaymentRow>('SELECT * FROM payments WHERE id = ?;', id);
    return row ? mapPayment(row) : null;
  }

  private validate(input: PaymentInput, excludePaymentId?: string): void {
    const ticket = this.tickets.getTicket(input.ticketId);
    if (!ticket) {
      throw new Error('Ticket not found or voided.');
    }
    if (isWalkInCustomer(ticket.customerId)) {
      throw new Error('Walk-in tickets cannot receive payments.');
    }
    if (ticket.customerId !== input.customerId) {
      throw new Error('Payment customer must match the selected ticket.');
    }
    const paid = this.tickets.totalPaid(input.ticketId, excludePaymentId);
    const error = validateTicketPayment(ticket, input.amountMillimes, paid);
    if (error) {
      throw new Error(error);
    }
  }

  /** The at-sale payment of a walk-in ticket only changes with its ticket (spec 003, research R18). */
  private assertNotWalkInSalePayment(payment: Payment): void {
    if (payment.paymentKind === 'AT_SALE' && isWalkInCustomer(payment.customerId)) {
      throw new Error('Void the walk-in ticket instead.');
    }
  }

  createPayment(input: PaymentInput): Payment {
    return this.transaction((db) => {
      this.validate(input);
      return insertPaymentRecords(createPaymentRecord(input), db);
    });
  }

  updatePayment(id: string, input: PaymentInput): Payment {
    return this.transaction((db) => {
      const before = this.getPayment(id);
      if (!before || before.voidedAt) {
        throw new Error('Active payment not found.');
      }
      this.assertNotWalkInSalePayment(before);
      this.validate(input, id);
      const after = {
        ...createPaymentRecord({ ...input, id, appliedAt: before.appliedAt, paymentKind: before.paymentKind }),
        updatedAt: new Date().toISOString(),
      };
      writePaymentRow(after, db);
      appendAuditEvent({ entityType: 'payment', entityId: id, action: 'update', before: { ...before }, after: { ...after } }, db);
      persistPendingChange(createPendingChange('payment', id, 'update', { before, after }), db);
      return after;
    });
  }

  voidPayment(id: string): Payment {
    return this.transaction((db) => {
      const before = this.getPayment(id);
      if (!before || before.voidedAt) {
        throw new Error('Active payment not found.');
      }
      this.assertNotWalkInSalePayment(before);
      return voidPaymentRecords(before, db);
    });
  }
}

export const paymentRepository = new PaymentRepository();
