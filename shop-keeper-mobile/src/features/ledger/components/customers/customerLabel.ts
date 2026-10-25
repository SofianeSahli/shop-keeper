import type { getTranslations } from '@/i18n';
import { isWalkInCustomer } from '../../constants';
import { Customer } from '../../types/customer';

/** Display name of a customer; the built-in walk-in customer is always shown translated. */
export function getCustomerLabel(
  customerId: string,
  customers: readonly Customer[],
  t: ReturnType<typeof getTranslations>,
): string {
  if (isWalkInCustomer(customerId)) return t.walkInCustomer;
  return customers.find((customer) => customer.id === customerId)?.displayLabel ?? t.recordUnavailable;
}
