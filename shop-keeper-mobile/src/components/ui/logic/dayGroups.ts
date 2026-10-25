import { addLocalDays, getLocalDateKey } from '@/lib/dates';

export type DayLabel = 'today' | 'yesterday' | 'date';

export interface DayGroup<T> {
  dayKey: string;
  label: DayLabel;
  items: T[];
}

export function getDayLabel(dayKey: string, now: Date = new Date()): DayLabel {
  if (dayKey === getLocalDateKey(now)) return 'today';
  if (dayKey === getLocalDateKey(addLocalDays(now, -1))) return 'yesterday';
  return 'date';
}

/** Groups items by device-local day, newest day first; order inside a day is kept. */
export function groupByLocalDay<T>(
  items: readonly T[],
  getDate: (item: T) => string,
  now: Date = new Date(),
): DayGroup<T>[] {
  const groups = new Map<string, T[]>();
  for (const item of items) {
    const key = getLocalDateKey(getDate(item));
    const group = groups.get(key);
    if (group) group.push(item);
    else groups.set(key, [item]);
  }
  return [...groups.entries()]
    .sort(([left], [right]) => right.localeCompare(left))
    .map(([dayKey, groupItems]) => ({ dayKey, label: getDayLabel(dayKey, now), items: groupItems }));
}
