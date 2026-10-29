import type * as SQLite from 'expo-sqlite';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { countRows, createTestLedgerDb } from '@/test/ledgerTestDb';
import { getSavedThemeChoice, saveThemeChoice, type ThemeChoice } from './themeStorage';

const testDatabase = vi.hoisted(() => ({ current: null as SQLite.SQLiteDatabase | null }));

vi.mock('@/db', () => ({
  accessLedger: <T>(read: (db: SQLite.SQLiteDatabase) => Promise<T>) => read(testDatabase.current!),
}));

function storeRawSetting(key: string, value: string): void {
  testDatabase.current!.runSync('INSERT INTO app_settings (key, value) VALUES (?, ?);', key, value);
}

describe('themeStorage', () => {
  beforeEach(() => {
    testDatabase.current = createTestLedgerDb();
  });

  it('reads no saved choice as following the phone', async () => {
    await expect(getSavedThemeChoice()).resolves.toBeNull();
  });

  it.each<ThemeChoice>(['light', 'dark'])('reads back a saved %s choice', async (choice) => {
    await saveThemeChoice(choice);
    await expect(getSavedThemeChoice()).resolves.toBe(choice);
  });

  it('reads an unknown stored value as following the phone', async () => {
    storeRawSetting('theme', 'sepia');
    await expect(getSavedThemeChoice()).resolves.toBeNull();
  });

  it('keeps a single theme row when saving twice', async () => {
    await saveThemeChoice('dark');
    await saveThemeChoice('light');
    expect(countRows(testDatabase.current!, 'app_settings', 'key = ?', 'theme')).toBe(1);
    await expect(getSavedThemeChoice()).resolves.toBe('light');
  });

  it('rejects an unsupported value', async () => {
    await expect(saveThemeChoice('sepia' as ThemeChoice)).rejects.toThrow('Unsupported theme.');
    expect(countRows(testDatabase.current!, 'app_settings')).toBe(0);
  });

  it('leaves the language setting untouched', async () => {
    storeRawSetting('language', 'ar');
    await saveThemeChoice('dark');
    const language = testDatabase.current!.getFirstSync<{ value: string }>(
      'SELECT value FROM app_settings WHERE key = ?;',
      'language',
    );
    expect(language?.value).toBe('ar');
  });
});
