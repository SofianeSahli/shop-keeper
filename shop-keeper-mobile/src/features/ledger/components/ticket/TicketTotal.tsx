import { Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatMillimeAmount } from '@/lib/money';

interface TicketTotalProps {
  amountMillimes: number;
}

export function TicketTotal({ amountMillimes }: TicketTotalProps) {
  const { language } = useLedgerTranslations();
  return (
    <View accessibilityRole="summary" className="rounded-xl bg-blue-50 p-4">
      <Text className="text-lg font-bold text-blue-900">{formatMillimeAmount(amountMillimes, language)}</Text>
    </View>
  );
}
