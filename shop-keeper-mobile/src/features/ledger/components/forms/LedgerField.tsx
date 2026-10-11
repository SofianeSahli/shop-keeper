import { Text, TextInput, TextInputProps, View } from 'react-native';

interface LedgerFieldProps extends Omit<TextInputProps, 'onChangeText' | 'value'> {
  label: string;
  value: string;
  error?: string;
  onChangeText: (value: string) => void;
}

export function LedgerField({ label, value, error, onChangeText, ...inputProps }: LedgerFieldProps) {
  return (
    <View className="gap-1">
      <Text className="text-sm font-semibold text-slate-700">{label}</Text>
      <TextInput
        accessibilityLabel={label}
        className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900"
        value={value}
        onChangeText={onChangeText}
        {...inputProps}
      />
      {error ? <Text className="text-sm text-red-700">{error}</Text> : null}
    </View>
  );
}
