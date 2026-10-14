import { useMemo } from 'react';
import { Link, useLocalSearchParams } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatLedgerDate, formatMillimeAmount } from '@/lib/money';
import { LedgerPage } from '../components/LedgerPage';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';

export function CustomerLedgerScreen() {
  const { customerId } = useLocalSearchParams<{ customerId: string }>();
  const { t, language } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const result = useMemo(() => {
    try {
      return {
        customer: listCustomers('', true).find((item) => item.id === customerId),
        tickets: ticketRepository.listTickets(customerId, true),
        payments: paymentRepository.listPayments(customerId, true),
        error: '',
        version: refreshVersion,
      };
    } catch (cause) {
      return {
        customer: undefined,
        tickets: [],
        payments: [],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [customerId, refreshVersion, t]);
  const { customer, tickets, payments } = result;

  return (
    <LedgerPage key={result.version} title={customer?.displayLabel ?? t.customers}>
      {result.error ? <Text accessibilityRole="alert" className="text-red-700">{result.error}</Text> : null}
      {tickets.map((ticket) => (
        <View key={ticket.id} className="ledger-card">
          <Text className="font-semibold text-slate-900">{formatLedgerDate(ticket.createdAt, language)}</Text>
          <Text className="font-semibold text-blue-800">{formatMillimeAmount(ticket.totalMillimes, language)}</Text>
          {ticket.voidedAt ? <Text className="text-red-700">{t.voidTicket}</Text> : null}
          {ticket.lines.map((line) => (
            <Text key={line.id} className="text-sm text-slate-700">
              {line.productNameSnapshot} · {line.quantity} × {formatMillimeAmount(line.unitPriceMillimes, language)}
            </Text>
          ))}
        </View>
      ))}
      <Text className="text-lg font-bold text-slate-900">{t.paymentHistory}</Text>
      <Link href={{ pathname: '/ledger/payment-form', params: { customerId } }} asChild>
        <Pressable accessibilityRole="button" className="ledger-primary-button">
          <Text className="font-semibold text-white">{t.recordPayment}</Text>
        </Pressable>
      </Link>
      {payments.map((payment) => (
        <View key={payment.id} className="ledger-card flex-row justify-between">
          <Text className="text-slate-700">{formatLedgerDate(payment.appliedAt, language, false)}</Text>
          <Text className="font-semibold text-slate-900">{formatMillimeAmount(payment.amountMillimes, language)}</Text>
        </View>
      ))}
      <Link href="/ledger/history" asChild>
        <Pressable accessibilityRole="button" className="ledger-touch-target rounded-xl bg-slate-200 p-4">
          <Text className="font-semibold text-slate-900">{t.auditHistory}</Text>
        </Pressable>
      </Link>
    </LedgerPage>
  );
}
