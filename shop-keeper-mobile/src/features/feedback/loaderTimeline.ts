export const SHOW_AFTER_MS = 300;
export const MIN_VISIBLE_MS = 500;
export const SLOW_AFTER_MS = 2000;

export function outcomeDelayMs(finishedAt: number, shownAt: number | null): number {
  if (shownAt === null) return 0;
  return Math.max(0, shownAt + MIN_VISIBLE_MS - finishedAt);
}
