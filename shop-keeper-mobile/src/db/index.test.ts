import { describe, expect, it, vi } from 'vitest';
import { countRows, createTestLedgerDb, insertTestCustomer } from '@/test/ledgerTestDb';
import { accessLedger, withLedgerTransaction } from './index';

vi.mock('expo-sqlite', () => ({}));
vi.mock('@/lib/id', () => ({ createId: () => 'generated-id' }));

describe('withLedgerTransaction and accessLedger', () => {
  it('makes a read started during a transaction wait until it commits', async () => {
    const db = createTestLedgerDb();
    let releaseTransaction!: () => void;
    const transactionHeld = new Promise<void>((resolve) => {
      releaseTransaction = resolve;
    });

    const events: string[] = [];

    const transaction = withLedgerTransaction(async (transactionDb) => {
      insertTestCustomer(transactionDb, 'ali');
      await transactionHeld;
      events.push('transaction finished');
    }, db);
    const read = accessLedger(async (readDb) => {
      events.push('read');
      return countRows(readDb, 'customers');
    }, db);

    await new Promise((resolve) => setTimeout(resolve, 10));
    expect(events).toEqual([]);
    releaseTransaction();
    await transaction;
    await expect(read).resolves.toBe(1);
    expect(events).toEqual(['transaction finished', 'read']);
  });

  it('rolls everything back when the operation rejects', async () => {
    const db = createTestLedgerDb();
    await expect(
      withLedgerTransaction(async (transactionDb) => {
        insertTestCustomer(transactionDb, 'ali');
        throw new Error('disk full');
      }, db),
    ).rejects.toThrow('disk full');
    expect(countRows(db, 'customers')).toBe(0);
  });
});
