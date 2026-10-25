import { describe, expect, it } from 'vitest';
import { getQuickAmountOptions } from './quickAmounts';

const chips = [10_000, 20_000, 50_000];

describe('getQuickAmountOptions', () => {
  it('clamps chips larger than the remaining amount', () => {
    expect(getQuickAmountOptions(15_000, chips)).toEqual([
      { id: 'whole', amountMillimes: 15_000, enabled: true },
      { id: 10_000, amountMillimes: 10_000, enabled: true },
      { id: 20_000, amountMillimes: 15_000, enabled: true },
      { id: 50_000, amountMillimes: 15_000, enabled: true },
    ]);
  });

  it('enables only the whole ticket under 10 DT', () => {
    const options = getQuickAmountOptions(8_000, chips);
    expect(options.filter((option) => option.enabled).map((option) => option.id)).toEqual(['whole']);
  });

  it('disables everything when nothing remains', () => {
    expect(getQuickAmountOptions(0, chips).some((option) => option.enabled)).toBe(false);
  });
});
