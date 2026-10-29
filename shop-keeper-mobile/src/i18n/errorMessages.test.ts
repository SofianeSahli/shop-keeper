import { describe, expect, it, vi } from 'vitest';
import { getFieldValidationKey, getLedgerErrorMessage } from './errorMessages';
import { getTranslations } from './index';
import { SALE_ERROR_MESSAGES } from '@/features/ledger/services/saleService';

vi.mock('@/db', () => ({ getLedgerDatabase: vi.fn(), withLedgerTransaction: vi.fn() }));
vi.mock('@/lib/id', () => ({ createId: vi.fn(() => 'generated-id') }));

const t = getTranslations('fr');
const message = (text: string) => getLedgerErrorMessage(new Error(text), t);

describe('getLedgerErrorMessage for sale and walk-in rules', () => {
  it.each([
    'Walk-in tickets must be paid at sale.',
    'Walk-in tickets must stay fully paid.',
    'Walk-in tickets cannot receive payments.',
    'Void the walk-in ticket instead.',
    'The walk-in customer cannot be changed.',
  ])('maps "%s" to walkInLocked before the generic ticket rule', (text) => {
    expect(message(text)).toBe(t.walkInLocked);
  });

  it('maps the sale choice errors before the generic amount rule', () => {
    expect(message(SALE_ERROR_MESSAGES.kridiNeedsCustomer)).toBe(t.kridiNeedsCustomer);
    expect(message(SALE_ERROR_MESSAGES.partialAmountInvalid)).toBe(t.partialAmountInvalid);
    expect(message(SALE_ERROR_MESSAGES.partialEqualsTotal)).toBe(t.partialEqualsTotal);
  });

  it('keeps the existing mappings', () => {
    expect(message('Payment exceeds the outstanding ticket balance.')).toBe(t.paymentExceedsBalance);
    expect(message('Ticket total must equal the sum of its line totals.')).toBe(t.invalidTicket);
  });
});

describe('getFieldValidationKey', () => {
  it.each([
    ['Product name is required.', 'requiredField'],
    ['Customer display label is required.', 'requiredField'],
    ['An active product already uses this barcode.', 'duplicateBarcode'],
    ['Enter a valid amount with up to three decimal places.', 'invalidAmount'],
    ['Ticket quantity must be a positive integer.', 'invalidQuantity'],
    [SALE_ERROR_MESSAGES.kridiNeedsCustomer, 'kridiNeedsCustomer'],
    [SALE_ERROR_MESSAGES.partialAmountInvalid, 'partialAmountInvalid'],
    ['Payment exceeds the outstanding ticket balance.', 'paymentExceedsBalance'],
  ])('treats "%s" as a field error (%s)', (text, key) => {
    expect(getFieldValidationKey(new Error(text))).toBe(key);
  });

  it.each(['Product not found.', 'The walk-in customer cannot be changed.', 'disk I/O error', 'Ticket total must equal the sum of its line totals.'])(
    'leaves "%s" to the error toast',
    (text) => {
      expect(getFieldValidationKey(new Error(text))).toBeNull();
    },
  );

  it('returns null for values that are not errors', () => {
    expect(getFieldValidationKey('oops')).toBeNull();
  });
});
