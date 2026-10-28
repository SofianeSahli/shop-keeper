import { ActivityIndicator, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';

export function RefreshingPill({ visible }: { visible: boolean }) {
  const { t } = useLedgerTranslations();
  if (!visible) return null;
  return (
    <View accessible accessibilityRole="progressbar" accessibilityLabel={t.updating} className="ui-pill bg-primary-faint">
      <ActivityIndicator size="small" className="h-3 w-3 text-primary" />
      <Text className="font-plex-semibold text-caption text-primary">{t.updating}</Text>
    </View>
  );
}
