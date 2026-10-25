import { useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';
import { Icon, IconName } from '@/components/ui';

interface ConfirmActionProps {
  buttonLabel: string;
  title: string;
  message: string;
  confirmLabel: string;
  cancelLabel: string;
  onConfirm: () => void;
  icon?: IconName;
}

/** Destructive action behind a confirmation dialog; lives on detail and form screens, never in list rows. */
export function ConfirmAction({
  buttonLabel,
  title,
  message,
  confirmLabel,
  cancelLabel,
  onConfirm,
  icon = 'trash-outline',
}: ConfirmActionProps) {
  const [visible, setVisible] = useState(false);
  return (
    <>
      <Pressable
        accessibilityRole="button"
        className="ui-button border border-debt-line bg-surface active:bg-debt-soft"
        onPress={() => setVisible(true)}
      >
        <Icon name={icon} size={20} className="text-debt" />
        <Text className="type-button text-debt">{buttonLabel}</Text>
      </Pressable>
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
