import { describe, expect, it } from 'vitest';
import { isolateLtr, splitAmountRuns } from './bidi';

describe('isolateLtr', () => {
  it('wraps text in a left-to-right isolate', () => {
    expect(isolateLtr('1 284 500')).toBe('⁦1 284 500⁩');
  });
});

describe('splitAmountRuns', () => {
  it('wraps the whole number in a single isolate, across both runs, with the currency outside', () => {
    const { major, minor } = splitAmountRuns({ sign: '', dinars: '8', millimes: ',600' }, ' د.ت');
    expect(major + minor).toBe('⁦8,600⁩ د.ت');
    expect(major).toBe('⁦8');
    expect(minor.startsWith(',600⁩')).toBe(true);
  });

  it('keeps the minus sign inside the isolate', () => {
    const { major, minor } = splitAmountRuns({ sign: '−', dinars: '20', millimes: ',000' }, ' DT');
    expect(major + minor).toBe('⁦−20,000⁩ DT');
  });
});
