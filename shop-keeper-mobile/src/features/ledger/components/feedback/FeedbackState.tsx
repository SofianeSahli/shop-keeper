import { ActivityIndicator, Text, View } from 'react-native';

interface FeedbackStateProps {
  kind: 'loading' | 'empty' | 'error' | 'info';
  message: string;
}

export function FeedbackState({ kind, message }: FeedbackStateProps) {
  const tone = kind === 'error'
    ? 'text-red-700'
    : kind === 'info'
      ? 'text-blue-800'
      : 'text-slate-600';
  return (
    <View className="items-center gap-3 rounded-xl bg-white p-5">
      {kind === 'loading' ? <ActivityIndicator accessibilityLabel={message} /> : null}
      <Text accessibilityRole={kind === 'error' ? 'alert' : 'text'} className={`text-center ${tone}`}>
        {message}
      </Text>
    </View>
  );
}
