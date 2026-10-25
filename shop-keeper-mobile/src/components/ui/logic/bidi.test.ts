import { describe, expect, it } from 'vitest';
import { isolateLtr } from './bidi';

describe('isolateLtr', () => {
  it('wraps text in a left-to-right isolate', () => {
    expect(isolateLtr('1 284 500')).toBe('⁦1 284 500⁩');
  });
});
