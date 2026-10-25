import type * as SQLite from 'expo-sqlite';
import { getLedgerDatabase } from '@/db';
import { createId } from '@/lib/id';

export type AuditableEntityType = 'ticket' | 'payment';
export type AuditAction = 'create' | 'update' | 'void';

export interface LedgerAuditEvent {
  id: string;
  entityType: AuditableEntityType;
  entityId: string;
  action: AuditAction;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  createdAt: string;
}

interface AuditRow {
  id: string;
  entity_type: AuditableEntityType;
  entity_id: string;
  action: AuditAction;
  before_json: string | null;
  after_json: string | null;
  created_at: string;
}


export function appendAuditEvent(
  event: Omit<LedgerAuditEvent, 'id' | 'createdAt'>,
  db: SQLite.SQLiteDatabase = getLedgerDatabase(),
): LedgerAuditEvent {
  const saved: LedgerAuditEvent = {
    ...event,
    id: createId(),
    createdAt: new Date().toISOString(),
  };
  db.runSync(
    `INSERT INTO ledger_audit_events
       (id, entity_type, entity_id, action, before_json, after_json, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?);`,
    saved.id,
    saved.entityType,
    saved.entityId,
    saved.action,
    saved.before ? JSON.stringify(saved.before) : null,
    saved.after ? JSON.stringify(saved.after) : null,
    saved.createdAt,
  );
  return saved;
}

export function listAuditEvents(
  entityType?: AuditableEntityType,
  entityId?: string,
): LedgerAuditEvent[] {
  const rows = getLedgerDatabase().getAllSync<AuditRow>(
    `SELECT * FROM ledger_audit_events
     WHERE (? IS NULL OR entity_type = ?)
       AND (? IS NULL OR entity_id = ?)
     ORDER BY created_at DESC;`,
    entityType ?? null,
    entityType ?? null,
    entityId ?? null,
    entityId ?? null,
  );
  return rows.map((row) => ({
    id: row.id,
    entityType: row.entity_type,
    entityId: row.entity_id,
    action: row.action,
    before: row.before_json ? JSON.parse(row.before_json) as Record<string, unknown> : null,
    after: row.after_json ? JSON.parse(row.after_json) as Record<string, unknown> : null,
    createdAt: row.created_at,
  }));
}
