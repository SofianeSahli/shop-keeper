import { useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { usePathname } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getTextDirection } from '@/i18n';
import { FEATURES, getActiveFeature, getActiveItem, isFormRoute } from './features';
import { useAppNavigation } from './useAppNavigation';
import { LanguageMenu } from './LanguageMenu';

const DIRECTION_CLASS = { rtl: 'ledger-rtl', ltr: 'ledger-ltr' } as const;
const BACK_ICON = { rtl: 'arrow-forward', ltr: 'arrow-back' } as const;
const ICON_COLOR = '#475569';
const ICON_SIZE = 20;

export function TopBar() {
  const pathname = usePathname();
  const insets = useSafeAreaInsets();
  const { t, language } = useLedgerTranslations();
  const { switchTo, goBack } = useAppNavigation();
  const [featureMenuOpen, setFeatureMenuOpen] = useState(false);

  const direction = getTextDirection(language);
  const feature = getActiveFeature(pathname);
  const activeItem = getActiveItem(feature, pathname);
  const showBack = isFormRoute(feature, pathname);
  const hasMultipleFeatures = FEATURES.length > 1;

  return (
    <View
      style={{ paddingTop: insets.top }}
      className={`border-b border-slate-200 bg-white ${DIRECTION_CLASS[direction]}`}
    >
      <View className="flex-row items-center justify-between px-3 py-2">
        <View className="flex-1 flex-row items-center gap-2">
          {showBack && (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={t.back}
              className="ledger-touch-target items-center justify-center rounded-lg bg-slate-100 px-3"
              onPress={() => goBack(activeItem.route)}
            >
              <Ionicons name={BACK_ICON[direction]} size={ICON_SIZE} color={ICON_COLOR} />
            </Pressable>
          )}
          <Text numberOfLines={1} className="flex-1 text-base font-semibold text-slate-900">
            {t[activeItem.labelKey]}
          </Text>
        </View>

        <View className="flex-row items-center gap-2">
          <LanguageMenu />
          {hasMultipleFeatures && (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={t.menu}
              className="ledger-touch-target items-center justify-center rounded-lg bg-slate-100 px-3"
              onPress={() => setFeatureMenuOpen(true)}
            >
              <Ionicons name="grid-outline" size={ICON_SIZE} color={ICON_COLOR} />
            </Pressable>
          )}
        </View>
      </View>

      {hasMultipleFeatures && (
        <Modal transparent animationType="fade" visible={featureMenuOpen} onRequestClose={() => setFeatureMenuOpen(false)}>
          <Pressable className="flex-1 items-center justify-center bg-black/40 p-6" onPress={() => setFeatureMenuOpen(false)}>
            <View className={`w-full max-w-xs gap-2 rounded-2xl bg-white p-3 ${DIRECTION_CLASS[direction]}`}>
              {FEATURES.map((f) => {
                const selected = f.id === feature.id;
                return (
                  <Pressable
                    key={f.id}
                    accessibilityRole="button"
                    accessibilityState={{ selected }}
                    className={`ledger-touch-target rounded-xl px-4 py-3 ${selected ? 'bg-blue-700' : 'bg-slate-100'}`}
                    onPress={() => {
                      setFeatureMenuOpen(false);
                      if (!selected) switchTo(f.route);
                    }}
                  >
                    <Text className={selected ? 'font-semibold text-white' : 'text-slate-700'}>
                      {t[f.labelKey]}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </Pressable>
        </Modal>
      )}
    </View>
  );
}