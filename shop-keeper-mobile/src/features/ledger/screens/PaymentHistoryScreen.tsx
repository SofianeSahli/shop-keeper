import { useMemo } from 'react';
import { router } from 'expo-router';
import { View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { formatLedgerDate } from '@/lib/money';
import { AmountText, Avatar, DayHeader, EmptyState, Fab, InfoBanner, ListRow, ScreenScroll } from '@/components/ui';
import { groupByLocalDay } from '@/components/ui/logic/dayGroups';
import { getCustomerLabel } from '../components/customers/customerLabel';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';
import { Customer } from '../types/customer';
import { Payment } from '../types/payment';
import { Ticket } from '../types/ticket';

export function PaymentHistoryScreen() {
  const { t, language } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const result = useMemo(() => {
    try {
      return {
        payments: paymentRepository.listPayments(undefined, true),
        customers: listCustomers('', true),
        tickets: ticketRepository.listTickets(undefined, true),
        error: '',
        version: refreshVersion,
      };
    } catch (cause) {
      return {
        payments: [] as Payment[],
        customers: [] as Customer[],
        tickets: [] as Ticket[],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [refreshVersion, t]);
  const groups = groupByLocalDay(result.payments, (payment) => payment.appliedAt);

  return (
    <View className="flex-1">
      <ScreenScroll key={result.version}>
        {result.error ? <InfoBanner tone="error" title={result.error} /> : null}
        {result.payments.length === 0 && !result.error ? (
          <EmptyState icon="wallet-outline" message={t.noPayments} />
        ) : null}
        {groups.map((group) => (
          <View key={group.dayKey} className="gap-2">
            <DayHeader dayKey={group.dayKey} />
            <View className="ui-card-flush">
              {group.items.map((payment, index) => {
                const label = getCustomerLabel(payment.customerId, result.customers, t);
                const ticket = result.tickets.find((item) => item.id === payment.ticketId);
                const voided = Boolean(payment.voidedAt);
                return (
                  <ListRow
                    key={payment.id}
                    separated={index > 0}
                    leading={<Avatar label={label} />}
                    title={label}
                    meta={ticket ? formatMessage(t.onTicketOf, { date: formatLedgerDate(ticket.createdAt, language, false) }) : undefined}
                    trailing={<AmountText millimes={payment.amountMillimes} signed tone={voided ? 'ink' : 'paid'} />}
                    trailingCaption={voided ? t.cancelledEntry : undefined}
                    chevron={!voided}
                    onPress={voided ? undefined : () => router.push({ pathname: '/ledger/payment-form', params: { id: payment.id } })}
                    accessibilityLabel={voided ? `${label}, ${t.cancelledEntry}` : `${t.editPayment}, ${label}`}
                  />
                );
              })}
            </View>
          </View>
        ))}
      </ScreenScroll>
      <Fab label={t.collect} onPress={() => router.push('/ledger/payment-form')} />
    </View>
  );
}
