import { Link } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { LedgerPage } from '@/features/ledger/components/LedgerPage';
import { useLedgerTranslations } from '@/i18n/useTranslations';

const destinations = [
  { label: 'products', href: '/ledger/products' },
  { label: 'customers', href: '/ledger/customers' },
  { label: 'tickets', href: '/ledger/tickets' },
  { label: 'balances', href: '/ledger/balances' },
  { label: 'paymentHistory', href: '/ledger/payments' },
  { label: 'history', href: '/ledger/history' },
] as const;

export default function LedgerHomeScreen() {
  const { t } = useLedgerTranslations();
  return (
    <LedgerPage title={t.dashboard} subtitle={t.offlineMode}>
      <View className="gap-3">
        {destinations.map(({ label, href }) => (
          <Link key={href} href={href} asChild>
            <Pressable accessibilityRole="button" className="ledger-card ledger-touch-target">
              <Text className="text-base font-semibold text-slate-900">{t[label]}</Text>
            </Pressable>
          </Link>
        ))}
      </View>
    </LedgerPage>
  );
}
