import * as SQLite from 'expo-sqlite';

import { getLedgerDatabase, accessLedger, withLedgerTransaction } from '@/db';
import { Customer } from '../types/customer';
import { Product } from '../types/product';
import { WALK_IN_CUSTOMER_ID } from '../constants';

interface ProductRow {
  id: string;
  barcode: string | null;
  name: string;
  selling_price_millimes: number;
  is_active: number;
  created_at: string;
  updated_at: string;
}

interface CustomerRow {
  id: string;
  internal_code: string;
  display_label: string;
  real_name: string | null;
  phone_number: string | null;
  is_active: number;
  created_at: string;
  updated_at: string;
}

function mapProduct(row: ProductRow): Product {
  return {
    id: row.id,
    barcode: row.barcode,
    name: row.name,
    sellingPriceMillimes: row.selling_price_millimes,
    isActive: row.is_active === 1,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  };
}

function mapCustomer(row: CustomerRow): Customer {
  return {
    id: row.id,
    internalCode: row.internal_code,
    displayLabel: row.display_label,
    realName: row.real_name,
    phoneNumber: row.phone_number,
    isActive: row.is_active === 1,
    createdAt: row.created_at,
    updatedAt: row.updated_at,
  };
}

export class LedgerRepository {
  constructor(
    private readonly db?: SQLite.SQLiteDatabase,
    private readonly insideTransaction = false,
  ) {}

  private get database(): SQLite.SQLiteDatabase {
    return this.db ?? getLedgerDatabase();
  }

  private access<T>(task: (db: SQLite.SQLiteDatabase) => Promise<T>): Promise<T> {
    return this.insideTransaction ? task(this.database) : accessLedger(task, this.database);
  }

  transaction<T>(operation: (db: SQLite.SQLiteDatabase, repository: LedgerRepository) => Promise<T>): Promise<T> {
    return withLedgerTransaction((db) => operation(db, new LedgerRepository(db, true)), this.database);
  }

  async listProducts(search = '', includeInactive = false): Promise<Product[]> {
    const searchPattern = `%${search.trim()}%`;
    const rows = await this.access((db) =>
      db.getAllAsync<ProductRow>(
        `SELECT * FROM products
         WHERE (? = 1 OR is_active = 1)
           AND (name LIKE ? COLLATE NOCASE OR COALESCE(barcode, '') LIKE ?)
         ORDER BY name COLLATE NOCASE, created_at DESC;`,
        includeInactive ? 1 : 0,
        searchPattern,
        searchPattern,
      ),
    );
    return rows.map(mapProduct);
  }

  async getProduct(id: string): Promise<Product | null> {
    const row = await this.access((db) => db.getFirstAsync<ProductRow>('SELECT * FROM products WHERE id = ?;', id));
    return row ? mapProduct(row) : null;
  }

  async findActiveProductByBarcode(barcode: string): Promise<Product | null> {
    const row = await this.access((db) =>
      db.getFirstAsync<ProductRow>('SELECT * FROM products WHERE barcode = ? AND is_active = 1 LIMIT 1;', barcode.trim()),
    );
    return row ? mapProduct(row) : null;
  }

  async saveProduct(product: Product): Promise<void> {
    await this.access((db) =>
      db.runAsync(
        `INSERT INTO products (id, barcode, name, selling_price_millimes, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT(id) DO UPDATE SET barcode = excluded.barcode, name = excluded.name,
           selling_price_millimes = excluded.selling_price_millimes,
           is_active = excluded.is_active, updated_at = excluded.updated_at;`,
        product.id,
        product.barcode,
        product.name,
        product.sellingPriceMillimes,
        product.isActive ? 1 : 0,
        product.createdAt,
        product.updatedAt,
      ),
    );
  }

  async listCustomers(search = '', includeInactive = false, includeWalkIn = false): Promise<Customer[]> {
    const searchPattern = `%${search.trim()}%`;
    const rows = await this.access((db) =>
      db.getAllAsync<CustomerRow>(
        `SELECT * FROM customers
         WHERE (? = 1 OR is_active = 1)
           AND (? = 1 OR id <> ?)
           AND (display_label LIKE ? COLLATE NOCASE
             OR COALESCE(real_name, '') LIKE ? COLLATE NOCASE
             OR COALESCE(phone_number, '') LIKE ?)
         ORDER BY display_label COLLATE NOCASE, created_at DESC;`,
        includeInactive ? 1 : 0,
        includeWalkIn ? 1 : 0,
        WALK_IN_CUSTOMER_ID,
        searchPattern,
        searchPattern,
        searchPattern,
      ),
    );
    return rows.map(mapCustomer);
  }

  async getCustomer(id: string): Promise<Customer | null> {
    const row = await this.access((db) => db.getFirstAsync<CustomerRow>('SELECT * FROM customers WHERE id = ?;', id));
    return row ? mapCustomer(row) : null;
  }

  async saveCustomer(customer: Customer): Promise<void> {
    await this.access((db) =>
      db.runAsync(
        `INSERT INTO customers
           (id, internal_code, display_label, real_name, phone_number, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT(id) DO UPDATE SET display_label = excluded.display_label,
           real_name = excluded.real_name, phone_number = excluded.phone_number,
           is_active = excluded.is_active, updated_at = excluded.updated_at;`,
        customer.id,
        customer.internalCode,
        customer.displayLabel,
        customer.realName ?? null,
        customer.phoneNumber ?? null,
        customer.isActive ? 1 : 0,
        customer.createdAt,
        customer.updatedAt,
      ),
    );
  }
}

export const ledgerRepository = new LedgerRepository();
