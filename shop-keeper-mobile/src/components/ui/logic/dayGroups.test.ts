import { describe, expect, it } from 'vitest';
import { groupByLocalDay } from './dayGroups';

const local = (month: number, day: number, hour = 12, minute = 0) =>
  new Date(2026, month - 1, day, hour, minute).toISOString();

describe('groupByLocalDay', () => {
  const now = new Date(2026, 9, 24, 9);

  it('labels today, yesterday and older days, newest first', () => {
    const items = [
      { id: 'old', at: local(10, 20) },
      { id: 'today', at: local(10, 24, 8) },
      { id: 'yesterday', at: local(10, 23) },
    ];
    const groups = groupByLocalDay(items, (item) => item.at, now);
    expect(groups.map((group) => [group.dayKey, group.label])).toEqual([
      ['2026-10-24', 'today'],
      ['2026-10-23', 'yesterday'],
      ['2026-10-20', 'date'],
    ]);
  });

  it('splits at local midnight', () => {
    const items = [
      { id: 'late', at: local(10, 23, 23, 59) },
      { id: 'early', at: local(10, 24, 0, 1) },
    ];
    const groups = groupByLocalDay(items, (item) => item.at, now);
    expect(groups.map((group) => group.items.map((item) => item.id))).toEqual([['early'], ['late']]);
  });

  it('keeps the input order inside a day', () => {
    const items = [
      { id: 'b', at: local(10, 24, 11) },
      { id: 'a', at: local(10, 24, 10) },
    ];
    expect(groupByLocalDay(items, (item) => item.at, now)[0].items.map((item) => item.id)).toEqual(['b', 'a']);
  });

  it('returns no groups for no items', () => {
    expect(groupByLocalDay([], () => '', now)).toEqual([]);
  });
});
