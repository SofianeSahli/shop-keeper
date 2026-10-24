import { useMemo } from 'react';
import { router } from 'expo-router';
import { View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { AmountText, Avatar, EmptyState, InfoBanner, ListRow, ScreenScroll } from '@/components/ui';
import { CustomerBalanceSummary, summariseCustomerBalances } from '../services/customerBalanceService';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';

export function CustomerBalanceScreen() {
  const { t } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const result = useMemo(() => {
    try {
      return {
        balances: summariseCustomerBalances(
          listCustomers('', true),
          ticketRepository.listTickets(),
          paymentRepository.listPayments(),
        ),
        error: '',
        version: refreshVersion,
      };
    } catch (cause) {
      return {
        balances: [] as CustomerBalanceSummary[],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [refreshVersion, t]);
  const { balances, error } = result;

  return (
    <ScreenScroll key={result.version}>
      {error ? <InfoBanner tone="error" title={error} /> : null}
      {balances.length === 0 && !error ? <EmptyState icon="people-outline" message={t.emptyCustomers} /> : null}
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
