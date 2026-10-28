import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { resolvePendingToast, showPendingToast } from '@/features/feedback/toasts';
import { seedDevelopmentLedger } from '@/features/ledger/dev/devSeed';
import { refreshCompleted } from '@/features/ledger/store/ledgerSlice';

const DEMO_TASK_MS = 3000;

function wait(milliseconds: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

export function useDevTools() {
  const dispatch = useDispatch();
  const { t } = useLedgerTranslations();

  async function runInBackground(work: () => Promise<void>, doneTitle: string): Promise<void> {
    const toastId = showPendingToast(dispatch, { title: t.demoBackgroundTask, detail: t.youCanContinue });
    try {
      await work();
      dispatch(refreshCompleted());
      resolvePendingToast(dispatch, toastId, { kind: 'success', title: doneTitle });
    } catch {
      resolvePendingToast(dispatch, toastId, { kind: 'error', title: t.operationFailed });
    }
  }

  if (!__DEV__) return null;

  return {
    runDemoTask: () => void runInBackground(() => wait(DEMO_TASK_MS), t.demoBackgroundTaskDone),
    seedLedger: () => void runInBackground(() => seedDevelopmentLedger(), t.devSeedDone),
  };
}
