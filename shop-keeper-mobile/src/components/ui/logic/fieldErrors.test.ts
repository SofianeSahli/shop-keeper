import { describe, expect, it } from 'vitest';
import { displayedFieldError, firstFieldInError, withoutField } from './fieldErrors';

const order = ['customer', 'items', 'amount'] as const;
type Field = (typeof order)[number];

describe('field errors', () => {
  it('picks the first field in error in form order, not object order', () => {
    expect(firstFieldInError(order, { amount: 'Invalid', customer: 'Required' })).toBe('customer');
    expect(firstFieldInError(order, { amount: 'Invalid' })).toBe('amount');
  });

  it('returns null when no field is in error', () => {
    expect(firstFieldInError(order, {})).toBeNull();
    expect(firstFieldInError(order, { items: undefined })).toBeNull();
  });

  it('removes one field and keeps the others', () => {
    expect(withoutField<Field>({ customer: 'a', amount: 'b' }, 'customer')).toEqual({ amount: 'b' });
  });

  it('shows a live error only for fields checked on Save', () => {
    const checked = new Set<Field>(['customer']);
    const live = { customer: 'Required', amount: 'Invalid' };
    expect(displayedFieldError<Field>('customer', {}, checked, live)).toBe('Required');
    expect(displayedFieldError<Field>('amount', {}, checked, live)).toBeUndefined();
  });

  it('clears a checked field as soon as it becomes valid', () => {
    expect(displayedFieldError<Field>('customer', {}, new Set<Field>(['customer']), {})).toBeUndefined();
  });

  it('shows an error reported by a service even if the field passes the form check', () => {
    expect(displayedFieldError<Field>('amount', { amount: 'Too high' }, new Set<Field>(), {})).toBe('Too high');
  });
});
