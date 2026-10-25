export interface QuickAmountOption {
  id: 'whole' | number;
  amountMillimes: number;
  enabled: boolean;
}

/**
 * "Whole ticket" plus fixed chips. Fixed chips are offered only when the remaining amount
 * reaches the smallest chip, and a chip never exceeds the remaining amount (research R22).
 */
export function getQuickAmountOptions(
  remainingMillimes: number,
  chipsMillimes: readonly number[],
): QuickAmountOption[] {
  const remaining = Math.max(0, remainingMillimes);
  const smallest = Math.min(...chipsMillimes);
  return [
    { id: 'whole', amountMillimes: remaining, enabled: remaining > 0 },
    ...chipsMillimes.map((chip) => ({
      id: chip,
      amountMillimes: Math.min(chip, remaining),
      enabled: remaining > 0 && remaining >= smallest,
    })),
  ];
}
