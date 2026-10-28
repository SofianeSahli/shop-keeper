import * as SQLite from 'expo-sqlite';

import { runQueued } from './queue';
import { initializeLedgerSchema, LEDGER_DATABASE_NAME } from './schema';

export { runQueued };

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
      await db?.closeAsync().catch(() => undefined);
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
  operation: (db: SQLite.SQLiteDatabase) => Promise<T>,
  db: SQLite.SQLiteDatabase = getLedgerDatabase(),
): Promise<T> {
  return runQueued(async () => {
    let result!: T;
    await db.withTransactionAsync(async () => {
      result = await operation(db);
    });
    return result;
  });
}

export function accessLedger<T>(
  read: (db: SQLite.SQLiteDatabase) => Promise<T>,
  db: SQLite.SQLiteDatabase = getLedgerDatabase(),
): Promise<T> {
  return runQueued(() => read(db));
}
