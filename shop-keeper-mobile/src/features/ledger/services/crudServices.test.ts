import type * as SQLite from 'expo-sqlite';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Customer } from '../types/customer';
import { Product } from '../types/product';
import { LedgerRepository } from './ledgerRepository';
import { createCustomer, removeCustomer, updateCustomer } from './customerService';
import { createProduct, removeProduct, updateProduct } from './productService';

const persistence = vi.hoisted(() => ({
  operations: [] as string[],
  createPendingChange: vi.fn((_entity: string, _id: string, operation: string) => ({ operation })),
  persistPendingChange: vi.fn((change: { operation: string }) => {
    persistence.operations.push(change.operation);
  }),
}));

vi.mock('@/db', () => ({ getLedgerDatabase: vi.fn(), withLedgerTransaction: vi.fn(), accessLedger: vi.fn() }));
vi.mock('./pendingChangeService', () => ({
  createPendingChange: persistence.createPendingChange,
  persistPendingChange: persistence.persistPendingChange,
}));
vi.mock('@/lib/id', () => ({ createId: vi.fn(() => 'generated-id') }));

describe('product and customer CRUD services', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    persistence.operations = [];
  });

  it('creates, edits and soft-removes products with pending change records', async () => {
    const products = new Map<string, Product>();
    const repository = {
      getProduct: async (id: string) => products.get(id) ?? null,
      findActiveProductByBarcode: async (barcode: string) =>
        [...products.values()].find((product) => product.isActive && product.barcode === barcode) ?? null,
      saveProduct: async (product: Product) => {
        products.set(product.id, product);
      },
    } as unknown as LedgerRepository;
    Object.assign(repository, {
      transaction: <T>(operation: (db: SQLite.SQLiteDatabase, scoped: LedgerRepository) => Promise<T>) =>
        operation({} as SQLite.SQLiteDatabase, repository),
    });

    const created = await createProduct({
      name: ' Tea ',
      barcode: ' 12345 ',
      sellingPriceMillimes: 1250,
    }, repository);
    expect(created.name).toBe('Tea');
    expect(created.barcode).toBe('12345');
    expect(products.get(created.id)).toEqual(created);

    const updated = await updateProduct(created.id, {
      name: 'Green Tea',
      barcode: '12345',
      sellingPriceMillimes: 1500,
    }, repository);
    expect(updated.createdAt).toBe(created.createdAt);
    expect(updated.sellingPriceMillimes).toBe(1500);

    const removed = await removeProduct(created.id, repository);
    expect(removed.isActive).toBe(false);
    expect(products.get(created.id)?.isActive).toBe(false);
    expect(persistence.operations).toEqual(['create', 'update', 'soft_delete']);
  });

  it('creates, edits and deactivates a customer without deleting its record', async () => {
    const customers = new Map<string, Customer>();
    const repository = {
      getCustomer: async (id: string) => customers.get(id) ?? null,
      saveCustomer: async (customer: Customer) => {
        customers.set(customer.id, customer);
      },
    } as unknown as LedgerRepository;
    Object.assign(repository, {
      transaction: <T>(operation: (db: SQLite.SQLiteDatabase, scoped: LedgerRepository) => Promise<T>) =>
        operation({} as SQLite.SQLiteDatabase, repository),
    });

    const created = await createCustomer({
      displayLabel: ' Corner Shop ',
      realName: '  ',
      phoneNumber: ' 555 ',
    }, repository);
    expect(created.displayLabel).toBe('Corner Shop');
    expect(created.realName).toBeNull();
    expect(created.phoneNumber).toBe('555');

    const updated = await updateCustomer(created.id, {
      displayLabel: 'Shop A',
      phoneNumber: '',
    }, repository);
    expect(updated.internalCode).toBe(created.internalCode);
    expect(updated.phoneNumber).toBeNull();

    const removed = await removeCustomer(created.id, repository);
    expect(removed.isActive).toBe(false);
    expect(customers.has(created.id)).toBe(true);
    expect(persistence.operations).toEqual(['create', 'update', 'soft_delete']);
  });
});
