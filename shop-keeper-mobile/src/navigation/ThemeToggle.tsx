import { Pressable } from 'react-native';
import { Icon } from '@/components/ui';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useThemeChoice } from '@/theme/useThemeChoice';

export function ThemeToggle() {
  const { t } = useLedgerTranslations();
  const { scheme, toggle } = useThemeChoice();
  const isDark = scheme === 'dark';

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={isDark ? t.switchToLight : t.switchToDark}
      className="ui-icon-button rounded-full border border-line-strong bg-surface-2 active:bg-muted"
      onPress={toggle}
    >
      <Icon name={isDark ? 'sunny' : 'moon-outline'} size={20} className="text-theme-icon" />
    </Pressable>
  );
}
