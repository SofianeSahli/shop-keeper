import { Text } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { locales } from '@/lib/money';
import { getDayLabel } from './logic/dayGroups';

interface DayHeaderProps {
  /** Local day as `YYYY-MM-DD`. */
  dayKey: string;
  now?: Date;
}

export function DayHeader({ dayKey, now }: DayHeaderProps) {
  const { t, language } = useLedgerTranslations();
  const label = getDayLabel(dayKey, now);
  const [year, month, day] = dayKey.split('-').map(Number);
  const text =
    label === 'today'
      ? t.today
      : label === 'yesterday'
        ? t.yesterday
        : new Intl.DateTimeFormat(locales[language], { weekday: 'long', day: 'numeric', month: 'long' }).format(
            new Date(year, month - 1, day),
          );

  return (
    <Text accessibilityRole="header" className="type-label px-1 pt-2 uppercase tracking-wide">
      {text}
    </Text>
  );
}
