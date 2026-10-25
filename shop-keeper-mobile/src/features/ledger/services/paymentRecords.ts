import type * as SQLite from 'expo-sqlite';
import { Payment } from '../types/payment';
import { appendAuditEvent } from './auditService';
import { createPendingChange, persistPendingChange } from './pendingChangeService';

export interface PaymentRow {
  id: string;
  ticket_id: string;
  customer_id: string;
  amount_millimes: number;
  payment_kind: Payment['paymentKind'];
  applied_at: string;
  updated_at: string;
  voided_at: string | null;
}

export function mapPayment(row: PaymentRow): Payment {
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

export function writePaymentRow(payment: Payment, db: SQLite.SQLiteDatabase): void {
  db.runSync(
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

/** Writes a new payment with its audit event and operation-log entry. Call inside a transaction. */
export function insertPaymentRecords(payment: Payment, db: SQLite.SQLiteDatabase): Payment {
  writePaymentRow(payment, db);
  appendAuditEvent({ entityType: 'payment', entityId: payment.id, action: 'create', before: null, after: { ...payment } }, db);
  persistPendingChange(createPendingChange('payment', payment.id, 'create', { payment }), db);
  return payment;
}

/** Voids a payment with its audit event and operation-log entry. Call inside a transaction. */
export function voidPaymentRecords(before: Payment, db: SQLite.SQLiteDatabase, voidedAt = new Date().toISOString()): Payment {
  const after = { ...before, voidedAt, updatedAt: voidedAt };
  writePaymentRow(after, db);
  appendAuditEvent({ entityType: 'payment', entityId: before.id, action: 'void', before: { ...before }, after: { ...after } }, db);
  persistPendingChange(createPendingChange('payment', before.id, 'soft_delete', { before, after }), db);
  return after;
}

export function listActiveSalePayments(ticketId: string, db: SQLite.SQLiteDatabase): Payment[] {
  return db
    .getAllSync<PaymentRow>(
      `SELECT * FROM payments WHERE ticket_id = ? AND payment_kind = 'AT_SALE' AND voided_at IS NULL;`,
      ticketId,
    )
    .map(mapPayment);
}
