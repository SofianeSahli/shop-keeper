import type * as SQLite from 'expo-sqlite';
import { getLedgerDatabase, withLedgerTransaction } from '@/db';
import { Ticket, TicketLine } from '../types/ticket';
import { sumMillimeAmounts } from '@/lib/money';
import { appendAuditEvent } from './auditService';
import { createPendingChange, persistPendingChange } from './pendingChangeService';
import { getTicketStatus } from './ticketService';
import { listActiveSalePayments, voidPaymentRecords } from './paymentRecords';
import { isWalkInCustomer } from '../constants';

interface TicketRow {
  id: string;
  customer_id: string;
  created_at: string;
  updated_at: string;
  total_millimes: number;
  status: Ticket['status'];
  voided_at: string | null;
}

interface TicketLineRow {
  id: string;
  ticket_id: string;
  product_id: string | null;
  product_name_snapshot: string;
  barcode_snapshot: string | null;
  unit_price_millimes: number;
  quantity: number;
  line_total_millimes: number;
}

function mapLine(row: TicketLineRow): TicketLine {
  return {
    id: row.id,
    ticketId: row.ticket_id,
    productId: row.product_id,
    productNameSnapshot: row.product_name_snapshot,
    barcodeSnapshot: row.barcode_snapshot,
    unitPriceMillimes: row.unit_price_millimes,
    quantity: row.quantity,
    lineTotalMillimes: row.line_total_millimes,
  };
}

export class TicketRepository {
  constructor(private readonly db?: SQLite.SQLiteDatabase) {}

  private get database(): SQLite.SQLiteDatabase {
    return this.db ?? getLedgerDatabase();
  }

  transaction<T>(operation: (db: SQLite.SQLiteDatabase) => T): T {
    return withLedgerTransaction(operation, this.database);
  }

  private mapTicket(row: TicketRow): Ticket {
    const paid = this.database.getFirstSync<{ amount: number }>(
      'SELECT COALESCE(SUM(amount_millimes), 0) AS amount FROM payments WHERE ticket_id = ? AND voided_at IS NULL;',
      row.id,
    )?.amount ?? 0;
    const lines = this.database.getAllSync<TicketLineRow>(
      'SELECT * FROM ticket_lines WHERE ticket_id = ? ORDER BY rowid;',
      row.id,
    );
    return {
      id: row.id,
      customerId: row.customer_id,
      createdAt: row.created_at,
      updatedAt: row.updated_at,
      totalMillimes: row.total_millimes,
      status: getTicketStatus(paid, row.total_millimes),
      voidedAt: row.voided_at,
      lines: lines.map(mapLine),
    };
  }

  listTickets(customerId?: string, includeVoided = false): Ticket[] {
    const rows = this.database.getAllSync<TicketRow>(
      `SELECT * FROM tickets
       WHERE (? IS NULL OR customer_id = ?)
         AND (? = 1 OR voided_at IS NULL)
       ORDER BY created_at DESC;`,
      customerId ?? null,
      customerId ?? null,
      includeVoided ? 1 : 0,
    );
    return rows.map((row) => this.mapTicket(row));
  }

  getTicket(id: string, includeVoided = true): Ticket | null {
    const row = this.database.getFirstSync<TicketRow>(
      `SELECT * FROM tickets WHERE id = ? AND (? = 1 OR voided_at IS NULL);`,
      id,
      includeVoided ? 1 : 0,
    );
    return row ? this.mapTicket(row) : null;
  }

  totalPaid(ticketId: string, excludePaymentId?: string): number {
    return this.database.getFirstSync<{ amount: number }>(
      `SELECT COALESCE(SUM(amount_millimes), 0) AS amount FROM payments
       WHERE ticket_id = ? AND voided_at IS NULL AND (? IS NULL OR id <> ?);`,
      ticketId,
      excludePaymentId ?? null,
      excludePaymentId ?? null,
    )?.amount ?? 0;
  }

  saveTicket(ticket: Ticket): void {
    if (ticket.lines.length === 0) {
      throw new Error('A ticket must contain at least one line.');
    }
    const lineTotals = ticket.lines.map((line) => {
      if (
        !Number.isSafeInteger(line.unitPriceMillimes) ||
        line.unitPriceMillimes <= 0 ||
        !Number.isSafeInteger(line.quantity) ||
        line.quantity <= 0 ||
        line.lineTotalMillimes !== line.unitPriceMillimes * line.quantity ||
        !line.productNameSnapshot.trim()
      ) {
        throw new Error('Ticket line details are invalid.');
      }
      return line.lineTotalMillimes;
    });
    if (sumMillimeAmounts(lineTotals) !== ticket.totalMillimes) {
      throw new Error('Ticket total must equal the sum of its line totals.');
    }
    this.database.runSync(
      `INSERT INTO tickets
         (id, customer_id, created_at, updated_at, total_millimes, status, voided_at)
       VALUES (?, ?, ?, ?, ?, ?, ?)
       ON CONFLICT(id) DO UPDATE SET customer_id = excluded.customer_id,
         updated_at = excluded.updated_at, total_millimes = excluded.total_millimes,
         status = excluded.status, voided_at = excluded.voided_at;`,
      ticket.id,
      ticket.customerId,
      ticket.createdAt,
      ticket.updatedAt,
      ticket.totalMillimes,
      ticket.status,
      ticket.voidedAt,
    );
    this.database.runSync('DELETE FROM ticket_lines WHERE ticket_id = ?;', ticket.id);
    for (const line of ticket.lines) {
      this.database.runSync(
        `INSERT INTO ticket_lines
           (id, ticket_id, product_id, product_name_snapshot, barcode_snapshot,
            unit_price_millimes, quantity, line_total_millimes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?);`,
        line.id,
        ticket.id,
        line.productId,
        line.productNameSnapshot,
        line.barcodeSnapshot,
        line.unitPriceMillimes,
        line.quantity,
        line.lineTotalMillimes,
      );
    }
  }

  /** Writes a new ticket with its audit event and operation-log entry. Call inside a transaction. */
  insertTicketRecords(ticket: Ticket, db: SQLite.SQLiteDatabase): Ticket {
    const customer = db.getFirstSync<{ is_active: number }>(
      'SELECT is_active FROM customers WHERE id = ?;',
      ticket.customerId,
    );
    if (!customer || customer.is_active !== 1) {
      throw new Error('An active customer must be selected for a ticket.');
    }
    this.saveTicket(ticket);
    appendAuditEvent({ entityType: 'ticket', entityId: ticket.id, action: 'create', before: null, after: { ...ticket } }, db);
    persistPendingChange(createPendingChange('ticket', ticket.id, 'create', { ticket }), db);
    return ticket;
  }

  /** Records a ticket without payment. Sales paid at the counter go through `recordSale`. */
  createTicket(ticket: Ticket): Ticket {
    if (isWalkInCustomer(ticket.customerId)) {
      throw new Error('Walk-in tickets must be paid at sale.');
    }
    return this.transaction((db) => this.insertTicketRecords(ticket, db));
  }

  updateTicket(id: string, ticket: Ticket): Ticket {
    return this.transaction((db) => {
      const before = this.getTicket(id);
      if (!before) {
        throw new Error('Ticket not found.');
      }
      if (before.voidedAt) {
        throw new Error('Voided tickets cannot be edited.');
      }
      const paid = this.totalPaid(id);
      if (ticket.customerId !== before.customerId && isWalkInCustomer(ticket.customerId)) {
        throw new Error('Walk-in tickets must be paid at sale.');
      }
      if (isWalkInCustomer(before.customerId) && ticket.totalMillimes !== paid) {
        throw new Error('Walk-in tickets must stay fully paid.');
      }
      if (ticket.customerId !== before.customerId && paid > 0) {
        throw new Error('A ticket with payments cannot be reassigned to another customer.');
      }
      if (ticket.customerId !== before.customerId) {
        const customer = db.getFirstSync<{ is_active: number }>(
          'SELECT is_active FROM customers WHERE id = ?;',
          ticket.customerId,
        );
        if (!customer || customer.is_active !== 1) {
          throw new Error('An active customer must be selected for a ticket.');
        }
      }
      if (ticket.totalMillimes < paid) {
        throw new Error('Ticket total cannot be less than payments already recorded.');
      }
      const updated = { ...ticket, id, createdAt: before.createdAt, updatedAt: new Date().toISOString() };
      this.saveTicket(updated);
      appendAuditEvent({ entityType: 'ticket', entityId: id, action: 'update', before: { ...before }, after: { ...updated } }, db);
      persistPendingChange(createPendingChange('ticket', id, 'update', { before, after: updated }), db);
      return updated;
    });
  }

  voidTicket(id: string): Ticket {
    return this.transaction((db) => {
      const before = this.getTicket(id);
      if (!before) {
        throw new Error('Ticket not found.');
      }
      if (before.voidedAt) {
        throw new Error('Ticket is already voided.');
      }
      const voidedAt = new Date().toISOString();
      const after = { ...before, voidedAt, updatedAt: voidedAt };
      this.database.runSync('UPDATE tickets SET voided_at = ?, updated_at = ? WHERE id = ?;', voidedAt, voidedAt, id);
      appendAuditEvent({ entityType: 'ticket', entityId: id, action: 'void', before: { ...before }, after: { ...after } }, db);
      persistPendingChange(createPendingChange('ticket', id, 'soft_delete', { before, after }), db);
      // The money received at sale goes with the ticket, so today's sales and cash stay consistent (FR-020).
      for (const payment of listActiveSalePayments(id, db)) {
        voidPaymentRecords(payment, db, voidedAt);
      }
      return after;
    });
  }
}

export const ticketRepository = new TicketRepository();
