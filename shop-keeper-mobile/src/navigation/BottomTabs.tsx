import { Pressable, Text, View } from 'react-native';
import { usePathname } from 'expo-router';
import { Icon } from '@/components/ui';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getActiveFeature, getActiveItem, isFormRoute } from './features';
import { useAppNavigation } from './useAppNavigation';

export function BottomTabs() {
  const pathname = usePathname();
  const { t } = useLedgerTranslations();
  const { switchTo } = useAppNavigation();

  const feature = getActiveFeature(pathname);
  if (!feature || isFormRoute(feature, pathname)) return null;

  const activeItem = getActiveItem(feature, pathname);

  return (
    <View accessibilityRole="tablist" className="flex-row border-t border-line bg-surface pb-safe-offset-1 pt-2">
      {feature.items.map((item) => {
        const selected = item.id === activeItem.id;
        return (
          <Pressable
            key={item.id}
            accessibilityRole="tab"
            accessibilityState={{ selected }}
            accessibilityLabel={t[item.labelKey]}
            className="min-h-touch flex-1 items-center justify-center gap-1"
            onPress={() => !selected && switchTo(item.route)}
          >
            <View className={`h-[30px] w-14 items-center justify-center rounded-full ${selected ? 'bg-primary-soft' : ''}`}>
              <Icon
                name={selected ? item.activeIcon : item.icon}
                size={22}
                className={selected ? 'text-primary' : 'text-ink-2'}
              />
            </View>
            <Text
              numberOfLines={1}
              className={`type-tab ${selected ? 'font-plex-bold text-primary' : 'text-ink-2'}`}
            >
              {t[item.labelKey]}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}
