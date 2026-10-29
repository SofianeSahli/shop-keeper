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

export async function writePaymentRow(payment: Payment, db: SQLite.SQLiteDatabase): Promise<void> {
  await db.runAsync(
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

export async function insertPaymentRecords(payment: Payment, transactionDb: SQLite.SQLiteDatabase): Promise<Payment> {
  await writePaymentRow(payment, transactionDb);
  await appendAuditEvent({ entityType: 'payment', entityId: payment.id, action: 'create', before: null, after: { ...payment } }, transactionDb);
  await persistPendingChange(createPendingChange('payment', payment.id, 'create', { payment }), transactionDb);
  return payment;
}

export async function voidPaymentRecords(
  before: Payment,
  transactionDb: SQLite.SQLiteDatabase,
  voidedAt = new Date().toISOString(),
): Promise<Payment> {
  const after = { ...before, voidedAt, updatedAt: voidedAt };
  await writePaymentRow(after, transactionDb);
  await appendAuditEvent({ entityType: 'payment', entityId: before.id, action: 'void', before: { ...before }, after: { ...after } }, transactionDb);
  await persistPendingChange(createPendingChange('payment', before.id, 'soft_delete', { before, after }), transactionDb);
  return after;
}

export async function listActiveSalePayments(ticketId: string, transactionDb: SQLite.SQLiteDatabase): Promise<Payment[]> {
  const rows = await transactionDb.getAllAsync<PaymentRow>(
    `SELECT * FROM payments WHERE ticket_id = ? AND payment_kind = 'AT_SALE' AND voided_at IS NULL;`,
    ticketId,
  );
  return rows.map(mapPayment);
}
