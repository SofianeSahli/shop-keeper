import { useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { saveLanguage } from '@/i18n/languageStorage';
import { languageChanged } from '@/features/ledger/store/ledgerSlice';
import { useDispatch } from 'react-redux';

export const LANGUAGES = [
  { code: 'en', label: 'English' },
  { code: 'fr', label: 'Français' },
  { code: 'ar', label: 'العربية' },
] as const; // set your supported languages

export function LanguageMenu() {
  const { t, language } = useLedgerTranslations();
  const [open, setOpen] = useState(false);
  const dispatch = useDispatch();

  return (
    <>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={t.language}
        className="ledger-touch-target flex-row items-center gap-1 rounded-lg bg-slate-100 px-3"
        onPress={() => setOpen(true)}
      >
        <Ionicons name="language-outline" size={16} color="#475569" />
        <Text className="text-sm font-medium text-slate-700">{language.toUpperCase()}</Text>
      </Pressable>

      <Modal transparent animationType="fade" visible={open} onRequestClose={() => setOpen(false)}>
        <Pressable className="flex-1 items-center justify-center bg-black/40 p-6" onPress={() => setOpen(false)}>
          <View className="w-full max-w-xs gap-2 rounded-2xl bg-white p-3">
            {LANGUAGES.map(({ code, label }) => {
              const selected = code === language;
              return (
                <Pressable
                  key={code}
                  accessibilityRole="button"
                  accessibilityState={{ selected }}
                  className={`ledger-touch-target flex-row items-center justify-between rounded-xl px-4 py-3 ${
                    selected ? 'bg-blue-700' : 'bg-slate-100'
                  }`}
                  onPress={() => {
                    setOpen(false);
                    saveLanguage(code);
                          dispatch(languageChanged(code));

                  }}
                >
                  <Text className={selected ? 'font-semibold text-white' : 'text-slate-700'}>{label}</Text>
                  {selected && <Ionicons name="checkmark" size={18} color="#fff" />}
                </Pressable>
              );
            })}
          </View>
        </Pressable>
      </Modal>
    </>
  );
}