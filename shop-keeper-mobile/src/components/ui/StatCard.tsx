import { Pressable, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatMillimeAmount } from '@/lib/money';
import { AmountText } from './AmountText';

interface StatCardProps {
  caption: string;
  amountMillimes: number;
  meta: string;
  tone?: 'neutral' | 'debt';
  onPress?: () => void;
  accessibilityHint?: string;
}

export function StatCard({ caption, amountMillimes, meta, tone = 'neutral', onPress, accessibilityHint }: StatCardProps) {
  const { language } = useLedgerTranslations();
  const isDebt = tone === 'debt';
  const content = (
    <>
      <Text numberOfLines={2} className={`type-label ${isDebt ? 'text-debt-strong' : ''}`}>
        {caption}
      </Text>
      <AmountText millimes={amountMillimes} size="display" tone={isDebt ? 'debt' : 'ink'} fitToWidth />
      <Text numberOfLines={2} className={`type-caption ${isDebt ? 'text-debt-strong' : ''}`}>
        {meta}
      </Text>
    </>
  );
  const className = `flex-1 gap-1 rounded-card border p-4 ${
    isDebt ? 'border-debt-line bg-debt-soft' : 'border-line bg-surface'
  }`;
  const label = `${caption}, ${formatMillimeAmount(amountMillimes, language)}, ${meta}`;

  if (!onPress) {
    return (
      <View accessible accessibilityLabel={label} className={className}>
        {content}
      </View>
    );
  }
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityHint={accessibilityHint}
      className={`${className} active:opacity-80`}
      onPress={onPress}
    >
      {content}
    </Pressable>
  );
}
