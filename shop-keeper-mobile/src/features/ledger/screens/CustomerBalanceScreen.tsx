import { useMemo } from 'react';
import { Link } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMillimeAmount } from '@/lib/money';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { LedgerPage } from '../components/LedgerPage';
import { CustomerBalanceSummary, summariseCustomerBalances } from '../services/customerBalanceService';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';

export function CustomerBalanceScreen() {
  const { t, language } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const result = useMemo(() => {
    try {
      const customers = listCustomers('', true);
      return {
        balances: summariseCustomerBalances(
          customers,
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
    <LedgerPage key={result.version} title={t.balances}>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      {balances.length === 0 && !error ? <FeedbackState kind="empty" message={t.noTickets} /> : null}
      {balances.map((balance) => (
        <View key={balance.customerId} className="ledger-card">
          <Link
            href={{ pathname: '/ledger/customer-ledger', params: { customerId: balance.customerId } }}
            asChild
          >
            <Pressable accessibilityRole="button" className="ledger-touch-target flex-row items-center justify-between">
              <Text className="font-semibold text-slate-900">{balance.displayLabel}</Text>
              <Text className="font-bold text-blue-800">{formatMillimeAmount(balance.outstandingBalanceMillimes, language)}</Text>
            </Pressable>
          </Link>
          <Link
            href={{ pathname: '/ledger/payment-form', params: { customerId: balance.customerId } }}
            asChild
          >
            <Pressable accessibilityRole="button" className="ledger-touch-target self-start rounded-lg bg-blue-50 px-3 py-2">
              <Text className="font-medium text-blue-800">{t.recordPayment}</Text>
            </Pressable>
          </Link>
        </View>
      ))}
    </LedgerPage>
  );
}
