import { getTranslations } from './index';

type TranslationKey = keyof ReturnType<typeof getTranslations>;

const errorRules: [RegExp, TranslationKey][] = [
  // Sale and walk-in rules (spec 003) come first: their messages also mention amounts and tickets.
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

export function getLedgerErrorMessage(
  cause: unknown,
  translations: ReturnType<typeof getTranslations>,
  fallback: string = translations.operationFailed,
): string {
  const message = cause instanceof Error ? cause.message : '';
  const rule = errorRules.find(([pattern]) => pattern.test(message));
  return rule ? translations[rule[1]] : fallback;
}
