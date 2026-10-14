import { Link } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { SupportedLanguage } from '@/i18n/types';
import { saveLanguage } from '@/i18n/languageStorage';
import { languageChanged } from '@/features/ledger/store/ledgerSlice';
import { FeedbackState } from '@/features/ledger/components/feedback/FeedbackState';
import { useState } from 'react';

const languages: SupportedLanguage[] = ['fr', 'ar', 'en'];

export default function HomeScreen() {
  const dispatch = useDispatch();
  const { t, language } = useLedgerTranslations();
  const [error, setError] = useState('');

  function changeLanguage(next: SupportedLanguage): void {
    try {
      saveLanguage(next);
      dispatch(languageChanged(next));
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  return (
    <View className={`flex-1 gap-6 bg-slate-100 p-5 ${language === 'ar' ? 'items-end' : 'items-start'}`}>
      <View className="gap-2">
        <Text className="text-sm font-semibold uppercase tracking-wider text-blue-700">{t.offlineMode}</Text>
        <Text className="text-3xl font-extrabold text-slate-950">{t.dashboard}</Text>
      </View>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      <View className="w-full gap-3">
        <Text className="font-semibold text-slate-800">{t.switchLanguage}</Text>
        <View className="flex-row gap-2">
          {languages.map((option) => (
            <Pressable
              key={option}
              accessibilityRole="button"
              accessibilityState={{ selected: language === option }}
              className={`ledger-touch-target rounded-lg px-4 py-2 ${language === option ? 'bg-blue-700' : 'bg-white'}`}
              onPress={() => changeLanguage(option)}
            >
              <Text className={language === option ? 'font-semibold text-white' : 'text-slate-800'}>
                {option.toUpperCase()}
              </Text>
            </Pressable>
          ))}
        </View>
      </View>
      <Link href="./ledger" asChild>
        <Pressable accessibilityRole="button" className="ledger-primary-button w-full">
          <Text className="font-semibold text-white">{t.dashboard}</Text>
        </Pressable>
      </Link>
    </View>
  );
}
