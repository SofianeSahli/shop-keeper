import { Text, View } from 'react-native';
import { getInitials } from './logic/initials';

interface AvatarProps {
  label: string;
  size?: 'md' | 'lg';
}

export function Avatar({ label, size = 'md' }: AvatarProps) {
  return (
    <View
      accessibilityElementsHidden
      importantForAccessibility="no-hide-descendants"
      className={`items-center justify-center rounded-full bg-muted ${size === 'lg' ? 'h-11 w-11' : 'h-10 w-10'}`}
    >
      <Text className="font-plex-bold text-label text-ink-2">{getInitials(label)}</Text>
    </View>
  );
}
