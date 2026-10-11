import type * as SQLite from 'expo-sqlite';
import { getLedgerDatabase, withLedgerTransaction } from '@/db';
import { Payment, PaymentInput } from '../types/payment';
import { appendAuditEvent } from './auditService';
import { createPendingChange, persistPendingChange } from './pendingChangeService';
import { createPaymentRecord, validateTicketPayment } from './paymentService';
import { TicketRepository, ticketRepository } from './ticketRepository';

interface PaymentRow {
  id: string;
  ticket_id: string;
  customer_id: string;
  amount_millimes: number;
  payment_kind: Payment['paymentKind'];
  applied_at: string;
  updated_at: string;
  voided_at: string | null;
}

function mapPayment(row: PaymentRow): Payment {
  return {
    id: row.id,
    ticketId: row.ticket_id,
    customerId: row.customer_id,
    amountMillimes: row.amount_millimes,
    paymentKind: row.payment_kind,
    appliedAt: row.applied_at,
    updatedAt: row.updated_at,
    voidedAt: row.voided_at,
  };
}

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

  private writePayment(payment: Payment): void {
    this.database.runSync(
      `INSERT INTO payments
         (id, ticket_id, customer_id, amount_millimes, payment_kind, applied_at, updated_at, voided_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)
       ON CONFLICT(id) DO UPDATE SET ticket_id = excluded.ticket_id,
         customer_id = excluded.customer_id, amount_millimes = excluded.amount_millimes,
         payment_kind = excluded.payment_kind, applied_at = excluded.applied_at,
         updated_at = excluded.updated_at, voided_at = excluded.voided_at;`,
      payment.id,
      payment.ticketId,
      payment.customerId,
      payment.amountMillimes,
      payment.paymentKind,
      payment.appliedAt,
      payment.updatedAt,
      payment.voidedAt,
    );
  }

  private validate(input: PaymentInput, excludePaymentId?: string): void {
    const ticket = this.tickets.getTicket(input.ticketId);
    if (!ticket) {
      throw new Error('Ticket not found or voided.');
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

  createPayment(input: PaymentInput): Payment {
    return this.transaction((db) => {
      this.validate(input);
      const payment = createPaymentRecord(input);
      this.writePayment(payment);
      appendAuditEvent({ entityType: 'payment', entityId: payment.id, action: 'create', before: null, after: { ...payment } }, db);
      persistPendingChange(createPendingChange('payment', payment.id, 'create', { payment }), db);
      return payment;
    });
  }

  updatePayment(id: string, input: PaymentInput): Payment {
    return this.transaction((db) => {
      const before = this.getPayment(id);
      if (!before || before.voidedAt) {
        throw new Error('Active payment not found.');
      }
      this.validate(input, id);
      const after = {
        ...createPaymentRecord({ ...input, id, appliedAt: before.appliedAt }),
        updatedAt: new Date().toISOString(),
      };
      this.writePayment(after);
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
      const voidedAt = new Date().toISOString();
      const after = { ...before, voidedAt, updatedAt: voidedAt };
      this.writePayment(after);
      appendAuditEvent({ entityType: 'payment', entityId: id, action: 'void', before: { ...before }, after: { ...after } }, db);
      persistPendingChange(createPendingChange('payment', id, 'soft_delete', { before, after }), db);
      return after;
    });
  }
}

export const paymentRepository = new PaymentRepository();
