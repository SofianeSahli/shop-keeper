import { router } from 'expo-router';
import { View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import { AmountText, Avatar, EmptyState, InfoBanner, ListRow, RefreshingPill, ScreenScroll, SkeletonRows } from '@/components/ui';
import { summariseCustomerBalances } from '../services/customerBalanceService';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';

async function loadBalances() {
  const customers = await listCustomers('', true);
  const tickets = await ticketRepository.listTickets();
  const payments = await paymentRepository.listPayments();
  return summariseCustomerBalances(customers, tickets, payments);
}

export function CustomerBalanceScreen() {
  const { t } = useLedgerTranslations();
  const query = useLedgerQuery(loadBalances);
  const balances = query.data ?? [];

  return (
    <ScreenScroll>
      <View className="items-end">
        <RefreshingPill visible={query.status === 'refreshing'} />
      </View>
      {query.status === 'error' ? (
        <InfoBanner tone="error" title={query.error} actionLabel={t.retry} onAction={query.retry} />
      ) : null}
      {query.status === 'loading' ? <SkeletonRows count={5} /> : null}
      {balances.length === 0 && query.status === 'ready' ? <EmptyState icon="people-outline" message={t.emptyCustomers} /> : null}
      {balances.length > 0 ? (
        <View className="ui-card-flush">
          {balances.map((balance, index) => (
            <ListRow
              key={balance.customerId}
              separated={index > 0}
              leading={<Avatar label={balance.displayLabel} />}
              title={balance.displayLabel}
              trailing={
                <AmountText
                  millimes={balance.outstandingBalanceMillimes}
                  tone={balance.outstandingBalanceMillimes > 0 ? 'debt' : 'paid'}
                />
              }
              chevron
              onPress={() =>
                router.push({ pathname: '/ledger/customer-ledger', params: { customerId: balance.customerId } })
              }
            />
          ))}
        </View>
      ) : null}
    </ScreenScroll>
  );
}
