/** Keeps a stepper quantity a safe integer no lower than `min`. */
export function clampQuantity(next: number, min = 1): number {
  if (!Number.isFinite(next)) return min;
  return Math.min(Math.max(Math.trunc(next), min), Number.MAX_SAFE_INTEGER);
}
