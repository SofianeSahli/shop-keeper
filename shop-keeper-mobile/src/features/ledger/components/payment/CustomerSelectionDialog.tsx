import { Modal, Pressable, Text, View } from 'react-native';
import { Customer } from '../../types/customer';

interface CustomerSelectionDialogProps {
  visible: boolean;
  customers: Customer[];
  selectedCustomerId: string;
  title: string;
  cancelLabel: string;
  onSelect: (customerId: string) => void;
  onClose: () => void;
}

export function CustomerSelectionDialog({
  visible,
  customers,
  selectedCustomerId,
  title,
  cancelLabel,
  onSelect,
  onClose,
}: CustomerSelectionDialogProps) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View className="flex-1 justify-center bg-black/50 p-5">
        <View className="gap-3 rounded-2xl bg-white p-5">
          <Text className="text-lg font-bold text-slate-900">{title}</Text>
          {customers.map((customer) => (
            <Pressable
              key={customer.id}
              accessibilityRole="radio"
              accessibilityState={{ selected: customer.id === selectedCustomerId }}
              className={`ledger-touch-target rounded-lg p-3 ${customer.id === selectedCustomerId ? 'bg-blue-100' : 'bg-slate-50'}`}
              onPress={() => onSelect(customer.id)}
            >
              <Text className="font-medium text-slate-900">{customer.displayLabel}</Text>
            </Pressable>
          ))}
          <Pressable accessibilityRole="button" className="ledger-primary-button" onPress={onClose}>
            <Text className="font-semibold text-white">{cancelLabel}</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}
