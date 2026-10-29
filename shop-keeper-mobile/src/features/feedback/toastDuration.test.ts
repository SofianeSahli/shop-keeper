import { describe, expect, it } from 'vitest';
import { toastDurationMs } from './toastDuration';

describe('toastDurationMs', () => {
  it('closes success after 3 s and info after 4 s', () => {
    expect(toastDurationMs('success')).toBe(3000);
    expect(toastDurationMs('info')).toBe(4000);
  });

  it('keeps error and pending toasts until the owner or the work ends them', () => {
    expect(toastDurationMs('error')).toBeNull();
    expect(toastDurationMs('pending')).toBeNull();
  });
});
