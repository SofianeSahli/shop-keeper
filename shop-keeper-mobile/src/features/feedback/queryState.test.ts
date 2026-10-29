import { describe, expect, it } from 'vitest';
import { initialQueryState, queryFailed, QueryState, queryStarted, querySucceeded } from './queryState';

const initial: QueryState<string[]> = { ...initialQueryState };

describe('query state', () => {
  it('loads first, then is ready with the data', () => {
    const loading = queryStarted(initial, 1);
    expect(loading.status).toBe('loading');
    expect(querySucceeded(loading, 1, ['Ali'])).toEqual({ data: ['Ali'], status: 'ready', error: '', requestId: 1 });
  });

  it('keeps the previous data while refreshing', () => {
    const ready = querySucceeded(queryStarted(initial, 1), 1, ['Ali']);
    const refreshing = queryStarted(ready, 2);
    expect(refreshing).toMatchObject({ status: 'refreshing', data: ['Ali'] });
  });

  it('reports an error without data and keeps data on a failed refresh', () => {
    expect(queryFailed(queryStarted(initial, 1), 1, 'Impossible de charger')).toMatchObject({
      status: 'error',
      data: undefined,
      error: 'Impossible de charger',
    });
    const ready = querySucceeded(queryStarted(initial, 1), 1, ['Ali']);
    expect(queryFailed(queryStarted(ready, 2), 2, 'Impossible de charger')).toMatchObject({ status: 'error', data: ['Ali'] });
  });

  it('drops a result that arrives after a newer request started', () => {
    const second = queryStarted(queryStarted(initial, 1), 2);
    expect(querySucceeded(second, 1, ['stale'])).toBe(second);
    expect(queryFailed(second, 1, 'stale error')).toBe(second);
    expect(querySucceeded(second, 2, ['fresh']).data).toEqual(['fresh']);
  });
});
