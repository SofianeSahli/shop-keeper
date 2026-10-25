import { ReactNode } from 'react';
import { Text, TextInput, TextInputProps, View } from 'react-native';

interface LedgerFieldProps extends Omit<TextInputProps, 'onChangeText' | 'value'> {
  label: string;
  value: string;
  error?: string;
  hint?: string;
  /** Short text after the value, e.g. the currency. */
  suffix?: string;
  /** Element beside the input, e.g. a re-scan button. */
  accessory?: ReactNode;
  onChangeText: (value: string) => void;
}

export function LedgerField({ label, value, error, hint, suffix, accessory, onChangeText, ...inputProps }: LedgerFieldProps) {
  return (
    <View className="gap-1.5">
      <Text className="type-label">{label}</Text>
      <View className="flex-row items-center gap-2">
        <View
          className={`min-h-[50px] flex-1 flex-row items-center rounded-control border bg-surface px-4 ${
            error ? 'border-[1.5px] border-debt' : 'border-line-strong'
          }`}
        >
          <TextInput
            accessibilityLabel={label}
            accessibilityHint={error ?? hint}
            placeholderClassName="text-ink-2"
            className="min-h-[48px] flex-1 font-plex text-body text-ink"
            value={value}
            onChangeText={onChangeText}
            {...inputProps}
          />
          {suffix ? <Text className="font-plex-semibold text-body text-ink-2">{suffix}</Text> : null}
        </View>
        {accessory}
      </View>
      {error ? (
        <Text accessibilityLiveRegion="polite" className="font-plex text-label text-debt">
          {error}
        </Text>
      ) : hint ? (
        <Text className="type-caption">{hint}</Text>
      ) : null}
    </View>
  );
}
