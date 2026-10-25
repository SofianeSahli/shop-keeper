import { Pressable, Text, View } from 'react-native';
import { Icon, IconName } from './Icon';

interface EmptyStateProps {
  icon: IconName;
  message: string;
  actionLabel?: string;
  onAction?: () => void;
}

export function EmptyState({ icon, message, actionLabel, onAction }: EmptyStateProps) {
  return (
    <View accessible={!actionLabel} className="items-center gap-3 px-6 py-8">
      <View className="h-14 w-14 items-center justify-center rounded-full bg-muted">
        <Icon name={icon} size={26} className="text-ink-2" />
      </View>
      <Text className="type-body text-center text-ink-2">{message}</Text>
      {actionLabel && onAction ? (
        <Pressable accessibilityRole="button" className="ui-button-outline" onPress={onAction}>
          <Text className="type-button text-primary">{actionLabel}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}
