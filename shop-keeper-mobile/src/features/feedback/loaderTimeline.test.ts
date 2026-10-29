import { describe, expect, it } from 'vitest';
import { MIN_VISIBLE_MS, outcomeDelayMs, SHOW_AFTER_MS, SLOW_AFTER_MS } from './loaderTimeline';

describe('loader timeline', () => {
  it('uses the design thresholds', () => {
    expect([SHOW_AFTER_MS, MIN_VISIBLE_MS, SLOW_AFTER_MS]).toEqual([300, 500, 2000]);
  });

  it('shows the outcome at once when the loader never appeared', () => {
    expect(outcomeDelayMs(250, null)).toBe(0);
  });

  it('keeps a shown loader at least 500 ms', () => {
    expect(outcomeDelayMs(400, 300)).toBe(400);
    expect(outcomeDelayMs(800, 300)).toBe(0);
    expect(outcomeDelayMs(1500, 300)).toBe(0);
  });
});
