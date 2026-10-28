import { useCallback, useEffect, useRef, useState } from 'react';
import { useIsFocused } from 'expo-router';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { initialQueryState, queryFailed, QueryState, queryStarted, querySucceeded, QueryStatus } from './queryState';

export interface LedgerQuery<T> {
  data: T | undefined;
  status: QueryStatus;
  error: string;
  retry: () => void;
}

type QueryKey = readonly (string | number | boolean | null | undefined)[];

export function useLedgerQuery<T>(load: () => Promise<T>, key: QueryKey = []): LedgerQuery<T> {
  const { t } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const isFocused = useIsFocused();
  const [state, setState] = useState<QueryState<T>>(initialQueryState);
  const latestLoad = useRef(load);
  const latestTranslations = useRef(t);
  const lastRequestId = useRef(0);
  const isMounted = useRef(true);
  const keyText = key.map(String).join('|');

  useEffect(() => {
    latestLoad.current = load;
    latestTranslations.current = t;
  });

  useEffect(() => {
    isMounted.current = true;
    return () => {
      isMounted.current = false;
    };
  }, []);

  const retry = useCallback(() => {
    lastRequestId.current += 1;
    const requestId = lastRequestId.current;
    setState((current) => queryStarted(current, requestId));
    latestLoad.current().then(
      (data) => {
        if (isMounted.current) setState((current) => querySucceeded(current, requestId, data));
      },
      (cause: unknown) => {
        if (!isMounted.current) return;
        const translations = latestTranslations.current;
        const message = getLedgerErrorMessage(cause, translations, translations.loadFailed);
        setState((current) => queryFailed(current, requestId, message));
      },
    );
  }, []);

  useEffect(() => {
    if (isFocused) retry();
  }, [isFocused, retry, refreshVersion, keyText]);

  return { data: state.data, status: state.status, error: state.error, retry };
}
