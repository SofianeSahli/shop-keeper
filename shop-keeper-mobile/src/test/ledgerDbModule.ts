import type * as SQLite from 'expo-sqlite';

export function getLedgerDatabase(): SQLite.SQLiteDatabase {
  throw new Error('Tests pass their database explicitly.');
}

export function accessLedger<T>(
  read: (db: SQLite.SQLiteDatabase) => Promise<T>,
  db: SQLite.SQLiteDatabase,
): Promise<T> {
  return read(db);
}

export async function withLedgerTransaction<T>(
  operation: (db: SQLite.SQLiteDatabase) => Promise<T>,
  db: SQLite.SQLiteDatabase,
): Promise<T> {
  let result!: T;
  await db.withTransactionAsync(async () => {
    result = await operation(db);
  });
  return result;
}
