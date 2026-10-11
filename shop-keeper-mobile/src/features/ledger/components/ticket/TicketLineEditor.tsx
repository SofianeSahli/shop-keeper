import { Pressable, Text, View } from 'react-native';
import { LedgerField } from '../forms/LedgerField';
import { TicketDraftItem } from '../../types/ticket';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatMillimeAmount } from '@/lib/money';

interface TicketLineEditorProps {
  item: TicketDraftItem;
  onQuantityChange: (quantity: number) => void;
  onRemove: () => void;
  removeLabel: string;
  quantityLabel: string;
}

export function TicketLineEditor({
  item,
  onQuantityChange,
  onRemove,
  removeLabel,
  quantityLabel,
}: TicketLineEditorProps) {
  const { language } = useLedgerTranslations();
  return (
    <View className="gap-3 rounded-xl border border-slate-200 bg-white p-4">
      <View className="flex-row items-start justify-between gap-3">
        <View className="flex-1">
          <Text className="font-semibold text-slate-900">{item.productName}</Text>
          <Text className="text-sm text-slate-600">{formatMillimeAmount(item.unitPriceMillimes, language)}</Text>
        </View>
        <Pressable accessibilityRole="button" className="ledger-touch-target" onPress={onRemove}>
          <Text className="font-medium text-red-700">{removeLabel}</Text>
        </Pressable>
      </View>
      <LedgerField
        label={quantityLabel}
        value={String(item.quantity)}
        onChangeText={(value) => {
          const quantity = Number(value);
          if (Number.isInteger(quantity) && quantity > 0) onQuantityChange(quantity);
        }}
        keyboardType="number-pad"
      />
      <Text className="text-right font-semibold text-slate-900">
        {formatMillimeAmount(item.unitPriceMillimes * item.quantity, language)}
      </Text>
    </View>
  );
}
