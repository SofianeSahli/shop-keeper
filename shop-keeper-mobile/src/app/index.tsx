import { Link } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { Provider, useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { SupportedLanguage } from '@/i18n/types';
import { saveLanguage } from '@/i18n/languageStorage';
import { languageChanged } from '@/features/ledger/store/ledgerSlice';
import { FeedbackState } from '@/features/ledger/components/feedback/FeedbackState';
import { useState } from 'react';
import LedgerHomeScreen from './ledger';
import { SafeAreaView } from 'react-native-safe-area-context';

const languages: SupportedLanguage[] = ['fr', 'ar', 'en'];

export default function HomeScreen() {
    const { t, language } = useLedgerTranslations();

  return (
    <SafeAreaView  className={`flex-1 gap-6 bg-slate-100 p-5 ${language === 'ar' ? 'items-end' : 'items-start'}`}>
      <View className="w-full flex">
        <Text className="text-sm font-semibold uppercase tracking-wider text-blue-700">{t.offlineMode}</Text>
        <Text className="text-3xl font-extrabold text-slate-950">{t.dashboard}</Text>
      </View>
      <View className="flex-1 w-full">
        <LedgerHomeScreen ></LedgerHomeScreen>
      </View>
    </SafeAreaView >

  );
}
