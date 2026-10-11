import en from './translations/en.json';
import fr from './translations/fr.json';
import ar from './translations/ar.json';
import { SupportedLanguage } from './types';

export type { SupportedLanguage } from './types';

export const translations = {
  en,
  fr,
  ar,
} as const;

export function getTranslations(language: SupportedLanguage = 'en') {
  return translations[language];
}

export function getTextDirection(language: SupportedLanguage): 'rtl' | 'ltr' {
  return language === 'ar' ? 'rtl' : 'ltr';
}
