import { ActivityIndicator, Pressable, Text, View } from 'react-native';
import type { Toast as ToastModel, ToastKind } from '@/features/feedback/store/feedbackSlice';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { Icon, IconName } from './Icon';

const KIND_STYLES: Record<ToastKind, { card: string; iconCircle: string; icon: string; detail: string; iconName: IconName }> = {
  success: { card: 'bg-paid-soft border-paid-line', iconCircle: 'bg-paid-fill', icon: 'text-on-fill', detail: 'text-ink-2', iconName: 'checkmark' },
  error: { card: 'bg-error-soft border-error-line', iconCircle: 'bg-error-fill', icon: 'text-on-fill', detail: 'text-error-strong', iconName: 'alert' },
  info: { card: 'bg-primary-soft border-primary-line', iconCircle: 'bg-primary-fill', icon: 'text-on-fill', detail: 'text-ink-2', iconName: 'information' },
  pending: { card: 'bg-surface border-line', iconCircle: 'bg-primary-soft', icon: 'text-primary', detail: 'text-ink-2', iconName: 'sync' },
};

const ACTION_TEXT: Record<ToastKind, string> = {
  success: 'text-paid',
  error: 'text-error',
  info: 'text-primary',
  pending: 'text-primary',
};

interface ToastProps {
  toast: ToastModel;
  onAction: () => void;
  onDismiss: () => void;
}

export function Toast({ toast, onAction, onDismiss }: ToastProps) {
  const { t } = useLedgerTranslations();
  const styles = KIND_STYLES[toast.kind];

  return (
    <View className={`min-h-toast flex-row items-center gap-3 rounded-toast border py-2 ps-3 pe-1 shadow-md shadow-elevation/20 ${styles.card}`}>
      <View
        accessible
        accessibilityRole={toast.kind === 'error' ? 'alert' : 'text'}
        accessibilityLiveRegion={toast.kind === 'error' ? 'assertive' : 'polite'}
        accessibilityLabel={[toast.title, toast.detail].filter(Boolean).join('. ')}
        className="flex-1 flex-row items-center gap-3"
      >
        <View className={`h-8 w-8 items-center justify-center rounded-full ${styles.iconCircle}`}>
          {toast.kind === 'pending' ? (
            <ActivityIndicator size="small" className="text-primary" />
          ) : (
            <Icon name={styles.iconName} size={18} className={styles.icon} />
          )}
        </View>
        <View className="flex-1 gap-0.5">
          <Text className="font-plex-bold text-body text-ink">{toast.title}</Text>
          {toast.detail ? <Text className={`font-plex text-label ${styles.detail}`}>{toast.detail}</Text> : null}
        </View>
      </View>
      {toast.actionLabel ? (
        <Pressable accessibilityRole="button" className="min-h-touch justify-center px-2" onPress={onAction}>
          <Text className={`font-plex-bold text-label ${ACTION_TEXT[toast.kind]}`}>{toast.actionLabel}</Text>
        </Pressable>
      ) : null}
      {toast.kind !== 'pending' ? (
        <Pressable accessibilityRole="button" accessibilityLabel={t.close} className="ui-icon-button" onPress={onDismiss}>
          <Icon name="close" size={20} className="text-ink-2" />
        </Pressable>
      ) : null}
    </View>
  );
}
