import type * as SQLite from 'expo-sqlite';
import { getLedgerDatabase, accessLedger } from '@/db';
import { PendingChange, PendingChangeOperation, PendingChangeStatus } from '../types/pending-change';
import { createId } from '@/lib/id';

interface PendingChangeRow {
  id: string;
  entity_type: PendingChange['entityType'];
  entity_id: string;
  operation: PendingChangeOperation;
  payload_json: string;
  status: PendingChangeStatus;
  created_at: string;
  updated_at: string;
}

export function createPendingChange(
  entityType: PendingChange['entityType'],
  entityId: string,
  operation: PendingChangeOperation,
  payloadJson: Record<string, unknown>,
  status: PendingChangeStatus = 'pending',
): PendingChange {
  const now = new Date().toISOString();
  return {
    id: createId(),
    entityType,
    entityId,
    operation,
    payloadJson,
    status,
    createdAt: now,
    updatedAt: now,
  };
}

export async function persistPendingChange(
  change: PendingChange,
  db: SQLite.SQLiteDatabase = getLedgerDatabase(),
): Promise<void> {
  await db.runAsync(
    `INSERT INTO pending_changes
       (id, entity_type, entity_id, operation, payload_json, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?);`,
    change.id,
    change.entityType,
    change.entityId,
    change.operation,
    JSON.stringify(change.payloadJson),
    change.status,
    change.createdAt,
    change.updatedAt,
  );
}

export async function listPendingChanges(): Promise<PendingChange[]> {
  const rows = await accessLedger((db) =>
    db.getAllAsync<PendingChangeRow>(`SELECT * FROM pending_changes WHERE status = 'pending' ORDER BY created_at;`),
  );
  return rows.map((row) => ({
    id: row.id,
    entityType: row.entity_type,
    entityId: row.entity_id,
    operation: row.operation,
    payloadJson: JSON.parse(row.payload_json) as Record<string, unknown>,
    status: row.status,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  }));
}

export function markPendingChangeStatus(change: PendingChange, status: PendingChangeStatus): PendingChange {
  return { ...change, status, updatedAt: new Date().toISOString() };
}
