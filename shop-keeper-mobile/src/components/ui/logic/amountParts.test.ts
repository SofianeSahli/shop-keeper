import { describe, expect, it } from 'vitest';
import { formatMillimeAmount } from '@/lib/money';
import { splitMillimeAmount } from './amountParts';

const digits = (value: string) => value.replace(/\D/g, '');

describe('splitMillimeAmount', () => {
  it('groups dinars with the shared Tunisian format and a comma before millimes', () => {
    expect(splitMillimeAmount(1_284_500_500)).toEqual({
      sign: '',
      dinars: '1 284 500',
      millimes: ',500',
    });
  });

  it('pads millimes and handles zero', () => {
    expect(splitMillimeAmount(0)).toMatchObject({ dinars: '0', millimes: ',000' });
    expect(splitMillimeAmount(999)).toMatchObject({ dinars: '0', millimes: ',999' });
    expect(splitMillimeAmount(1000)).toMatchObject({ dinars: '1', millimes: ',000' });
  });

  it('marks negative amounts with a minus sign', () => {
    expect(splitMillimeAmount(-20_000)).toEqual({ sign: '−', dinars: '20', millimes: ',000' });
  });

  it.each(['fr', 'ar', 'en'] as const)('keeps the same digits as formatMillimeAmount in %s', (language) => {
    for (const value of [0, 250, 7900, 1_284_500_000]) {
      const parts = splitMillimeAmount(value);
      expect(digits(parts.dinars + parts.millimes)).toBe(digits(formatMillimeAmount(value, language)));
    }
  });

  it('rejects non-integer amounts', () => {
    expect(() => splitMillimeAmount(1.5)).toThrow();
  });
});
