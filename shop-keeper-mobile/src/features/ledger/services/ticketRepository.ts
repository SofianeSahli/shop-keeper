import type * as SQLite from 'expo-sqlite';
import { getLedgerDatabase, accessLedger, withLedgerTransaction } from '@/db';
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

const WALK_IN_MUST_BE_PAID = 'Walk-in tickets must be paid at sale.';
const ACTIVE_CUSTOMER_REQUIRED = 'An active customer must be selected for a ticket.';

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

async function isActiveCustomer(db: SQLite.SQLiteDatabase, customerId: string): Promise<boolean> {
  const customer = await db.getFirstAsync<{ is_active: number }>('SELECT is_active FROM customers WHERE id = ?;', customerId);
  return customer?.is_active === 1;
}

function assertValidTicketLines(ticket: Ticket): void {
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
}

export class TicketRepository {
  constructor(
    private readonly db?: SQLite.SQLiteDatabase,
    private readonly insideTransaction = false,
  ) {}

  private get database(): SQLite.SQLiteDatabase {
    return this.db ?? getLedgerDatabase();
  }

  private access<T>(task: (db: SQLite.SQLiteDatabase) => Promise<T>): Promise<T> {
    return this.insideTransaction ? task(this.database) : accessLedger(task, this.database);
  }

  withinTransaction(transactionDb: SQLite.SQLiteDatabase): TicketRepository {
    return new TicketRepository(transactionDb, true);
  }

  transaction<T>(operation: (db: SQLite.SQLiteDatabase, repository: TicketRepository) => Promise<T>): Promise<T> {
    return withLedgerTransaction((db) => operation(db, this.withinTransaction(db)), this.database);
  }

  private async mapTicket(db: SQLite.SQLiteDatabase, row: TicketRow): Promise<Ticket> {
    const paidRow = await db.getFirstAsync<{ amount: number }>(
      'SELECT COALESCE(SUM(amount_millimes), 0) AS amount FROM payments WHERE ticket_id = ? AND voided_at IS NULL;',
      row.id,
    );
    const lines = await db.getAllAsync<TicketLineRow>('SELECT * FROM ticket_lines WHERE ticket_id = ? ORDER BY rowid;', row.id);
    return {
      id: row.id,
      customerId: row.customer_id,
      createdAt: row.created_at,
      updatedAt: row.updated_at,
      totalMillimes: row.total_millimes,
      status: getTicketStatus(paidRow?.amount ?? 0, row.total_millimes),
      voidedAt: row.voided_at,
      lines: lines.map(mapLine),
    };
  }

  listTickets(customerId?: string, includeVoided = false): Promise<Ticket[]> {
    return this.access(async (db) => {
      const rows = await db.getAllAsync<TicketRow>(
        `SELECT * FROM tickets
         WHERE (? IS NULL OR customer_id = ?)
           AND (? = 1 OR voided_at IS NULL)
         ORDER BY created_at DESC;`,
        customerId ?? null,
        customerId ?? null,
        includeVoided ? 1 : 0,
      );
      const tickets: Ticket[] = [];
      for (const row of rows) {
        tickets.push(await this.mapTicket(db, row));
      }
      return tickets;
    });
  }

  getTicket(id: string, includeVoided = true): Promise<Ticket | null> {
    return this.access(async (db) => {
      const row = await db.getFirstAsync<TicketRow>(
        `SELECT * FROM tickets WHERE id = ? AND (? = 1 OR voided_at IS NULL);`,
        id,
        includeVoided ? 1 : 0,
      );
      return row ? this.mapTicket(db, row) : null;
    });
  }

  async totalPaid(ticketId: string, excludePaymentId?: string): Promise<number> {
    const row = await this.access((db) =>
      db.getFirstAsync<{ amount: number }>(
        `SELECT COALESCE(SUM(amount_millimes), 0) AS amount FROM payments
         WHERE ticket_id = ? AND voided_at IS NULL AND (? IS NULL OR id <> ?);`,
        ticketId,
        excludePaymentId ?? null,
        excludePaymentId ?? null,
      ),
    );
    return row?.amount ?? 0;
  }

  async saveTicket(ticket: Ticket): Promise<void> {
    assertValidTicketLines(ticket);
    await this.access(async (db) => {
      await db.runAsync(
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
      await db.runAsync('DELETE FROM ticket_lines WHERE ticket_id = ?;', ticket.id);
      for (const line of ticket.lines) {
        await db.runAsync(
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
    });
  }

  async insertTicketRecords(ticket: Ticket, transactionDb: SQLite.SQLiteDatabase): Promise<Ticket> {
    if (!(await isActiveCustomer(transactionDb, ticket.customerId))) {
      throw new Error(ACTIVE_CUSTOMER_REQUIRED);
    }
    await this.withinTransaction(transactionDb).saveTicket(ticket);
    await appendAuditEvent({ entityType: 'ticket', entityId: ticket.id, action: 'create', before: null, after: { ...ticket } }, transactionDb);
    await persistPendingChange(createPendingChange('ticket', ticket.id, 'create', { ticket }), transactionDb);
    return ticket;
  }

  async createTicket(ticket: Ticket): Promise<Ticket> {
    if (isWalkInCustomer(ticket.customerId)) {
      throw new Error(WALK_IN_MUST_BE_PAID);
    }
    return this.transaction((db) => this.insertTicketRecords(ticket, db));
  }

  updateTicket(id: string, ticket: Ticket): Promise<Ticket> {
    return this.transaction(async (db, tickets) => {
      const before = await tickets.getTicket(id);
      if (!before) {
        throw new Error('Ticket not found.');
      }
      if (before.voidedAt) {
        throw new Error('Voided tickets cannot be edited.');
      }
      const paid = await tickets.totalPaid(id);
      const changesCustomer = ticket.customerId !== before.customerId;
      if (changesCustomer && isWalkInCustomer(ticket.customerId)) {
        throw new Error(WALK_IN_MUST_BE_PAID);
      }
      if (isWalkInCustomer(before.customerId) && ticket.totalMillimes !== paid) {
        throw new Error('Walk-in tickets must stay fully paid.');
      }
      if (changesCustomer && paid > 0) {
        throw new Error('A ticket with payments cannot be reassigned to another customer.');
      }
      if (changesCustomer && !(await isActiveCustomer(db, ticket.customerId))) {
        throw new Error(ACTIVE_CUSTOMER_REQUIRED);
      }
      if (ticket.totalMillimes < paid) {
        throw new Error('Ticket total cannot be less than payments already recorded.');
      }
      const updated = { ...ticket, id, createdAt: before.createdAt, updatedAt: new Date().toISOString() };
      await tickets.saveTicket(updated);
      await appendAuditEvent({ entityType: 'ticket', entityId: id, action: 'update', before: { ...before }, after: { ...updated } }, db);
      await persistPendingChange(createPendingChange('ticket', id, 'update', { before, after: updated }), db);
      return updated;
    });
  }

  voidTicket(id: string): Promise<Ticket> {
    return this.transaction(async (db, tickets) => {
      const before = await tickets.getTicket(id);
      if (!before) {
        throw new Error('Ticket not found.');
      }
      if (before.voidedAt) {
        throw new Error('Ticket is already voided.');
      }
      const voidedAt = new Date().toISOString();
      const after = { ...before, voidedAt, updatedAt: voidedAt };
      await db.runAsync('UPDATE tickets SET voided_at = ?, updated_at = ? WHERE id = ?;', voidedAt, voidedAt, id);
      await appendAuditEvent({ entityType: 'ticket', entityId: id, action: 'void', before: { ...before }, after: { ...after } }, db);
      await persistPendingChange(createPendingChange('ticket', id, 'soft_delete', { before, after }), db);
      for (const salePayment of await listActiveSalePayments(id, db)) {
        await voidPaymentRecords(salePayment, db, voidedAt);
      }
      return after;
    });
  }
}

export const ticketRepository = new TicketRepository();
