import { Text } from 'react-native';

export function FieldErrorText({ message }: { message?: string }) {
  if (!message) return null;
  return (
    <Text accessibilityLiveRegion="polite" className="font-plex text-label text-error">
      {message}
    </Text>
  );
}
