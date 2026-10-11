import { useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';

interface ConfirmActionProps {
  buttonLabel: string;
  title: string;
  message: string;
  confirmLabel: string;
  cancelLabel: string;
  onConfirm: () => void;
}

export function ConfirmAction({
  buttonLabel,
  title,
  message,
  confirmLabel,
  cancelLabel,
  onConfirm,
}: ConfirmActionProps) {
  const [visible, setVisible] = useState(false);
  return (
    <>
      <Pressable
        accessibilityRole="button"
        className="ledger-touch-target rounded-lg border border-red-300 px-3 py-2"
        onPress={() => setVisible(true)}
      >
        <Text className="font-semibold text-red-700">{buttonLabel}</Text>
      </Pressable>
      <Modal visible={visible} transparent animationType="fade" onRequestClose={() => setVisible(false)}>
        <View className="flex-1 justify-center bg-black/50 p-6">
          <View className="gap-4 rounded-2xl bg-white p-5">
            <Text className="text-lg font-bold text-slate-900">{title}</Text>
            <Text className="text-base text-slate-700">{message}</Text>
            <View className="flex-row justify-end gap-3">
              <Pressable
                accessibilityRole="button"
                className="ledger-touch-target rounded-lg px-4 py-3"
                onPress={() => setVisible(false)}
              >
                <Text className="font-semibold text-slate-700">{cancelLabel}</Text>
              </Pressable>
              <Pressable
                accessibilityRole="button"
                className="ledger-touch-target rounded-lg bg-red-700 px-4 py-3"
                onPress={() => {
                  setVisible(false);
                  onConfirm();
                }}
              >
                <Text className="font-semibold text-white">{confirmLabel}</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </>
  );
}
