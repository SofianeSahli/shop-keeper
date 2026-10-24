import { Pressable, Text, View } from 'react-native';
import { Icon, IconName } from './Icon';

interface ActionTileProps {
  variant: 'primary' | 'secondary';
  icon: IconName;
  title: string;
  subtitle?: string;
  tint?: 'primary' | 'paid';
  onPress: () => void;
}

const SECONDARY_TINT = {
  primary: { tile: 'bg-primary-soft', icon: 'text-primary' },
  paid: { tile: 'bg-paid-soft', icon: 'text-paid' },
} as const;

export function ActionTile({ variant, icon, title, subtitle, tint = 'primary', onPress }: ActionTileProps) {
  if (variant === 'primary') {
    return (
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={subtitle ? `${title}, ${subtitle}` : title}
        className="min-h-[84px] flex-row items-center gap-3.5 rounded-tile bg-primary px-4 py-4 active:opacity-90"
        onPress={onPress}
      >
        <View className="h-[52px] w-[52px] items-center justify-center rounded-control-lg bg-surface/15">
          <Icon name={icon} size={28} className="text-surface" />
        </View>
        <View className="flex-1 gap-0.5">
          <Text className="font-plex-bold text-title text-surface">{title}</Text>
          {subtitle ? <Text className="font-plex text-label text-primary-soft">{subtitle}</Text> : null}
        </View>
        <Icon name="chevron-forward" directional className="text-surface" />
      </Pressable>
    );
  }

  const colors = SECONDARY_TINT[tint];
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={title}
      className="min-h-[64px] flex-1 flex-row items-center gap-3 rounded-card border border-line bg-surface px-3 active:opacity-80"
      onPress={onPress}
    >
      <View className={`h-10 w-10 items-center justify-center rounded-control ${colors.tile}`}>
        <Icon name={icon} className={colors.icon} />
      </View>
      <Text numberOfLines={2} className="type-body-strong flex-1">
        {title}
      </Text>
    </Pressable>
  );
}
