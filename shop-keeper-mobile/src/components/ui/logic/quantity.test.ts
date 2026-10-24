import { describe, expect, it } from 'vitest';
import { clampQuantity } from './quantity';

describe('clampQuantity', () => {
  it('never goes below the minimum', () => {
    expect(clampQuantity(0)).toBe(1);
    expect(clampQuantity(-4)).toBe(1);
    expect(clampQuantity(2, 3)).toBe(3);
  });

  it('keeps integers and truncates fractions', () => {
    expect(clampQuantity(5)).toBe(5);
    expect(clampQuantity(2.7)).toBe(2);
  });

  it('handles invalid and huge values', () => {
    expect(clampQuantity(Number.NaN)).toBe(1);
    expect(clampQuantity(Number.MAX_SAFE_INTEGER + 10)).toBe(Number.MAX_SAFE_INTEGER);
  });
});
