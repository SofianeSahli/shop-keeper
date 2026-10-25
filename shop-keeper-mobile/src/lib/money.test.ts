import { describe, expect, it } from 'vitest';
import {
  assertValidMillimeAmount,
  formatLedgerDate,
  formatLongDay,
  formatMillimeAmount,
  formatMillimeInput,
  formatMillimeNumber,
  formatShortDate,
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
    expect(formatMillimeInput(1234)).toBe('1,234');
    expect(formatMillimeAmount(1234, 'fr')).toContain('1');
    expect(formatLedgerDate('2025-03-04T12:00:00.000Z', 'fr')).toContain('2025');
    expect(formatLedgerDate('2025-03-04T12:00:00.000Z', 'ar')).not.toBe(
      formatLedgerDate('2025-03-04T12:00:00.000Z', 'fr'),
    );
  });

  it('writes numbers the same way in every language; only the currency label changes (FR-022)', () => {
    const grouped = '1 284 500,500';
    expect(formatMillimeNumber(1_284_500_500)).toBe(grouped);
    expect(formatMillimeAmount(1_284_500_500, 'fr')).toBe(`${grouped} DT`);
    expect(formatMillimeAmount(1_284_500_500, 'en')).toBe(`${grouped} DT`);
    expect(formatMillimeAmount(1_284_500_500, 'ar')).toBe(`${grouped} د.ت`);
    expect(formatMillimeAmount(-20_000, 'ar')).toBe('-20,000 د.ت');
  });

  it('keeps Latin digits in Arabic dates', () => {
    for (const formatted of [
      formatLedgerDate('2026-10-24T12:00:00.000Z', 'ar'),
      formatShortDate('2026-10-24T12:00:00.000Z', 'ar'),
      formatLongDay('2026-10-24T12:00:00.000Z', 'ar'),
    ]) {
      expect(formatted).toMatch(/24/);
      expect(formatted).not.toMatch(/[٠-٩۰-۹]/);
    }
  });
});
