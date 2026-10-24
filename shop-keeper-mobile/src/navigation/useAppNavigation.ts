import { Href, useRouter } from 'expo-router';
import { useDispatch, useSelector } from 'react-redux';
import { RootState } from '@/store';
import { clearFormDirty } from '@/features/ledger/store/ledgerSlice';
import { confirmDiscard } from './confirmDiscard';
import { useLedgerTranslations } from '@/i18n/useTranslations';

export function useIsFormDirty() {
  const formDirty = useSelector((state: RootState) => state.ledger.formDirty);
  return formDirty.isDirty && formDirty.section !== null;
}

export function useAppNavigation() {
  const router = useRouter();
  const dispatch = useDispatch();
  const { t } = useLedgerTranslations();
  const isDirty = useIsFormDirty();

  /** Asks once before leaving a dirty form, then clears the flag so the screen's own guard does not ask again. */
  const guard = (action: () => void) => {
    if (!isDirty) return action();
    confirmDiscard(t, () => {
      dispatch(clearFormDirty());
      // Navigate after React commits the cleared flag, otherwise usePreventRemove still sees a dirty form.
      setTimeout(action, 0);
    });
  };

  /** Tabs and feature switches are peers: don't stack them. */
  const switchTo = (href: Href) => guard(() => router.navigate(href));

  /** Drill-down screens: keep the stack so back works. */
  const pushTo = (href: Href) => guard(() => router.push(href));

  /** Back removes the current screen; a dirty form's `useDirtyFormGuard` asks for confirmation. */
  const goBack = (fallback: Href) => (router.canGoBack() ? router.back() : router.replace(fallback));

  return { switchTo, pushTo, goBack };
}
