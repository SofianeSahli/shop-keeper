import { Product, ProductInput } from '../types/product';
import { isValidMillimeAmount } from '@/lib/money';
import { validateRequiredText } from '@/utils/validation';
import { ledgerRepository, LedgerRepository } from './ledgerRepository';
import { createPendingChange, persistPendingChange } from './pendingChangeService';
import { createId } from '@/lib/id';

export function validateProductInput(input: Partial<ProductInput>): string[] {
  const errors: string[] = [];

  if (!input.name || !input.name.trim()) {
    errors.push('Product name is required.');
  }

  if (input.sellingPriceMillimes !== undefined && !isValidMillimeAmount(input.sellingPriceMillimes)) {
    errors.push('Product selling price must be a positive integer in millimes.');
  }

  if (input.barcode !== undefined && input.barcode !== null && input.barcode.trim() === '') {
    errors.push('Barcode cannot be empty when provided.');
  }

  return errors;
}

export function createProductRecord(input: ProductInput): Product {
  const errors = validateProductInput(input);
  if (errors.length > 0) {
    throw new Error(errors.join(' '));
  }

  const now = new Date().toISOString();

  return {
    id: input.id ?? createId(),
    barcode: input.barcode?.trim() ? input.barcode.trim() : null,
    name: validateRequiredText(input.name, 'Product name'),
    sellingPriceMillimes: input.sellingPriceMillimes,
    isActive: input.isActive ?? true,
    createdAt: now,
    updatedAt: now,
  };
}

export function updateProductRecord(existing: Product, input: ProductInput): Product {
  const updated = createProductRecord({ ...input, id: existing.id, isActive: existing.isActive });
  return {
    ...updated,
    createdAt: existing.createdAt,
    updatedAt: new Date().toISOString(),
  };
}

export function listProducts(search = '', repository: LedgerRepository = ledgerRepository): Product[] {
  return repository.listProducts(search);
}

export function getProduct(id: string, repository: LedgerRepository = ledgerRepository): Product | null {
  return repository.getProduct(id);
}

export function createProduct(input: ProductInput, repository: LedgerRepository = ledgerRepository): Product {
  const product = createProductRecord(input);
  return repository.transaction((db) => {
    if (product.barcode && repository.findActiveProductByBarcode(product.barcode)) {
      throw new Error('An active product already uses this barcode.');
    }
    repository.saveProduct(product);
    persistPendingChange(createPendingChange('product', product.id, 'create', { product }), db);
    return product;
  });
}

export function updateProduct(
  id: string,
  input: ProductInput,
  repository: LedgerRepository = ledgerRepository,
): Product {
  return repository.transaction((db) => {
    const existing = repository.getProduct(id);
    if (!existing) {
      throw new Error('Product not found.');
    }
    if (!existing.isActive) {
      throw new Error('Inactive products cannot be edited.');
    }
    const product = updateProductRecord(existing, input);
    const duplicate = product.barcode && repository.findActiveProductByBarcode(product.barcode);
    if (duplicate && duplicate.id !== product.id) {
      throw new Error('An active product already uses this barcode.');
    }
    repository.saveProduct(product);
    persistPendingChange(createPendingChange('product', product.id, 'update', { product }), db);
    return product;
  });
}

export function removeProduct(id: string, repository: LedgerRepository = ledgerRepository): Product {
  return repository.transaction((db) => {
    const product = repository.getProduct(id);
    if (!product) {
      throw new Error('Product not found.');
    }
    if (!product.isActive) {
      throw new Error('Product is already inactive.');
    }
    const removed = softDeleteProduct(product);
    repository.saveProduct(removed);
    persistPendingChange(createPendingChange('product', id, 'soft_delete', { before: product, after: removed }), db);
    return removed;
  });
}

export function findProductByBarcode(products: Product[], barcode: string | null | undefined): Product | undefined {
  if (!barcode) {
    return undefined;
  }

  return products.find((product) => product.barcode?.trim() === barcode.trim() && product.isActive);
}

export function findProductById(products: Product[], id: string): Product | undefined {
  return products.find((product) => product.id === id);
}

export function softDeleteProduct(product: Product): Product {
  return {
    ...product,
    isActive: false,
    updatedAt: new Date().toISOString(),
  };
}
