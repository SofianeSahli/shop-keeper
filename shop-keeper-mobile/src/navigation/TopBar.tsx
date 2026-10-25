import { Pressable, Text, View } from 'react-native';
import { usePathname } from 'expo-router';
import { Icon } from '@/components/ui';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getActiveFeature, getActiveItem, isNestedRoute } from './features';
import { useAppNavigation } from './useAppNavigation';
import { LanguageMenu } from './LanguageMenu';
import { useTopBarState } from './TopBarContext';

export function TopBar() {
  const pathname = usePathname();
  const { t } = useLedgerTranslations();
  const { goBack, pushTo } = useAppNavigation();
  const options = useTopBarState();

  const feature = getActiveFeature(pathname);
  if (!feature) return null;

  const activeItem = getActiveItem(feature, pathname);
  const showBack = isNestedRoute(feature, pathname);
  const title = options.title ?? (activeItem.id === 'home' ? t.appTitle : t[activeItem.labelKey]);

  return (
    <View className="border-b border-line bg-surface pt-safe">
      <View className="min-h-[60px] flex-row items-center gap-2 px-3">
        {showBack ? (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={t.back}
            className="h-12 w-12 items-center justify-center rounded-control active:bg-muted"
            onPress={() => goBack(activeItem.route)}
          >
            <Icon name="arrow-back" directional />
          </Pressable>
        ) : (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={t.home}
            className="ui-icon-button"
            onPress={() => activeItem.id !== 'home' && pushTo('/')}
          >
            <View className="h-[34px] w-[34px] items-center justify-center rounded-control bg-primary">
              <Text className="font-plex-bold text-heading text-surface">{t.appMark}</Text>
            </View>
          </Pressable>
        )}
        <Text accessibilityRole="header" numberOfLines={1} className="type-title flex-1">
          {title}
        </Text>
        {options.action ? (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={options.action.label}
            className="ui-icon-button active:bg-muted"
            onPress={options.action.onPress}
          >
            <Icon name={options.action.icon} className="text-primary" />
          </Pressable>
        ) : null}
        <LanguageMenu />
      </View>
    </View>
  );
}
