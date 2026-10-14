import { useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { MillimeInput } from '../forms/MillimeInput';
import { Customer } from '../../types/customer';
import { Ticket } from '../../types/ticket';
import { TicketSelectionDialog } from './TicketSelectionDialog';
import { CustomerSelectionDialog } from './CustomerSelectionDialog';
import { formatLedgerDate, formatMillimeAmount } from '@/lib/money';

interface PaymentFormProps {
  customers: Customer[];
  tickets: Ticket[];
  selectedCustomerId: string;
  selectedTicketId: string;
  amount: string;
  customerLabel: string;
  ticketLabel: string;
  amountLabel: string;
  cancelLabel: string;
  openTicketSelectionInitially?: boolean;
  onCustomerChange: (customerId: string) => void;
  onTicketChange: (ticketId: string) => void;
  onAmountChange: (amount: string) => void;
}

export function PaymentForm({
  customers,
  tickets,
  selectedCustomerId,
  selectedTicketId,
  amount,
  customerLabel,
  ticketLabel,
  amountLabel,
  cancelLabel,
  openTicketSelectionInitially = false,
  onCustomerChange,
  onTicketChange,
  onAmountChange,
}: PaymentFormProps) {
  const { language } = useLedgerTranslations();
  const [selectionVisible, setSelectionVisible] = useState(openTicketSelectionInitially);
  const [customerSelectionVisible, setCustomerSelectionVisible] = useState(false);
  const selectedCustomer = customers.find((customer) => customer.id === selectedCustomerId);
  const customerTickets = tickets.filter((ticket) => ticket.customerId === selectedCustomerId);
  const selectedTicket = customerTickets.find((ticket) => ticket.id === selectedTicketId);

  return (
    <View className="gap-4">
      <Pressable
        accessibilityRole="button"
        className="ledger-touch-target rounded-xl border border-slate-300 bg-white p-4"
        onPress={() => setCustomerSelectionVisible(true)}
      >
        <Text className="text-sm text-slate-600">{customerLabel}</Text>
        <Text className="font-semibold text-slate-900">{selectedCustomer?.displayLabel ?? ''}</Text>
      </Pressable>
      <CustomerSelectionDialog
        visible={customerSelectionVisible}
        customers={customers}
        selectedCustomerId={selectedCustomerId}
        title={customerLabel}
        cancelLabel={cancelLabel}
        onSelect={(customerId) => {
          onCustomerChange(customerId);
          setCustomerSelectionVisible(false);
        }}
        onClose={() => setCustomerSelectionVisible(false)}
      />
      <Pressable
        accessibilityRole="button"
        className="ledger-touch-target rounded-xl border border-slate-300 bg-white p-4"
        onPress={() => setSelectionVisible(true)}
        disabled={customerTickets.length === 0}
      >
        <Text className="text-sm text-slate-600">{ticketLabel}</Text>
        <Text className="font-semibold text-slate-900">
          {selectedTicket ? `${formatLedgerDate(selectedTicket.createdAt, language, false)} · ${formatMillimeAmount(selectedTicket.totalMillimes, language)}` : ''}
        </Text>
      </Pressable>
      <TicketSelectionDialog
        visible={selectionVisible}
        tickets={customerTickets}
        selectedTicketId={selectedTicketId}
        title={ticketLabel}
        cancelLabel={cancelLabel}
        onSelect={(ticketId) => {
          onTicketChange(ticketId);
          setSelectionVisible(false);
        }}
        onClose={() => setSelectionVisible(false)}
      />
      <MillimeInput label={amountLabel} value={amount} onChangeText={onAmountChange} />
    </View>
  );
}
