import { RefObject, useEffect, useRef, useState } from 'react';
import { store } from '@/store';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { AsyncActionSpec, createAsyncActionRunner, OutcomeWording } from './asyncActionRunner';
import { dismissToast } from './toasts';

export type { AsyncActionSpec } from './asyncActionRunner';

const appRunner = createAsyncActionRunner(store.dispatch);

type Translations = ReturnType<typeof useLedgerTranslations>['t'];

function outcomeWording(t: Translations): OutcomeWording {
  return {
    notSavedTitle: (thing) => formatMessage(t.notSavedToast, { thing }),
    explainFailure: (cause) => getLedgerErrorMessage(cause, t, '') || null,
    retryLabel: t.retry,
    viewLabel: t.view,
  };
}

function dismissOwnErrorToast(errorToastId: RefObject<string | null>): void {
  const toastId = errorToastId.current;
  if (!toastId) return;
  errorToastId.current = null;
  const toast = store.getState().feedback.toasts.find((candidate) => candidate.id === toastId);
  if (toast) dismissToast(store.dispatch, toast);
}

export function useAsyncAction<T>(buildSpec: () => AsyncActionSpec<T>) {
  const { t } = useLedgerTranslations();
  const [busy, setBusy] = useState(false);
  const latest = useRef({ buildSpec, t });
  const errorToastId = useRef<string | null>(null);

  useEffect(() => {
    latest.current = { buildSpec, t };
  });

  useEffect(() => () => dismissOwnErrorToast(errorToastId), []);

  async function run(): Promise<T | undefined> {
    if (appRunner.isRunning()) return undefined;
    dismissOwnErrorToast(errorToastId);
    try {
      return await appRunner.run(latest.current.buildSpec(), {
        wording: outcomeWording(latest.current.t),
        retry: () => {
          void run();
        },
        onErrorToast: (toastId) => {
          errorToastId.current = toastId;
        },
        onStarted: () => setBusy(true),
      });
    } finally {
      setBusy(false);
    }
  }

  return { run, busy };
}
