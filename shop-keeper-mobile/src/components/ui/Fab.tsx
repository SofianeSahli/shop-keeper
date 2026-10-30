import { Pressable, Text } from 'react-native';
import { Icon } from './Icon';

interface FabProps {
  label: string;
  onPress: () => void;
}

export function Fab({ label, onPress }: FabProps) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      className="ui-fab-shadow absolute bottom-4 end-4 min-h-button flex-row items-center gap-2 rounded-full bg-primary-fill px-5 active:opacity-90"
      onPress={onPress}
    >
      <Icon name="add" size={22} className="text-on-fill" />
      <Text className="type-button text-on-fill">{label}</Text>
    </Pressable>
  );
}
