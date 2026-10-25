import { Pressable, ScrollView, Text } from 'react-native';

export interface FilterOption<T extends string> {
  value: T;
  label: string;
  disabled?: boolean;
}

interface FilterChipsProps<T extends string> {
  options: readonly FilterOption<T>[];
  /** `null` when no chip is selected, e.g. a typed amount that matches no quick amount. */
  value: T | null;
  onChange: (value: T) => void;
  accessibilityLabel?: string;
}

export function FilterChips<T extends string>({ options, value, onChange, accessibilityLabel }: FilterChipsProps<T>) {
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      accessibilityRole="radiogroup"
      accessibilityLabel={accessibilityLabel}
      contentContainerClassName="gap-2"
    >
      {options.map((option) => {
        const selected = option.value === value;
        const disabled = Boolean(option.disabled);
        return (
          <Pressable
            key={option.value}
            accessibilityRole="radio"
            accessibilityState={{ selected, checked: selected, disabled }}
            disabled={disabled}
            className={`min-h-touch justify-center rounded-full border px-4 ${
              selected ? 'border-ink bg-ink' : 'border-line-strong bg-surface'
            } ${disabled ? 'opacity-40' : ''}`}
            onPress={() => onChange(option.value)}
          >
            <Text className={`font-plex-semibold text-label ${selected ? 'text-surface' : 'text-ink'}`}>
              {option.label}
            </Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}
