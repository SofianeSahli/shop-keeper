import { usePreventRemove } from 'expo-router';
import { useDispatch, useSelector, useStore } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { confirmDiscard } from '@/navigation/confirmDiscard';
import { useIsFormDirty } from '@/navigation/useAppNavigation';
import { clearFormDirty } from '../store/ledgerSlice';

export function useDirtyFormGuard(): void {
  const dispatch = useDispatch();
  const store = useStore<RootState>();
  const { t } = useLedgerTranslations();
  const isDirty = useIsFormDirty();
  const actionRunning = useSelector((state: RootState) => state.feedback.actionRunning);

  usePreventRemove(isDirty || actionRunning, ({ repeat }) => {
    if (store.getState().feedback.actionRunning) return;
    if (!store.getState().ledger.formDirty.isDirty) {
      repeat();
      return;
    }
    confirmDiscard(t, () => {
      dispatch(clearFormDirty());
      repeat();
    });
  });
}
