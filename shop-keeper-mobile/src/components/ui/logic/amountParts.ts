import { NUMBER_LOCALE } from '@/lib/money';

export interface AmountParts {
  sign: '' | '−';
  /** Whole dinars with locale grouping, e.g. "1 284 500". */
  dinars: string;
  /** Locale decimal separator followed by the three millime digits, e.g. ",500". */
  millimes: string;
}

/**
 * Splits an integer millime amount so dinars and millimes can be shown at different sizes.
 * Numbers use one format in every language (spec 003, FR-022).
 */
export function splitMillimeAmount(value: number): AmountParts {
  if (!Number.isSafeInteger(value)) {
    throw new Error('Millime amount must be a safe integer.');
  }
  const locale = NUMBER_LOCALE;
  const absolute = Math.abs(value);
  const dinars = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 }).format(
    Math.floor(absolute / 1000),
  );
  // 1.5 formatted as "1,5" or "1.5" exposes the locale decimal separator without formatToParts.
  const decimalSeparator = new Intl.NumberFormat(locale).format(1.5).replace(/\d/g, '') || ',';
  return {
    sign: value < 0 ? '−' : '',
    dinars,
    millimes: `${decimalSeparator}${String(absolute % 1000).padStart(3, '0')}`,
  };
}
