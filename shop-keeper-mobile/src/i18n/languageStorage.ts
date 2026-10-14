import { getLedgerDatabase } from '@/db';
import { SupportedLanguage } from './types';

const supportedLanguages: SupportedLanguage[] = ['ar', 'en', 'fr'];

function isSupportedLanguage(value: string | undefined): value is SupportedLanguage {
  return value !== undefined && supportedLanguages.some((language) => language === value);
}

export function getSavedLanguage(): SupportedLanguage {
  const row = getLedgerDatabase().getFirstSync<{ value: string }>(
    'SELECT value FROM app_settings WHERE key = ?;',
    'language',
  );
  return isSupportedLanguage(row?.value) ? row.value : 'fr';
}

export function saveLanguage(language: SupportedLanguage): void {
  if (!supportedLanguages.includes(language)) {
    throw new Error('Unsupported language.');
  }
  getLedgerDatabase().runSync(
    `INSERT INTO app_settings (key, value) VALUES ('language', ?)
     ON CONFLICT(key) DO UPDATE SET value = excluded.value;`,
    language,
  );
}
