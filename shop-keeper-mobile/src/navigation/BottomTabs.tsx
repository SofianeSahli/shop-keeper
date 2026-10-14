import { Pressable, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { usePathname } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getTextDirection } from '@/i18n';
import { getActiveFeature, getActiveItem, isFormRoute } from './features';
import { useAppNavigation } from './useAppNavigation';

const DIRECTION_CLASS = { rtl: 'ledger-rtl', ltr: 'ledger-ltr' } as const;
const COLOR_ACTIVE = '#1d4ed8';
const COLOR_INACTIVE = '#94a3b8';
const ICON_SIZE = 22;

export function BottomTabs() {
  const pathname = usePathname();
  const insets = useSafeAreaInsets();
  const { t, language } = useLedgerTranslations();
  const { switchTo } = useAppNavigation();

  const feature = getActiveFeature(pathname);
  if (isFormRoute(feature, pathname)) return null;

  const activeItem = getActiveItem(feature, pathname);
  const direction = getTextDirection(language);

  return (
    <View
      style={{ paddingBottom: Math.max(insets.bottom, 8) }}
      className={`flex-row border-t border-slate-200 bg-white pt-2 ${DIRECTION_CLASS[direction]}`}
    >
      {feature.items.map((item) => {
        const selected = item.id === activeItem.id;
        return (
          <Pressable
            key={item.id}
            accessibilityRole="tab"
            accessibilityState={{ selected }}
            accessibilityLabel={t[item.labelKey]}
            className="ledger-touch-target flex-1 items-center justify-center gap-0.5"
            onPress={() => !selected && switchTo(item.route)}
          >
            <Ionicons
              name={selected ? item.activeIcon : item.icon}
              size={ICON_SIZE}
              color={selected ? COLOR_ACTIVE : COLOR_INACTIVE}
            />
            <Text
              numberOfLines={1}
              className={`text-[11px] ${selected ? 'font-semibold text-blue-700' : 'text-slate-500'}`}
            >
              {t[item.labelKey]}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}