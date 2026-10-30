import { ActivityIndicator, Pressable, Text } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { Icon, IconName } from './Icon';

export type BusyButtonVariant = 'primary' | 'paid' | 'outline' | 'danger';

const VARIANTS: Record<BusyButtonVariant, { button: string; text: string }> = {
  primary: { button: 'ui-button-primary', text: 'text-on-fill' },
  paid: { button: 'ui-button-paid', text: 'text-on-fill' },
  outline: { button: 'ui-button-outline', text: 'text-ink' },
  danger: { button: 'ui-button border border-debt-line bg-surface-2 active:bg-debt-soft', text: 'text-debt' },
};

interface BusyButtonProps {
  label: string;
  busy: boolean;
  onPress: () => void;
  busyLabel?: string;
  variant?: BusyButtonVariant;
  icon?: IconName;
  disabled?: boolean;
  accessibilityLabel?: string;
  accessibilityHint?: string;
  className?: string;
}

export function BusyButton({
  label,
  busy,
  onPress,
  busyLabel,
  variant = 'primary',
  icon,
  disabled = false,
  accessibilityLabel,
  accessibilityHint,
  className = '',
}: BusyButtonProps) {
  const { t } = useLedgerTranslations();
  const colors = VARIANTS[variant];
  const inactive = busy || disabled;
  const visibleLabel = busy ? (busyLabel ?? t.saving) : label;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={busy ? visibleLabel : accessibilityLabel}
      accessibilityHint={accessibilityHint}
      accessibilityState={{ busy, disabled: inactive }}
      disabled={inactive}
      className={`${colors.button} ${busy ? 'opacity-85' : ''} ${disabled && !busy ? 'ui-button-disabled' : ''} ${className}`}
      onPress={onPress}
    >
      {busy ? (
        <ActivityIndicator size="small" className={colors.text} />
      ) : icon ? (
        <Icon name={icon} size={20} className={colors.text} />
      ) : null}
      <Text className={`type-button ${colors.text}`}>{visibleLabel}</Text>
    </Pressable>
  );
}
