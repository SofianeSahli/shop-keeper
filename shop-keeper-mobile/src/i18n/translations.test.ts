import { describe, expect, it } from 'vitest';
import ar from './translations/ar.json';
import en from './translations/en.json';
import fr from './translations/fr.json';
import { getPlaceholders } from './format';

const languages: Record<string, Record<string, string>> = { fr, ar, en };

describe('translation resources', () => {
  const frKeys = Object.keys(fr).sort();

  it.each(['ar', 'en'])('%s has exactly the same keys as fr', (language) => {
    expect(Object.keys(languages[language]).sort()).toEqual(frKeys);
  });

  it.each(frKeys)('"%s" uses the same placeholders in every language', (key) => {
    const expected = getPlaceholders(fr[key as keyof typeof fr]);
    for (const language of ['ar', 'en']) {
      expect(getPlaceholders(languages[language][key] ?? '')).toEqual(expected);
    }
  });

  it.each(Object.keys(languages))('%s has no empty values', (language) => {
    const empty = Object.entries(languages[language]).filter(([, value]) => !value.trim());
    expect(empty).toEqual([]);
  });
});
