// components/DialogShell.tsx
import { ReactNode } from 'react';
import { Modal, Text, View } from 'react-native';

type Props = {
  visible: boolean;
  title: string;
  onClose: () => void;
  children: ReactNode;
};

export function DialogShell({ visible, title, onClose, children }: Props) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View className="flex-1 justify-center bg-black/50 p-4">
        <View className="max-h-[85%] rounded-2xl bg-white p-4">
          <Text className="mb-3 text-lg font-semibold text-slate-900">{title}</Text>
          {children}
        </View>
      </View>
    </Modal>
  );
}