import { DatabaseSync, type SQLInputValue } from 'node:sqlite';
import type * as SQLite from 'expo-sqlite';
import { LEDGER_SCHEMA_SQL } from '@/db/schemaSql';

type Params = unknown[];

function toInputs(params: Params): SQLInputValue[] {
  const values = params.length === 1 && Array.isArray(params[0]) ? (params[0] as unknown[]) : params;
  return values.map((value) => (value === undefined ? null : value)) as SQLInputValue[];
}

export function createTestLedgerDb(): SQLite.SQLiteDatabase {
  const db = new DatabaseSync(':memory:');
  db.exec('PRAGMA foreign_keys = ON;');
  db.exec(LEDGER_SCHEMA_SQL);

  const runSync = (sql: string, ...params: Params) => {
    const result = db.prepare(sql).run(...toInputs(params));
    return { changes: Number(result.changes), lastInsertRowId: Number(result.lastInsertRowid) };
  };
  const getFirstSync = (sql: string, ...params: Params) => db.prepare(sql).get(...toInputs(params)) ?? null;
  const getAllSync = (sql: string, ...params: Params) => db.prepare(sql).all(...toInputs(params));

  const adapter = {
    execSync: (sql: string) => db.exec(sql),
    execAsync: async (sql: string) => db.exec(sql),
    runSync,
    runAsync: async (sql: string, ...params: Params) => runSync(sql, ...params),
    getFirstSync,
    getFirstAsync: async (sql: string, ...params: Params) => getFirstSync(sql, ...params),
    getAllSync,
    getAllAsync: async (sql: string, ...params: Params) => getAllSync(sql, ...params),
    withTransactionAsync: async (task: () => Promise<void>) => {
      db.exec('BEGIN');
      try {
        await task();
        db.exec('COMMIT');
      } catch (error) {
        db.exec('ROLLBACK');
        throw error;
      }
    },
  };
  return adapter as unknown as SQLite.SQLiteDatabase;
}

export function countRows(db: SQLite.SQLiteDatabase, table: string, where = '1 = 1', ...params: unknown[]): number {
  return db.getFirstSync<{ n: number }>(`SELECT COUNT(*) AS n FROM ${table} WHERE ${where};`, ...(params as never[]))?.n ?? 0;
}

export function insertTestCustomer(db: SQLite.SQLiteDatabase, id: string, displayLabel = id): void {
  const now = '2026-10-01T08:00:00.000Z';
  db.runSync(
    `INSERT INTO customers (id, internal_code, display_label, real_name, phone_number, is_active, created_at, updated_at)
     VALUES (?, ?, ?, NULL, NULL, 1, ?, ?);`,
    id,
    `code-${id}`,
    displayLabel,
    now,
    now,
  );
}
