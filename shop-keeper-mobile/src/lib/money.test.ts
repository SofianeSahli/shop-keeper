import { describe, expect, it } from 'vitest';
import {
  assertValidMillimeAmount,
  formatLedgerDate,
  formatMillimeAmount,
  formatMillimeInput,
  isValidMillimeAmount,
  sumMillimeAmounts,
} from './money';

describe('millime utilities', () => {
  it('accepts only positive integer amounts', () => {
    expect(isValidMillimeAmount(1250)).toBe(true);
    expect(isValidMillimeAmount(0)).toBe(false);
    expect(isValidMillimeAmount(1.5)).toBe(false);
    expect(() => assertValidMillimeAmount(-1)).toThrow(/positive integer/);
  });

  it('sums safe millime values and rejects overflow', () => {
    expect(sumMillimeAmounts([1250, 750])).toBe(2000);
    expect(() => sumMillimeAmounts([Number.MAX_SAFE_INTEGER, 1])).toThrow(/integer range/);
  });

  it('formats money, input values and dates for the selected locale', () => {
    expect(formatMillimeInput(1234)).toBe('1.234');
    expect(formatMillimeAmount(1234, 'fr')).toContain('1');
    expect(formatMillimeAmount(1234, 'ar')).not.toBe(formatMillimeAmount(1234, 'fr'));
    expect(formatLedgerDate('2025-03-04T12:00:00.000Z', 'fr')).toContain('2025');
    expect(formatLedgerDate('2025-03-04T12:00:00.000Z', 'ar')).not.toBe(
      formatLedgerDate('2025-03-04T12:00:00.000Z', 'fr'),
    );
  });
});
