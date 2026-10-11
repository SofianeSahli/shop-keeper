import { Customer, CustomerInput } from '../types/customer';
import { validateRequiredText } from '@/utils/validation';
import { ledgerRepository, LedgerRepository } from './ledgerRepository';
import { createPendingChange, persistPendingChange } from './pendingChangeService';
import { createId } from '@/lib/id';

export function createCustomerRecord(input: CustomerInput): Customer {
  const displayLabel = validateRequiredText(input.displayLabel, 'Customer display label');

  const now = new Date().toISOString();

  return {
    id: input.id ?? createId(),
    internalCode: createId(),
    displayLabel,
    realName: input.realName?.trim() ? input.realName.trim() : null,
    phoneNumber: input.phoneNumber?.trim() ? input.phoneNumber.trim() : null,
    isActive: true,
    createdAt: now,
    updatedAt: now,
  };
}

export function updateCustomerRecord(existing: Customer, input: CustomerInput): Customer {
  return {
    ...existing,
    displayLabel: validateRequiredText(input.displayLabel, 'Customer display label'),
    realName: input.realName?.trim() ? input.realName.trim() : null,
    phoneNumber: input.phoneNumber?.trim() ? input.phoneNumber.trim() : null,
    updatedAt: new Date().toISOString(),
  };
}

export function listCustomers(
  search = '',
  includeInactive = false,
  repository: LedgerRepository = ledgerRepository,
): Customer[] {
  return repository.listCustomers(search, includeInactive);
}

export function getCustomer(id: string, repository: LedgerRepository = ledgerRepository): Customer | null {
  return repository.getCustomer(id);
}

export function createCustomer(
  input: CustomerInput,
  repository: LedgerRepository = ledgerRepository,
): Customer {
  const customer = createCustomerRecord(input);
  return repository.transaction((db) => {
    repository.saveCustomer(customer);
    persistPendingChange(createPendingChange('customer', customer.id, 'create', { customer }), db);
    return customer;
  });
}

export function updateCustomer(
  id: string,
  input: CustomerInput,
  repository: LedgerRepository = ledgerRepository,
): Customer {
  return repository.transaction((db) => {
    const existing = repository.getCustomer(id);
    if (!existing) {
      throw new Error('Customer not found.');
    }
    if (!existing.isActive) {
      throw new Error('Inactive customers cannot be edited.');
    }
    const customer = updateCustomerRecord(existing, input);
    repository.saveCustomer(customer);
    persistPendingChange(createPendingChange('customer', customer.id, 'update', { customer }), db);
    return customer;
  });
}

export function removeCustomer(
  id: string,
  repository: LedgerRepository = ledgerRepository,
): Customer {
  return repository.transaction((db) => {
    const customer = repository.getCustomer(id);
    if (!customer) {
      throw new Error('Customer not found.');
    }
    if (!customer.isActive) {
      throw new Error('Customer is already inactive.');
    }
    const removed = { ...customer, isActive: false, updatedAt: new Date().toISOString() };
    repository.saveCustomer(removed);
    persistPendingChange(
      createPendingChange('customer', id, 'soft_delete', { before: customer, after: removed }),
      db,
    );
    return removed;
  });
}

export function ensurePrivateIdentity(customer: Customer): Customer {
  return {
    ...customer,
    realName: customer.realName?.trim() ? customer.realName.trim() : null,
    phoneNumber: customer.phoneNumber?.trim() ? customer.phoneNumber.trim() : null,
  };
}

export function findCustomerByLabel(customers: Customer[], displayLabel: string): Customer | undefined {
  const normalized = displayLabel.trim();
  return customers.find((customer) => customer.displayLabel.toLowerCase() === normalized.toLowerCase());
}
