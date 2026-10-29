import { accessLedger } from '@/db';
import { SupportedLanguage } from './types';

const supportedLanguages: SupportedLanguage[] = ['ar', 'en', 'fr'];

function isSupportedLanguage(value: string | undefined): value is SupportedLanguage {
  return value !== undefined && supportedLanguages.some((language) => language === value);
}

export async function getSavedLanguage(): Promise<SupportedLanguage> {
  const row = await accessLedger((db) =>
    db.getFirstAsync<{ value: string }>('SELECT value FROM app_settings WHERE key = ?;', 'language'),
  );
  return isSupportedLanguage(row?.value) ? row.value : 'fr';
}

export async function saveLanguage(language: SupportedLanguage): Promise<void> {
  if (!supportedLanguages.includes(language)) {
    throw new Error('Unsupported language.');
  }
  await accessLedger((db) =>
    db.runAsync(
      `INSERT INTO app_settings (key, value) VALUES ('language', ?)
       ON CONFLICT(key) DO UPDATE SET value = excluded.value;`,
      language,
    ),
  );
}
