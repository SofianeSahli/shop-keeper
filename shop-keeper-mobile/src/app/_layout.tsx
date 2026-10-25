import { Provider, useDispatch } from 'react-redux';
import { Stack } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import {
  IBMPlexSansArabic_400Regular,
  IBMPlexSansArabic_500Medium,
  IBMPlexSansArabic_600SemiBold,
  IBMPlexSansArabic_700Bold,
  useFonts,
} from '@expo-google-fonts/ibm-plex-sans-arabic';
import { store } from '@/store';
import { openLedgerDatabase } from '@/db';
import { getSavedLanguage } from '@/i18n/languageStorage';
import { getTextDirection } from '@/i18n';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { languageChanged } from '@/features/ledger/store/ledgerSlice';
import { TopBar } from '@/navigation/TopBar';
import { BottomTabs } from '@/navigation/BottomTabs';
import { TopBarProvider } from '@/navigation/TopBarContext';
import '../global.css';
import '@/components/ui/interop';

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <Provider store={store}>
        <RootContent />
      </Provider>
    </SafeAreaProvider>
  );
}

type DatabaseStatus = 'loading' | 'ready' | 'failed';

function RootContent() {
  const dispatch = useDispatch();
  const { t, language } = useLedgerTranslations();
  const directionClass = getTextDirection(language) === 'rtl' ? 'ledger-rtl' : 'ledger-ltr';
  // A font that fails to load leaves the system font in place (spec edge case), so errors also unblock the app.
  const [fontsLoaded, fontError] = useFonts({
    IBMPlexSansArabic_400Regular,
    IBMPlexSansArabic_500Medium,
    IBMPlexSansArabic_600SemiBold,
    IBMPlexSansArabic_700Bold,
  });
  const [databaseStatus, setDatabaseStatus] = useState<DatabaseStatus>('loading');

  const openDatabase = useCallback((isActive: () => boolean = () => true) => {
    void openLedgerDatabase().then(
      () => {
        if (!isActive()) return;
        try {
          dispatch(languageChanged(getSavedLanguage()));
          setDatabaseStatus('ready');
        } catch {
          setDatabaseStatus('failed');
        }
      },
      () => {
        if (isActive()) setDatabaseStatus('failed');
      },
    );
  }, [dispatch]);

  useEffect(() => {
    let active = true;
    openDatabase(() => active);
    return () => {
      active = false;
    };
  }, [openDatabase]);

  function retryDatabase(): void {
    setDatabaseStatus('loading');
    openDatabase();
  }

  const fontsSettled = fontsLoaded || Boolean(fontError);

  if (!fontsSettled || databaseStatus !== 'ready') {
    return (
      <View className={`flex-1 items-center justify-center gap-4 bg-ground p-6 ${directionClass}`}>
        {databaseStatus === 'failed' ? (
          <>
            <Text accessibilityRole="alert" className="type-body text-center text-debt-strong">
              {t.databaseStartupFailed}
            </Text>
            <Pressable accessibilityRole="button" className="ui-button-primary min-w-button" onPress={retryDatabase}>
              <Text className="type-button text-surface">{t.retry}</Text>
            </Pressable>
          </>
        ) : (
          <Text accessibilityRole="progressbar" className="type-body text-ink-2">
            {t.loading}
          </Text>
        )}
      </View>
    );
  }

  return (
    <TopBarProvider>
      <View className={`flex-1 bg-ground ${directionClass}`}>
        <TopBar />
        <View className="flex-1">
          <Stack screenOptions={{ headerShown: false }} />
        </View>
        <BottomTabs />
      </View>
    </TopBarProvider>
  );
}
