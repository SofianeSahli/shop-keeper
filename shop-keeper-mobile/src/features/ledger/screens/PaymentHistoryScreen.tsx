import { router } from 'expo-router';
import { View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import { formatMessage } from '@/i18n/format';
import { formatLedgerDate } from '@/lib/money';
import {
  AmountText,
  Avatar,
  DayHeader,
  EmptyState,
  Fab,
  InfoBanner,
  ListRow,
  RefreshingPill,
  ScreenScroll,
  SkeletonRows,
} from '@/components/ui';
import { groupByLocalDay } from '@/components/ui/logic/dayGroups';
import { getCustomerLabel } from '../components/customers/customerLabel';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';

async function loadPaymentHistory() {
  const payments = await paymentRepository.listPayments(undefined, true);
  const customers = await listCustomers('', true);
  const tickets = await ticketRepository.listTickets(undefined, true);
  return { payments, customers, tickets };
}

export function PaymentHistoryScreen() {
  const { t, language } = useLedgerTranslations();
  const query = useLedgerQuery(loadPaymentHistory);
  const payments = query.data?.payments ?? [];
  const customers = query.data?.customers ?? [];
  const tickets = query.data?.tickets ?? [];
  const groups = groupByLocalDay(payments, (payment) => payment.appliedAt);

  return (
    <View className="flex-1">
      <ScreenScroll>
        <View className="items-end">
          <RefreshingPill visible={query.status === 'refreshing'} />
        </View>
        {query.status === 'error' ? (
          <InfoBanner tone="error" title={query.error} actionLabel={t.retry} onAction={query.retry} />
        ) : null}
        {query.status === 'loading' ? <SkeletonRows count={5} /> : null}
        {payments.length === 0 && query.status === 'ready' ? <EmptyState icon="wallet-outline" message={t.noPayments} /> : null}
        {groups.map((group) => (
          <View key={group.dayKey} className="gap-2">
            <DayHeader dayKey={group.dayKey} />
            <View className="ui-card-flush">
              {group.items.map((payment, index) => {
                const label = getCustomerLabel(payment.customerId, customers, t);
                const ticket = tickets.find((item) => item.id === payment.ticketId);
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
