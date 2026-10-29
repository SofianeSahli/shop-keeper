import { ReactNode, Ref } from 'react';
import { Text, TextInput, TextInputInstance, TextInputProps, View } from 'react-native';
import { FieldErrorText } from '@/components/ui';

interface LedgerFieldProps extends Omit<TextInputProps, 'onChangeText' | 'value'> {
  label: string;
  value: string;
  error?: string;
  hint?: string;
  suffix?: string;
  accessory?: ReactNode;
  onChangeText: (value: string) => void;
  ref?: Ref<TextInputInstance>;
}

export function LedgerField({ label, value, error, hint, suffix, accessory, onChangeText, ref, ...inputProps }: LedgerFieldProps) {
  return (
    <View className="gap-1.5">
      <Text className="type-label">{label}</Text>
      <View className="flex-row items-center gap-2">
        <View
          className={`min-h-[50px] flex-1 flex-row items-center rounded-control border bg-surface px-4 ${
            error ? 'ui-input-error' : 'border-line-strong'
          }`}
        >
          <TextInput
            ref={ref}
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
      {error ? <FieldErrorText message={error} /> : hint ? <Text className="type-caption">{hint}</Text> : null}
    </View>
  );
}
