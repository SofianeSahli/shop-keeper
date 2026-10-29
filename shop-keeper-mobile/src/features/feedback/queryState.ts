export type QueryStatus = 'loading' | 'refreshing' | 'ready' | 'error';

export interface QueryState<T> {
  data: T | undefined;
  status: QueryStatus;
  error: string;
  requestId: number;
}

export const initialQueryState = { data: undefined, status: 'loading', error: '', requestId: 0 } as const;

export function queryStarted<T>(state: QueryState<T>, requestId: number): QueryState<T> {
  return { ...state, requestId, error: '', status: state.data === undefined ? 'loading' : 'refreshing' };
}

export function querySucceeded<T>(state: QueryState<T>, requestId: number, data: T): QueryState<T> {
  if (requestId !== state.requestId) return state;
  return { data, requestId, status: 'ready', error: '' };
}

export function queryFailed<T>(state: QueryState<T>, requestId: number, error: string): QueryState<T> {
  if (requestId !== state.requestId) return state;
  return { ...state, status: 'error', error };
}
