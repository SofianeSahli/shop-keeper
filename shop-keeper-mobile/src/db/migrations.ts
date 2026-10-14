import type * as SQLite from 'expo-sqlite';

async function getColumnNames(db: SQLite.SQLiteDatabase, tableName: string): Promise<Set<string>> {
  const columns = await db.getAllAsync<{ name: string }>(`PRAGMA table_info(${tableName});`);
  return new Set(columns.map((column) => column.name));
}

async function addColumnIfMissing(
  db: SQLite.SQLiteDatabase,
  tableName: string,
  columnName: string,
  definition: string,
): Promise<void> {
  if (!(await getColumnNames(db, tableName)).has(columnName)) {
    await db.execAsync(`ALTER TABLE ${tableName} ADD COLUMN ${columnName} ${definition};`);
  }
}

export async function migrateLedgerDatabase(db: SQLite.SQLiteDatabase): Promise<void> {
  await addColumnIfMissing(db, 'customers', 'is_active', 'INTEGER NOT NULL DEFAULT 1');
  await addColumnIfMissing(db, 'tickets', 'updated_at', "TEXT NOT NULL DEFAULT ''");
  await addColumnIfMissing(db, 'tickets', 'voided_at', 'TEXT');
  await addColumnIfMissing(db, 'payments', 'updated_at', "TEXT NOT NULL DEFAULT ''");
  await addColumnIfMissing(db, 'payments', 'voided_at', 'TEXT');
  await db.execAsync(`
    UPDATE tickets SET updated_at = created_at WHERE updated_at = '';
    UPDATE payments SET updated_at = applied_at WHERE updated_at = '';
    CREATE TABLE IF NOT EXISTS ledger_audit_events (
      id TEXT PRIMARY KEY,
      entity_type TEXT NOT NULL,
      entity_id TEXT NOT NULL,
      action TEXT NOT NULL,
      before_json TEXT,
      after_json TEXT,
      created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_pending_changes_entity
      ON pending_changes(entity_type, entity_id, created_at);
    CREATE INDEX IF NOT EXISTS idx_ledger_audit_entity
      ON ledger_audit_events(entity_type, entity_id, created_at);
    CREATE INDEX IF NOT EXISTS idx_active_products_barcode
      ON products(barcode) WHERE is_active = 1 AND barcode IS NOT NULL;
  `);
}
