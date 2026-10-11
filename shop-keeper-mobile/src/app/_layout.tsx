import { Provider, useDispatch } from 'react-redux';
import { Stack } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { store } from '@/store';
import { openLedgerDatabase } from '@/db';
import { getSavedLanguage } from '@/i18n/languageStorage';
import { getTextDirection } from '@/i18n';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { languageChanged } from '@/features/ledger/store/ledgerSlice';
import '../global.css';

export default function RootLayout() {
  return (
    <Provider store={store}>
      <RootContent />
    </Provider>
  );
}

function RootContent() {
  const dispatch = useDispatch();
  const { t, language } = useLedgerTranslations();
  const directionClass = getTextDirection(language) === 'rtl' ? 'ledger-rtl' : 'ledger-ltr';
  const [databaseStatus, setDatabaseStatus] = useState<'loading' | 'ready' | 'failed'>('loading');

  useEffect(() => {
    let active = true;
    void openLedgerDatabase().then(
      () => {
        if (!active) return;
        try {
          dispatch(languageChanged(getSavedLanguage()));
          setDatabaseStatus('ready');
        } catch {
          setDatabaseStatus('failed');
        }
      },
      () => {
        if (active) setDatabaseStatus('failed');
      },
    );
    return () => {
      active = false;
    };
  }, [dispatch]);

  function retryDatabase(): void {
    setDatabaseStatus('loading');
    void openLedgerDatabase().then(
      () => {
        try {
          dispatch(languageChanged(getSavedLanguage()));
          setDatabaseStatus('ready');
        } catch {
          setDatabaseStatus('failed');
        }
      },
      () => setDatabaseStatus('failed'),
    );
  }

  if (databaseStatus !== 'ready') {
    return (
      <View
        className={`flex-1 items-center justify-center gap-4 bg-slate-100 p-6 ${directionClass}`}
      >
        {databaseStatus === 'failed' ? (
          <>
            <Text accessibilityRole="alert" className="text-center text-base text-red-800">
              {t.databaseStartupFailed}
            </Text>
            <Pressable
              accessibilityRole="button"
              className="ledger-primary-button min-h-12 min-w-12 justify-center"
              onPress={retryDatabase}
            >
              <Text className="font-semibold text-white">{t.retry}</Text>
            </Pressable>
          </>
        ) : (
          <Text className="text-base text-slate-700">{t.loading}</Text>
        )}
      </View>
    );
  }

  return (
    <View className={`flex-1 ${directionClass}`}>
      <Stack screenOptions={{ headerShown: false }} />
    </View>
  );
}
