import { Alert } from 'react-native';
import { Href, useRouter } from 'expo-router';
import { useSelector } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { RootState } from '@/store';

function useIsFormDirty() {
  const formDirty = useSelector((state: RootState) => state.ledger.formDirty);
  return formDirty.isDirty && formDirty.section !== null;
}

export function useAppNavigation() {
  const router = useRouter();
  const { t } = useLedgerTranslations();
  const isDirty = useIsFormDirty();

  const guard = (action: () => void) => {
    if (!isDirty) return action();
    Alert.alert(t.confirmAction, '', [
      { text: t.cancel, style: 'cancel' },
      { text: t.confirm, onPress: action },
    ]);
  };

  /** Tabs and feature switches are peers: don't stack them. */
  const switchTo = (href: Href) => guard(() => router.navigate(href));

  /** Drill-down screens: keep the stack so back works. */
  const pushTo = (href: Href) => guard(() => router.push(href));

  const goBack = (fallback: Href) =>
    guard(() => (router.canGoBack() ? router.back() : router.replace(fallback)));

  return { switchTo, pushTo, goBack };
}