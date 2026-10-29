import { Pressable, Text, View } from 'react-native';
import { Icon, IconName } from './Icon';

export type BannerTone = 'warning' | 'success' | 'info' | 'error';

const TONES: Record<BannerTone, { container: string; text: string; icon: IconName; button: string }> = {
  warning: { container: 'bg-partial-soft border-partial-line', text: 'text-warn-strong', icon: 'warning', button: 'bg-warn-fill' },
  success: { container: 'bg-paid-soft border-paid-soft', text: 'text-paid', icon: 'checkmark-circle', button: 'bg-paid-fill' },
  info: { container: 'bg-primary-faint border-primary-soft', text: 'text-primary', icon: 'information-circle', button: 'bg-primary-fill' },
  error: { container: 'bg-error-soft border-error-line', text: 'text-error-strong', icon: 'alert-circle', button: 'bg-error-fill' },
};

interface InfoBannerProps {
  tone: BannerTone;
  title: string;
  text?: string;
  actionLabel?: string;
  onAction?: () => void;
}

export function InfoBanner({ tone, title, text, actionLabel, onAction }: InfoBannerProps) {
  const colors = TONES[tone];
  const isAlert = tone === 'warning' || tone === 'error';
  return (
    <View
      accessibilityRole={isAlert ? 'alert' : undefined}
      accessibilityLiveRegion={isAlert ? 'polite' : 'none'}
      className={`gap-2.5 rounded-control-lg border p-3.5 ${colors.container}`}
    >
      <View className="flex-row items-start gap-2.5">
        <Icon name={colors.icon} size={20} className={colors.text} />
        <View className="flex-1 gap-0.5">
          <Text className={`font-plex-bold text-body ${colors.text}`}>{title}</Text>
          {text ? <Text className={`font-plex text-label ${colors.text}`}>{text}</Text> : null}
        </View>
      </View>
      {actionLabel && onAction ? (
        <Pressable
          accessibilityRole="button"
          className={`min-h-touch items-center justify-center self-start rounded-control px-4 ${colors.button}`}
          onPress={onAction}
        >
          <Text className="font-plex-bold text-label text-on-fill">{actionLabel}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}
