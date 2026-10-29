import { useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';
import { BusyButton, IconName } from '@/components/ui';

interface ConfirmActionProps {
  buttonLabel: string;
  title: string;
  message: string;
  confirmLabel: string;
  cancelLabel: string;
  onConfirm: () => void;
  icon?: IconName;
  busy?: boolean;
  busyLabel?: string;
}

export function ConfirmAction({
  buttonLabel,
  title,
  message,
  confirmLabel,
  cancelLabel,
  onConfirm,
  icon = 'trash-outline',
  busy = false,
  busyLabel,
}: ConfirmActionProps) {
  const [visible, setVisible] = useState(false);
  return (
    <>
      <BusyButton
        variant="danger"
        icon={icon}
        label={buttonLabel}
        busy={busy}
        busyLabel={busyLabel}
        onPress={() => setVisible(true)}
      />
      <Modal visible={visible} transparent animationType="fade" statusBarTranslucent onRequestClose={() => setVisible(false)}>
        <View className="flex-1 justify-center bg-scrim p-6">
          <View accessibilityViewIsModal className="gap-4 rounded-card bg-surface p-5">
            <Text accessibilityRole="header" className="type-title">
              {title}
            </Text>
            <Text className="type-body text-ink-2">{message}</Text>
            <View className="flex-row justify-end gap-3">
              <Pressable accessibilityRole="button" className="ui-button-outline" onPress={() => setVisible(false)}>
                <Text className="type-button text-ink">{cancelLabel}</Text>
              </Pressable>
              <Pressable
                accessibilityRole="button"
                className="ui-button bg-debt"
                onPress={() => {
                  setVisible(false);
                  onConfirm();
                }}
              >
                <Text className="type-button text-surface">{confirmLabel}</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </>
  );
}
