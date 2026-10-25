import { usePreventRemove } from 'expo-router';
import { useDispatch, useStore } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { confirmDiscard } from '@/navigation/confirmDiscard';
import { useIsFormDirty } from '@/navigation/useAppNavigation';
import { clearFormDirty } from '../store/ledgerSlice';

/**
 * Asks for confirmation whenever a dirty form screen is about to be removed: Android system back,
 * iOS swipe or the top-bar back button (spec 002 leftover, FR-013).
 *
 * Expo Router 58: to continue the blocked action, the condition must turn false (the dirty flag is
 * cleared) and the callback's `repeat` is called.
 */
export function useDirtyFormGuard(): void {
  const dispatch = useDispatch();
  const store = useStore<RootState>();
  const { t } = useLedgerTranslations();
  const isDirty = useIsFormDirty();

  usePreventRemove(isDirty, ({ repeat }) => {
    // A save clears the flag right before navigating, before this hook re-renders: read the live state.
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
