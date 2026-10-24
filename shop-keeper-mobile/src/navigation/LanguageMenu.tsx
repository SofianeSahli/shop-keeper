import { useState } from 'react';
import { Pressable, Text } from 'react-native';
import { useDispatch } from 'react-redux';
import { BottomSheet, Icon, ListRow } from '@/components/ui';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { saveLanguage } from '@/i18n/languageStorage';
import { SupportedLanguage } from '@/i18n/types';
import { languageChanged } from '@/features/ledger/store/ledgerSlice';

const LANGUAGES = [
  { code: 'fr', shortKey: 'languageShortFr', nameKey: 'languageNameFr' },
  { code: 'ar', shortKey: 'languageShortAr', nameKey: 'languageNameAr' },
  { code: 'en', shortKey: 'languageShortEn', nameKey: 'languageNameEn' },
] as const satisfies readonly { code: SupportedLanguage; shortKey: string; nameKey: string }[];

export function LanguageMenu() {
  const { t, language } = useLedgerTranslations();
  const [open, setOpen] = useState(false);
  const dispatch = useDispatch();
  const current = LANGUAGES.find((entry) => entry.code === language) ?? LANGUAGES[0];

  function choose(code: SupportedLanguage): void {
    setOpen(false);
    saveLanguage(code);
    dispatch(languageChanged(code));
  }

  return (
    <>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${t.language}, ${t[current.nameKey]}`}
        className="min-h-touch flex-row items-center gap-1.5 rounded-full border border-line-strong bg-surface px-3 active:bg-muted"
        onPress={() => setOpen(true)}
      >
        <Icon name="globe-outline" size={18} className="text-ink-2" />
        <Text className="font-plex-semibold text-label text-ink">{t[current.shortKey]}</Text>
        <Icon name="chevron-down" size={14} className="text-ink-2" />
      </Pressable>

      <BottomSheet visible={open} title={t.language} onClose={() => setOpen(false)}>
        <Pressable className="ui-card-flush">
          {LANGUAGES.map((entry, index) => (
            <ListRow
              key={entry.code}
              title={t[entry.nameKey]}
              selected={entry.code === language}
              separated={index > 0}
              accessibilityRole="radio"
              trailing={entry.code === language ? <Icon name="checkmark" className="text-primary" /> : undefined}
              onPress={() => choose(entry.code)}
            />
          ))}
        </Pressable>
      </BottomSheet>
    </>
  );
}
