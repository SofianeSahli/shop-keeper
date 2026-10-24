import { Alert } from 'react-native';
import type { getTranslations } from '@/i18n';

/** The single confirmation shown before unsaved form values are discarded. */
export function confirmDiscard(t: ReturnType<typeof getTranslations>, onDiscard: () => void): void {
  Alert.alert(t.confirmAction, '', [
    { text: t.cancel, style: 'cancel' },
    { text: t.confirm, style: 'destructive', onPress: onDiscard },
  ]);
}
