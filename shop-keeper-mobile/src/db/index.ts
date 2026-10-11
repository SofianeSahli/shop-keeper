import * as SQLite from 'expo-sqlite';

import { initializeLedgerSchema, LEDGER_DATABASE_NAME } from './schema';

let ledgerDatabase: SQLite.SQLiteDatabase | null = null;
let initializationError: Error | null = null;
let openingDatabase: Promise<SQLite.SQLiteDatabase> | null = null;

export function openLedgerDatabase(): Promise<SQLite.SQLiteDatabase> {
  if (ledgerDatabase) {
    return Promise.resolve(ledgerDatabase);
  }
  if (openingDatabase) {
    return openingDatabase;
  }

  openingDatabase = (async () => {
    let db: SQLite.SQLiteDatabase | undefined;
    try {
      db = await SQLite.openDatabaseAsync(LEDGER_DATABASE_NAME);
      await db.execAsync('PRAGMA foreign_keys = ON;');
      await initializeLedgerSchema(db);
      ledgerDatabase = db;
      initializationError = null;
      return db;
    } catch (error) {
      initializationError = error instanceof Error ? error : new Error(String(error));
      try {
        await db?.closeAsync();
      } catch {
        // Preserve the initialization failure that the recovery screen reports.
      }
      throw initializationError;
    } finally {
      openingDatabase = null;
    }
  })();

  return openingDatabase;
}

export function getLedgerDatabase(): SQLite.SQLiteDatabase {
  if (ledgerDatabase) {
    return ledgerDatabase;
  }
  if (initializationError) {
    throw initializationError;
  }
  throw new Error('Ledger database has not been initialized.');
}

export function withLedgerTransaction<T>(
  operation: (db: SQLite.SQLiteDatabase) => T,
  db: SQLite.SQLiteDatabase = getLedgerDatabase(),
): T {
  let result!: T;
  db.withTransactionSync(() => {
    result = operation(db);
  });
  return result;
}
