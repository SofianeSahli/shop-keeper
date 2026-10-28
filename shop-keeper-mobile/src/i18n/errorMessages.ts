import { getTranslations } from './index';

export type LedgerErrorKey = keyof ReturnType<typeof getTranslations>;

const errorRules: [RegExp, LedgerErrorKey][] = [
  [/walk-in/i, 'walkInLocked'],
  [/credit needs a customer/i, 'kridiNeedsCustomer'],
  [/partial amount equals the total/i, 'partialEqualsTotal'],
  [/partial amount/i, 'partialAmountInvalid'],
  [/exceeds the outstanding|already fully paid|cannot be added to a paid/i, 'paymentExceedsBalance'],
  [/already uses this barcode/i, 'duplicateBarcode'],
  [/barcode/i, 'invalidBarcode'],
  [/required|cannot be empty/i, 'requiredField'],
  [/quantity/i, 'invalidQuantity'],
  [/customer must match|payment customer/i, 'paymentCustomerMismatch'],
  [/amount|price|millime|integer range/i, 'invalidAmount'],
  [/not found|inactive|already voided|voided ticket|already inactive/i, 'recordUnavailable'],
  [/ticket|line|total/i, 'invalidTicket'],
];

export function getLedgerErrorKey(cause: unknown): LedgerErrorKey | null {
  const message = cause instanceof Error ? cause.message : '';
  return errorRules.find(([pattern]) => pattern.test(message))?.[1] ?? null;
}

export function getLedgerErrorMessage(
  cause: unknown,
  translations: ReturnType<typeof getTranslations>,
  fallback: string = translations.operationFailed,
): string {
  const key = getLedgerErrorKey(cause);
  return key ? translations[key] : fallback;
}

const FIELD_VALIDATION_KEYS: ReadonlySet<LedgerErrorKey> = new Set<LedgerErrorKey>([
  'requiredField',
  'invalidAmount',
  'invalidQuantity',
  'invalidBarcode',
  'duplicateBarcode',
  'kridiNeedsCustomer',
  'partialAmountInvalid',
  'partialEqualsTotal',
  'paymentExceedsBalance',
  'paymentCustomerMismatch',
]);

export function getFieldValidationKey(cause: unknown): LedgerErrorKey | null {
  const key = getLedgerErrorKey(cause);
  return key && FIELD_VALIDATION_KEYS.has(key) ? key : null;
}
