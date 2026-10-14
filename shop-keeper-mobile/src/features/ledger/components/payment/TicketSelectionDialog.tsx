import { Modal, Pressable, Text, View } from 'react-native';
import { Ticket } from '../../types/ticket';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatLedgerDate, formatMillimeAmount } from '@/lib/money';

interface TicketSelectionDialogProps {
  visible: boolean;
  tickets: Ticket[];
  selectedTicketId: string;
  title: string;
  cancelLabel: string;
  onSelect: (ticketId: string) => void;
  onClose: () => void;
}

export function TicketSelectionDialog({
  visible,
  tickets,
  selectedTicketId,
  title,
  cancelLabel,
  onSelect,
  onClose,
}: TicketSelectionDialogProps) {
  const { language } = useLedgerTranslations();
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View className="flex-1 justify-center bg-black/50 p-5">
        <View className="gap-3 rounded-2xl bg-white p-5">
          <Text className="text-lg font-bold text-slate-900">{title}</Text>
          {tickets.map((ticket) => (
            <Pressable
              key={ticket.id}
              accessibilityRole="radio"
              accessibilityState={{ selected: ticket.id === selectedTicketId }}
              className={`ledger-touch-target rounded-lg p-3 ${ticket.id === selectedTicketId ? 'bg-blue-100' : 'bg-slate-50'}`}
              onPress={() => onSelect(ticket.id)}
            >
              <Text className="font-semibold text-slate-900">
                {formatLedgerDate(ticket.createdAt, language, false)} · {formatMillimeAmount(ticket.totalMillimes, language)}
              </Text>
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
