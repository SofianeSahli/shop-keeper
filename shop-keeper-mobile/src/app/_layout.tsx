import { Provider, useDispatch } from 'react-redux';
import { Stack } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
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
import { applySavedThemeChoice } from '@/theme/applySavedThemeChoice';
import { getTextDirection } from '@/i18n';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { languageChanged } from '@/features/ledger/store/ledgerSlice';
import { TopBar } from '@/navigation/TopBar';
import { BottomTabs } from '@/navigation/BottomTabs';
import { BlockingLoader, ToastHost } from '@/components/ui';
import { TopBarProvider } from '@/navigation/TopBarContext';
import '../global.css';
import '@/components/ui/interop';

void SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  return (
    <GestureHandlerRootView className="flex-1">
      <SafeAreaProvider>
        <Provider store={store}>
          <RootContent />
        </Provider>
      </SafeAreaProvider>
    </GestureHandlerRootView>
  );
}

type DatabaseStatus = 'loading' | 'ready' | 'failed';

function RootContent() {
  const dispatch = useDispatch();
  const { t, language } = useLedgerTranslations();
  const directionClass = getTextDirection(language) === 'rtl' ? 'ledger-rtl' : 'ledger-ltr';
  const [fontsLoaded, fontError] = useFonts({
    IBMPlexSansArabic_400Regular,
    IBMPlexSansArabic_500Medium,
    IBMPlexSansArabic_600SemiBold,
    IBMPlexSansArabic_700Bold,
  });
  const [databaseStatus, setDatabaseStatus] = useState<DatabaseStatus>('loading');

  const openDatabase = useCallback((isActive: () => boolean = () => true) => {
    void openLedgerDatabase()
      .then(() => Promise.all([getSavedLanguage(), applySavedThemeChoice()]))
      .then(
        ([savedLanguage]) => {
          if (!isActive()) return;
          dispatch(languageChanged(savedLanguage));
          setDatabaseStatus('ready');
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
  const startupSettled = fontsSettled && databaseStatus !== 'loading';

  useEffect(() => {
    if (startupSettled) void SplashScreen.hideAsync();
  }, [startupSettled]);

  if (!fontsSettled || databaseStatus !== 'ready') {
    return (
      <View className={`flex-1 items-center justify-center gap-4 bg-ground p-6 ${directionClass}`}>
        {databaseStatus === 'failed' ? (
          <>
            <Text accessibilityRole="alert" className="type-body text-center text-debt-strong">
              {t.databaseStartupFailed}
            </Text>
            <Pressable accessibilityRole="button" className="ui-button-primary min-w-button" onPress={retryDatabase}>
              <Text className="type-button text-on-fill">{t.retry}</Text>
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
      <StatusBar style="auto" />
      <View className={`flex-1 bg-ground ${directionClass}`}>
        <TopBar />
        <View className="flex-1">
          <Stack screenOptions={{ headerShown: false }} />
          <ToastHost />
        </View>
        <BottomTabs />
      </View>
      <BlockingLoader />
    </TopBarProvider>
  );
}
