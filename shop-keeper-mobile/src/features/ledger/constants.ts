/** Built-in customer for sales paid in full without a named customer (spec 003, FR-017). */
export const WALK_IN_CUSTOMER_ID = 'walk-in';
export const WALK_IN_INTERNAL_CODE = 'WALK_IN';

export function isWalkInCustomer(customerId: string | null | undefined): boolean {
  return customerId === WALK_IN_CUSTOMER_ID;
}

/** Quick payment amounts offered on the payment form: 10, 20 and 50 DT. */
export const QUICK_AMOUNTS_MILLIMES: readonly number[] = [10_000, 20_000, 50_000];
