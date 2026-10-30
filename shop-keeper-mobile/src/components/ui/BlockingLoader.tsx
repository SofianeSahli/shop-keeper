import { ActivityIndicator, Modal, Text, View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { getTextDirection } from '@/i18n';
import { useLedgerTranslations } from '@/i18n/useTranslations';

function ignoreBackPress(): void {}

export function BlockingLoader() {
  const loader = useSelector((state: RootState) => state.feedback.loader);
  const { t, language } = useLedgerTranslations();
  const direction = getTextDirection(language) === 'rtl' ? 'ledger-rtl' : 'ledger-ltr';

  if (!loader) return null;

  const title = loader.slow ? t.takingLonger : loader.label;
  const note = loader.slow ? t.doNotCloseApp : t.dataStaysOnPhone;

  return (
    <Modal
      transparent
      visible
      animationType="fade"
      statusBarTranslucent
      navigationBarTranslucent
      onRequestClose={ignoreBackPress}
    >
      <View className={`flex-1 items-center justify-center bg-scrim px-8 ${direction}`}>
        <View
          accessibilityViewIsModal
          accessible
          accessibilityRole="progressbar"
          accessibilityLabel={[title, loader.slow ? loader.label : loader.detail, note].filter(Boolean).join('. ')}
          accessibilityLiveRegion="polite"
          className="w-full max-w-[320px] items-center gap-2 rounded-loader bg-surface-2 px-6 py-7"
        >
          <ActivityIndicator size="large" className={loader.slow ? 'text-partial' : 'text-primary'} />
          <Text className="mt-2 text-center font-plex-bold text-loader-title text-ink">{title}</Text>
          {loader.slow ? (
            <Text className="text-center font-plex text-loader-detail text-ink-2">{loader.label}</Text>
          ) : loader.detail ? (
            <Text className="text-center font-plex text-loader-detail text-ink-2">{loader.detail}</Text>
          ) : null}
          <Text className="text-center font-plex text-caption text-ink-2">{note}</Text>
        </View>
      </View>
    </Modal>
  );
}
