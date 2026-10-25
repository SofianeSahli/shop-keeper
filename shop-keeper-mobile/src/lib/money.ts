import { getTranslations } from '@/i18n';
import { SupportedLanguage } from '@/i18n/types';

export type MillimeAmount = number;

export function isValidMillimeAmount(value: number): boolean {
  return Number.isInteger(value) && value > 0;
}

export function assertValidMillimeAmount(value: number, fieldName = 'amount'): number {
  if (!isValidMillimeAmount(value)) {
    throw new Error(`${fieldName} must be a positive integer in millimes.`);
  }

  return value;
}

export function sumMillimeAmounts(values: number[]): number {
  return values.reduce((total, value) => {
    if (!Number.isSafeInteger(value) || value < 0) {
      throw new Error('Millime values must be non-negative safe integers.');
    }
    const sum = total + value;
    if (!Number.isSafeInteger(sum)) {
      throw new Error('Millime total exceeds the supported integer range.');
    }
    return sum;
  }, 0);
}

/**
 * Locales for words (month and weekday names). `-u-nu-latn` keeps Latin digits in Arabic dates,
 * so numbers look the same in every language (spec 003, FR-022).
 */
export const locales: Record<SupportedLanguage, string> = {
  ar: 'ar-TN-u-nu-latn',
  en: 'en-TN-u-nu-latn',
  fr: 'fr-TN-u-nu-latn',
};

/** One number format for every language: Tunisian convention, e.g. "1 284 500,500" (FR-022). */
export const NUMBER_LOCALE = 'fr-TN-u-nu-latn';

export function formatMillimeNumber(value: number): string {
  if (!Number.isSafeInteger(value)) {
    throw new Error('Millime amount must be a safe integer.');
  }
  return new Intl.NumberFormat(NUMBER_LOCALE, {
    minimumFractionDigits: 3,
    maximumFractionDigits: 3,
  }).format(value / 1000);
}

export function formatMillimeAmount(value: number, language: SupportedLanguage = 'fr'): string {
  // Only the currency label changes with the language (translation key `currencySuffix`).
  return `${formatMillimeNumber(value)} ${getTranslations(language).currencySuffix}`;
}

export function formatMillimeInput(value: number): string {
  if (!Number.isSafeInteger(value)) {
    throw new Error('Millime amount must be a safe integer.');
  }
  return `${Math.floor(value / 1000)},${String(value % 1000).padStart(3, '0')}`;
}

export function formatLedgerDate(
  value: string,
  language: SupportedLanguage,
  includeTime = true,
): string {
  return new Intl.DateTimeFormat(locales[language], includeTime
    ? { dateStyle: 'medium', timeStyle: 'short' }
    : { dateStyle: 'medium' },
  ).format(new Date(value));
}

/** Short day for meta lines, e.g. "24 oct." / "24 أكتوبر". */
export function formatShortDate(value: string | Date, language: SupportedLanguage): string {
  return new Intl.DateTimeFormat(locales[language], { day: 'numeric', month: 'short' }).format(new Date(value));
}

/** Weekday and date for headings, e.g. "samedi 24 octobre". */
export function formatLongDay(value: string | Date, language: SupportedLanguage): string {
  return new Intl.DateTimeFormat(locales[language], { weekday: 'long', day: 'numeric', month: 'long' }).format(
    new Date(value),
  );
}
