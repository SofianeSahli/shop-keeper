import { Text } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatMillimeAmount } from '@/lib/money';
import { splitMillimeAmount } from './logic/amountParts';
import { splitAmountRuns } from './logic/bidi';

export type AmountSize = 'body' | 'heading' | 'display' | 'display-lg';
export type AmountTone = 'ink' | 'debt' | 'paid';

// Millimes and the currency use about 0.65× the dinar size (design-reference §1).
const SIZE_CLASSES: Record<AmountSize, { main: string; minor: string }> = {
  body: { main: 'font-plex-bold text-body', minor: 'text-[11px]' },
  heading: { main: 'font-plex-bold text-heading', minor: 'text-[11px]' },
  display: { main: 'font-plex-bold text-display', minor: 'text-[20px]' },
  'display-lg': { main: 'font-plex-bold text-display-lg', minor: 'text-[22px]' },
};

const TONE_CLASSES: Record<AmountTone, string> = {
  ink: 'text-ink',
  debt: 'text-debt',
  paid: 'text-paid',
};

interface AmountTextProps {
  millimes: number;
  size?: AmountSize;
  tone?: AmountTone;
  /** Shows a leading minus, e.g. for payments (−20,000). */
  signed?: boolean;
  showCurrency?: boolean;
  /** Shrinks the font to keep long amounts on one line (stat and balance cards). */
  fitToWidth?: boolean;
}

export function AmountText({
  millimes,
  size = 'body',
  tone = 'ink',
  signed = false,
  showCurrency = true,
  fitToWidth = false,
}: AmountTextProps) {
  const { t, language } = useLedgerTranslations();
  const parts = splitMillimeAmount(signed ? -Math.abs(millimes) : millimes);
  const classes = SIZE_CLASSES[size];
  const runs = splitAmountRuns(parts, showCurrency ? ` ${t.currencySuffix}` : '');

  return (
    <Text
      accessibilityLabel={`${signed ? '−' : ''}${formatMillimeAmount(Math.abs(millimes), language)}`}
      numberOfLines={1}
      adjustsFontSizeToFit={fitToWidth}
      className={`${classes.main} ${TONE_CLASSES[tone]} tabular-nums`}
    >
      {runs.major}
      <Text className={`${classes.minor} font-plex-semibold`}>{runs.minor}</Text>
    </Text>
  );
}
