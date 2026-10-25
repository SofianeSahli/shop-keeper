import { useState } from 'react';
import { View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatMessage } from '@/i18n/format';
import { formatMillimeAmount, formatShortDate } from '@/lib/money';
import { AmountText, BottomSheet, Icon, ListRow, StatusChip } from '@/components/ui';
import { Ticket } from '../../types/ticket';

export interface PayableTicket {
  ticket: Ticket;
  remainingMillimes: number;
}

interface PaymentTicketSheetProps {
  visible: boolean;
  tickets: readonly PayableTicket[];
  selectedTicketId: string;
  onConfirm: (ticketId: string) => void;
  onClose: () => void;
}

/** "Choisir un ticket impayé": radio list of unpaid tickets, oldest first (spec US4-AS4). */
export function PaymentTicketSheet({ visible, tickets, selectedTicketId, onConfirm, onClose }: PaymentTicketSheetProps) {
  const { t, language } = useLedgerTranslations();
  const [choice, setChoice] = useState(selectedTicketId);
  const current = visible ? choice || selectedTicketId : selectedTicketId;

  return (
    <BottomSheet
      visible={visible}
      title={t.selectTicket}
      helper={t.oldestSelected}
      onClose={() => {
        setChoice('');
        onClose();
      }}
      confirmLabel={t.validate}
      confirmDisabled={!current}
      onConfirm={() => {
        onConfirm(current);
        setChoice('');
      }}
    >
      <View className="ui-card-flush">
        {tickets.map(({ ticket, remainingMillimes }, index) => {
          const selected = ticket.id === current;
          return (
            <ListRow
              key={ticket.id}
              separated={index > 0}
              accessibilityRole="radio"
              selected={selected}
              accessibilityLabel={`${formatMessage(t.ticketOfDate, { date: formatShortDate(ticket.createdAt, language) })}, ${formatMessage(
                t.remainingShort,
                { amount: formatMillimeAmount(remainingMillimes, language) },
              )}`}
              leading={
                <Icon
                  name={selected ? 'radio-button-on' : 'radio-button-off'}
                  className={selected ? 'text-primary' : 'text-ink-2'}
                />
              }
              title={formatMessage(t.ticketOfDate, { date: formatShortDate(ticket.createdAt, language) })}
              meta={formatMillimeAmount(ticket.totalMillimes, language)}
              detail={<StatusChip status={ticket.status} />}
              trailing={<AmountText millimes={remainingMillimes} tone="debt" />}
              onPress={() => setChoice(ticket.id)}
            />
          );
        })}
      </View>
    </BottomSheet>
  );
}
