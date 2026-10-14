import { KeyboardTypeOptions } from 'react-native';
import { LedgerField } from './LedgerField';

interface MillimeInputProps {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  error?: string;
}

export function parseMillimes(value: string): number {
  const match = /^(\d+)(?:[.,](\d{1,3}))?$/.exec(value.trim());
  if (!match) {
    throw new Error('Enter a valid amount with up to three decimal places.');
  }
  const whole = Number(match[1]);
  const fraction = Number((match[2] ?? '').padEnd(3, '0'));
  const amount = whole * 1000 + fraction;
  if (!Number.isSafeInteger(amount) || amount <= 0) {
    throw new Error('Amount must be greater than zero.');
  }
  return amount;
}

export function MillimeInput({ label, value, onChangeText, error }: MillimeInputProps) {
  return (
    <LedgerField
      label={label}
      value={value}
      onChangeText={onChangeText}
      error={error}
      keyboardType={'decimal-pad' as KeyboardTypeOptions}
      placeholder="0.000"
    />
  );
}
