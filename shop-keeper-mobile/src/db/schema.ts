import type * as SQLite from 'expo-sqlite';
import { migrateLedgerDatabase } from './migrations';
import { LEDGER_SCHEMA_SQL } from './schemaSql';

export { LEDGER_SCHEMA_SQL };

export const LEDGER_DATABASE_NAME = 'shop_keeper_ledger.db';

export async function initializeLedgerSchema(db: SQLite.SQLiteDatabase): Promise<void> {
  await db.execAsync(LEDGER_SCHEMA_SQL);
  await migrateLedgerDatabase(db);
}
