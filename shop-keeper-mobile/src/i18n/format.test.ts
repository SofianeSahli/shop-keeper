import { describe, expect, it } from 'vitest';
import { formatMessage, getPlaceholders } from './format';

describe('formatMessage', () => {
  it('replaces string and number params', () => {
    expect(formatMessage('{count} tickets · {amount}', { count: 3, amount: '12,500 DT' })).toBe(
      '3 tickets · 12,500 DT',
    );
  });

  it('replaces a repeated param every time', () => {
    expect(formatMessage('{a} / {a}', { a: 1 })).toBe('1 / 1');
  });

  it('keeps unknown placeholders visible', () => {
    expect(formatMessage('depuis le {date}', {})).toBe('depuis le {date}');
  });

  it('returns templates without placeholders unchanged', () => {
    expect(formatMessage('Encaisser')).toBe('Encaisser');
  });
});

describe('getPlaceholders', () => {
  it('lists unique placeholder names sorted', () => {
    expect(getPlaceholders('{paid} · reste {rest} · {paid}')).toEqual(['paid', 'rest']);
  });
});
