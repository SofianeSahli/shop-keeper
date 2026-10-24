import { ActivityIndicator, View } from 'react-native';
import { EmptyState, InfoBanner } from '@/components/ui';

interface FeedbackStateProps {
  kind: 'loading' | 'empty' | 'error' | 'info';
  message: string;
}

/** Kept for screens not yet rebuilt in 003; delegates to the shared EmptyState / InfoBanner. */
export function FeedbackState({ kind, message }: FeedbackStateProps) {
  if (kind === 'error') return <InfoBanner tone="error" title={message} />;
  if (kind === 'info') return <InfoBanner tone="info" title={message} />;
  if (kind === 'empty') return <EmptyState icon="file-tray-outline" message={message} />;
  return (
    <View className="items-center py-8">
      <ActivityIndicator accessibilityLabel={message} className="text-primary" />
    </View>
  );
}
