import { accessLedger } from '@/db';

export type ThemeChoice = 'light' | 'dark';

const THEME_SETTING_KEY = 'theme';
const themeChoices: ThemeChoice[] = ['light', 'dark'];

function isThemeChoice(value: string | undefined): value is ThemeChoice {
  return value !== undefined && themeChoices.some((choice) => choice === value);
}

export async function getSavedThemeChoice(): Promise<ThemeChoice | null> {
  const row = await accessLedger((db) =>
    db.getFirstAsync<{ value: string }>('SELECT value FROM app_settings WHERE key = ?;', THEME_SETTING_KEY),
  );
  return isThemeChoice(row?.value) ? row.value : null;
}

export async function saveThemeChoice(choice: ThemeChoice): Promise<void> {
  if (!isThemeChoice(choice)) {
    throw new Error('Unsupported theme.');
  }
  await accessLedger((db) =>
    db.runAsync(
      `INSERT INTO app_settings (key, value) VALUES (?, ?)
       ON CONFLICT(key) DO UPDATE SET value = excluded.value;`,
      THEME_SETTING_KEY,
      choice,
    ),
  );
}
