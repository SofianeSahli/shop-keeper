import { Ref } from 'react';
import { KeyboardTypeOptions, TextInputInstance } from 'react-native';
import { LedgerField } from './LedgerField';

interface MillimeInputProps {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  error?: string;
  hint?: string;
  suffix?: string;
  ref?: Ref<TextInputInstance>;
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

export function isValidMillimeText(value: string): boolean {
  try {
    parseMillimes(value);
    return true;
  } catch {
    return false;
  }
}

export function MillimeInput({ label, value, onChangeText, error, hint, suffix, ref }: MillimeInputProps) {
  return (
    <LedgerField
      ref={ref}
      label={label}
      value={value}
      onChangeText={onChangeText}
      error={error}
      hint={hint}
      suffix={suffix}
      keyboardType={'decimal-pad' as KeyboardTypeOptions}
      placeholder="0,000"
      textAlign="left"
    />
  );
}
