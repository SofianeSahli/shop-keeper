import { Customer, CustomerInput } from '../types/customer';
import { validateRequiredText } from '@/utils/validation';
import { ledgerRepository, LedgerRepository } from './ledgerRepository';
import { createPendingChange, persistPendingChange } from './pendingChangeService';
import { createId } from '@/lib/id';
import { isWalkInCustomer } from '../constants';

const WALK_IN_LOCKED = 'The walk-in customer cannot be changed.';

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
  { includeWalkIn = false }: { includeWalkIn?: boolean } = {},
): Promise<Customer[]> {
  return repository.listCustomers(search, includeInactive, includeWalkIn);
}

export function getCustomer(id: string, repository: LedgerRepository = ledgerRepository): Promise<Customer | null> {
  return repository.getCustomer(id);
}

export async function createCustomer(
  input: CustomerInput,
  repository: LedgerRepository = ledgerRepository,
): Promise<Customer> {
  const customer = createCustomerRecord(input);
  return repository.transaction(async (db, customers) => {
    await customers.saveCustomer(customer);
    await persistPendingChange(createPendingChange('customer', customer.id, 'create', { customer }), db);
    return customer;
  });
}

export async function updateCustomer(
  id: string,
  input: CustomerInput,
  repository: LedgerRepository = ledgerRepository,
): Promise<Customer> {
  if (isWalkInCustomer(id)) throw new Error(WALK_IN_LOCKED);
  return repository.transaction(async (db, customers) => {
    const existing = await customers.getCustomer(id);
    if (!existing) {
      throw new Error('Customer not found.');
    }
    if (!existing.isActive) {
      throw new Error('Inactive customers cannot be edited.');
    }
    const customer = updateCustomerRecord(existing, input);
    await customers.saveCustomer(customer);
    await persistPendingChange(createPendingChange('customer', customer.id, 'update', { customer }), db);
    return customer;
  });
}

export async function removeCustomer(
  id: string,
  repository: LedgerRepository = ledgerRepository,
): Promise<Customer> {
  if (isWalkInCustomer(id)) throw new Error(WALK_IN_LOCKED);
  return repository.transaction(async (db, customers) => {
    const customer = await customers.getCustomer(id);
    if (!customer) {
      throw new Error('Customer not found.');
    }
    if (!customer.isActive) {
      throw new Error('Customer is already inactive.');
    }
    const removed = { ...customer, isActive: false, updatedAt: new Date().toISOString() };
    await customers.saveCustomer(removed);
    await persistPendingChange(
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
