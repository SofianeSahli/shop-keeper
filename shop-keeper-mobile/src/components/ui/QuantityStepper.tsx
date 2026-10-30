import { Pressable, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { Icon } from './Icon';
import { clampQuantity } from './logic/quantity';

interface QuantityStepperProps {
  value: number;
  onChange: (value: number) => void;
  min?: number;
  itemLabel: string;
}

export function QuantityStepper({ value, onChange, min = 1, itemLabel }: QuantityStepperProps) {
  const { t } = useLedgerTranslations();
  const atMin = value <= min;
  const change = (delta: number) => onChange(clampQuantity(value + delta, min));

  return (
    <View className="flex-row items-center overflow-hidden rounded-control border border-line-strong bg-surface-2">
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${t.decreaseQuantity}, ${itemLabel}`}
        accessibilityState={{ disabled: atMin }}
        disabled={atMin}
        className={`h-11 w-11 items-center justify-center ${atMin ? 'opacity-40' : ''}`}
        onPress={() => change(-1)}
      >
        <Icon name="remove" size={20} />
      </Pressable>
      <Text
        accessible
        accessibilityRole="adjustable"
        accessibilityLabel={`${t.quantity}, ${itemLabel}`}
        accessibilityValue={{ now: value, min }}
        accessibilityActions={[{ name: 'increment' }, { name: 'decrement' }]}
        onAccessibilityAction={(event) => change(event.nativeEvent.actionName === 'increment' ? 1 : -1)}
        className="min-w-[36px] text-center font-plex-bold text-heading text-ink tabular-nums"
      >
        {value}
      </Text>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${t.increaseQuantity}, ${itemLabel}`}
        className="h-11 w-11 items-center justify-center"
        onPress={() => change(1)}
      >
        <Icon name="add" size={20} />
      </Pressable>
    </View>
  );
}
