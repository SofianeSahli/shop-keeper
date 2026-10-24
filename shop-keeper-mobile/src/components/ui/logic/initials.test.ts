import { describe, expect, it } from 'vitest';
import { getInitials } from './initials';

describe('getInitials', () => {
  it.each([
    ['Ali', 'A'],
    ['ali ben salah', 'AB'],
    ['  Sonia   Trabelsi ', 'ST'],
    ['محمد علي', 'مع'],
    ['(Hédi) 🙂 épicier', 'HÉ'],
    ['', '?'],
    ['123 !!', '?'],
  ])('%s -> %s', (label, expected) => {
    expect(getInitials(label)).toBe(expected);
  });
});
