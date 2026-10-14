import { Link } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { LedgerPage } from '@/features/ledger/components/LedgerPage';
import { getDashboardSummary } from '@/features/ledger/services/dashboardService';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatMillimeAmount } from '@/lib/money';



export default function LedgerHomeScreen() {
  const { t, language } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const summary = getDashboardSummary();

  return (
    <LedgerPage title={t.home} subtitle={t.offlineMode} key={String(refreshVersion)}>
      <View className="gap-4">
        <View className="ledger-card gap-3">
          <Text className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t.todaySales}</Text>
          <Text className="text-3xl font-bold text-slate-950">
            {formatMillimeAmount(summary.todaySalesMillimes, language)}
          </Text>
        </View>
        <View className="ledger-card gap-3">
          <Text className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t.outstandingDebt}</Text>
          <Text className="text-3xl font-bold text-blue-800">
            {formatMillimeAmount(summary.outstandingBalanceMillimes, language)}
          </Text>
        </View>
      </View>

      <View className="gap-3">
        <Text className="text-lg font-semibold text-slate-900">{t.quickActions}</Text>
        <View className="gap-3">
          <Link href="/ledger/product-form" asChild>
            <Pressable accessibilityRole="button" className="ledger-primary-button">
              <Text className="font-semibold text-white">{t.newProduct}</Text>
            </Pressable>
          </Link>
          <Link href="/ledger/ticket-form" asChild>
            <Pressable accessibilityRole="button" className="ledger-primary-button">
              <Text className="font-semibold text-white">{t.newTicket}</Text>
            </Pressable>
          </Link>
          <Link href="/ledger/payment-form" asChild>
            <Pressable accessibilityRole="button" className="ledger-primary-button">
              <Text className="font-semibold text-white">{t.recordPayment}</Text>
            </Pressable>
          </Link>
        </View>
      </View>
    </LedgerPage>
  );
}
