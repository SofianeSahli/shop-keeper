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

vi.mock('@/db', () => ({ getLedgerDatabase: vi.fn(), withLedgerTransaction: vi.fn() }));
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

  it('creates, edits and soft-removes products with pending change records', () => {
    const products = new Map<string, Product>();
    const repository = {
      transaction: <T>(operation: (db: SQLite.SQLiteDatabase) => T) => operation({} as SQLite.SQLiteDatabase),
      getProduct: (id: string) => products.get(id) ?? null,
      findActiveProductByBarcode: (barcode: string) =>
        [...products.values()].find((product) => product.isActive && product.barcode === barcode) ?? null,
      saveProduct: (product: Product) => products.set(product.id, product),
    } as unknown as LedgerRepository;

    const created = createProduct({
      name: ' Tea ',
      barcode: ' 12345 ',
      sellingPriceMillimes: 1250,
    }, repository);
    expect(created.name).toBe('Tea');
    expect(created.barcode).toBe('12345');
    expect(products.get(created.id)).toEqual(created);

    const updated = updateProduct(created.id, {
      name: 'Green Tea',
      barcode: '12345',
      sellingPriceMillimes: 1500,
    }, repository);
    expect(updated.createdAt).toBe(created.createdAt);
    expect(updated.sellingPriceMillimes).toBe(1500);

    const removed = removeProduct(created.id, repository);
    expect(removed.isActive).toBe(false);
    expect(products.get(created.id)?.isActive).toBe(false);
    expect(persistence.operations).toEqual(['create', 'update', 'soft_delete']);
  });

  it('creates, edits and deactivates a customer without deleting its record', () => {
    const customers = new Map<string, Customer>();
    const repository = {
      transaction: <T>(operation: (db: SQLite.SQLiteDatabase) => T) => operation({} as SQLite.SQLiteDatabase),
      getCustomer: (id: string) => customers.get(id) ?? null,
      saveCustomer: (customer: Customer) => customers.set(customer.id, customer),
    } as unknown as LedgerRepository;

    const created = createCustomer({
      displayLabel: ' Corner Shop ',
      realName: '  ',
      phoneNumber: ' 555 ',
    }, repository);
    expect(created.displayLabel).toBe('Corner Shop');
    expect(created.realName).toBeNull();
    expect(created.phoneNumber).toBe('555');

    const updated = updateCustomer(created.id, {
      displayLabel: 'Shop A',
      phoneNumber: '',
    }, repository);
    expect(updated.internalCode).toBe(created.internalCode);
    expect(updated.phoneNumber).toBeNull();

    const removed = removeCustomer(created.id, repository);
    expect(removed.isActive).toBe(false);
    expect(customers.has(created.id)).toBe(true);
    expect(persistence.operations).toEqual(['create', 'update', 'soft_delete']);
  });
});
