import { Pressable, Text, View } from 'react-native';

export type SegmentTone = 'paid' | 'partial' | 'debt';

export interface SegmentOption<T extends string> {
  value: T;
  label: string;
  tone: SegmentTone;
}

const SELECTED: Record<SegmentTone, { container: string; text: string }> = {
  paid: { container: 'bg-paid-fill border-paid-fill', text: 'text-on-fill' },
  partial: { container: 'bg-partial-soft border-partial-line', text: 'text-partial' },
  debt: { container: 'bg-debt-soft border-debt-line', text: 'text-debt' },
};

interface SegmentedControlProps<T extends string> {
  options: readonly SegmentOption<T>[];
  value: T;
  onChange: (value: T) => void;
  accessibilityLabel: string;
}

export function SegmentedControl<T extends string>({ options, value, onChange, accessibilityLabel }: SegmentedControlProps<T>) {
  return (
    <View accessibilityRole="radiogroup" accessibilityLabel={accessibilityLabel} className="flex-row gap-2">
      {options.map((option) => {
        const selected = option.value === value;
        const colors = SELECTED[option.tone];
        return (
          <Pressable
            key={option.value}
            accessibilityRole="radio"
            accessibilityState={{ selected, checked: selected }}
            className={`min-h-touch flex-1 items-center justify-center rounded-control border ${
              selected ? colors.container : 'border-line-strong bg-surface-2'
            }`}
            onPress={() => onChange(option.value)}
          >
            <Text className={`font-plex-bold text-body ${selected ? colors.text : 'text-ink-2'}`}>{option.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}
