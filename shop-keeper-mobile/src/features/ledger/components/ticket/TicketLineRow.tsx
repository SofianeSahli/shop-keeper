import { Pressable, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatMessage } from '@/i18n/format';
import { formatMillimeAmount } from '@/lib/money';
import { Icon, QuantityStepper, isolateLtr } from '@/components/ui';
import { calculateLineTotal } from '../../services/ticketDraftService';
import { TicketDraftItem } from '../../types/ticket';

interface TicketLineRowProps {
  item: TicketDraftItem;
  separated?: boolean;
  onQuantityChange: (quantity: number) => void;
  onRemove: () => void;
}

export function TicketLineRow({ item, separated = false, onQuantityChange, onRemove }: TicketLineRowProps) {
  const { t, language } = useLedgerTranslations();
  const formula = formatMessage(t.lineFormula, {
    unit: formatMillimeAmount(item.unitPriceMillimes, language),
    quantity: item.quantity,
    total: formatMillimeAmount(calculateLineTotal(item.unitPriceMillimes, item.quantity), language),
  });

  return (
    <View className={`min-h-row flex-row items-center gap-2 py-3 ps-4 pe-2 ${separated ? 'border-t border-line-soft' : ''}`}>
      <View className="flex-1 gap-0.5">
        <Text numberOfLines={1} className="type-body-strong">
          {item.productName}
        </Text>
        <Text numberOfLines={1} className="type-caption tabular-nums">
          {isolateLtr(formula)}
        </Text>
      </View>
      <QuantityStepper value={item.quantity} onChange={onQuantityChange} itemLabel={item.productName} />
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${t.removeLine}, ${item.productName}`}
        className="ui-icon-button"
        onPress={onRemove}
      >
        <Icon name="close" size={20} className="text-ink-2" />
      </Pressable>
    </View>
  );
}
