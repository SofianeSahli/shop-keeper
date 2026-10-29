import { randomUUID } from 'node:crypto';
import { describe, expect, it, vi } from 'vitest';
import { countRows, createTestLedgerDb } from '@/test/ledgerTestDb';
import { WALK_IN_CUSTOMER_ID, WALK_IN_INTERNAL_CODE } from '@/features/ledger/constants';
import { ensureWalkInCustomer, migrateLedgerDatabase } from './migrations';

vi.mock('@/lib/id', () => ({ createId: () => randomUUID() }));

describe('ensureWalkInCustomer', () => {
  it('seeds the walk-in customer once and logs it for sync', async () => {
    const db = createTestLedgerDb();
    await ensureWalkInCustomer(db);

    expect(
      db.getFirstSync('SELECT id, internal_code, is_active FROM customers WHERE id = ?;', WALK_IN_CUSTOMER_ID),
    ).toEqual({ id: WALK_IN_CUSTOMER_ID, internal_code: WALK_IN_INTERNAL_CODE, is_active: 1 });
    expect(countRows(db, 'pending_changes', "entity_type = 'customer' AND operation = 'create' AND status = 'pending'")).toBe(1);
  });

  it('does nothing the second time', async () => {
    const db = createTestLedgerDb();
    await ensureWalkInCustomer(db);
    await ensureWalkInCustomer(db);
    expect(countRows(db, 'customers')).toBe(1);
    expect(countRows(db, 'pending_changes')).toBe(1);
  });

  it('runs as the last step of the ledger migration, which stays idempotent', async () => {
    const db = createTestLedgerDb();
    await migrateLedgerDatabase(db);
    await migrateLedgerDatabase(db);
    expect(countRows(db, 'customers', 'id = ?', WALK_IN_CUSTOMER_ID)).toBe(1);
    expect(countRows(db, 'pending_changes')).toBe(1);
  });
});
