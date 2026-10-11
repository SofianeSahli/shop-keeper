import { useMemo, useState } from 'react';
import { Link } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useDispatch, useSelector } from 'react-redux';
import { RootState } from '@/store';
import { refreshCompleted } from '../store/ledgerSlice';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatLedgerDate, formatMillimeAmount } from '@/lib/money';
import { ConfirmAction } from '../components/feedback/ConfirmAction';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { LedgerPage } from '../components/LedgerPage';
import { Customer } from '../types/customer';
import { Payment } from '../types/payment';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';

export function PaymentHistoryScreen() {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const [actionError, setActionError] = useState('');
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
        tickets: [],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [refreshVersion, t]);
  const error = actionError || result.error;

  return (
    <LedgerPage key={result.version} title={t.paymentHistory}>
      <Link href="/ledger/payment-form" asChild>
        <Pressable accessibilityRole="button" className="ledger-primary-button">
          <Text className="font-semibold text-white">{t.createPayment}</Text>
        </Pressable>
      </Link>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      {result.payments.length === 0 && !error ? <FeedbackState kind="empty" message={t.noPayments} /> : null}
      {result.payments.map((payment) => {
        const customer = result.customers.find((item) => item.id === payment.customerId);
        const ticket = result.tickets.find((item) => item.id === payment.ticketId);
        return (
          <View key={payment.id} className="ledger-card">
            <View className="flex-row items-start justify-between gap-3">
              <View className="flex-1 gap-1">
                <Text className="font-semibold text-slate-900">{customer?.displayLabel ?? payment.customerId}</Text>
                <Text className="text-sm text-slate-600">
                  {ticket ? formatLedgerDate(ticket.createdAt, language, false) : payment.ticketId}
                </Text>
                <Text className="font-semibold text-blue-800">{formatMillimeAmount(payment.amountMillimes, language)}</Text>
                {payment.voidedAt ? <Text className="text-sm text-red-700">{t.voidPayment}</Text> : null}
              </View>
              {!payment.voidedAt ? (
                <Link href={{ pathname: '/ledger/payment-form', params: { id: payment.id } }} asChild>
                  <Pressable accessibilityRole="button" className="ledger-touch-target rounded-lg bg-slate-100 px-3 py-2">
                    <Text className="font-medium text-slate-800">{t.editPayment}</Text>
                  </Pressable>
                </Link>
              ) : null}
            </View>
            {!payment.voidedAt ? (
              <ConfirmAction
                buttonLabel={t.voidPayment}
                title={t.confirmRemoval}
                message={t.confirmAction}
                confirmLabel={t.confirm}
                cancelLabel={t.cancel}
                onConfirm={() => {
                  try {
                    paymentRepository.voidPayment(payment.id);
                    dispatch(refreshCompleted());
                    setActionError('');
                  } catch (cause) {
                    setActionError(getLedgerErrorMessage(cause, t, t.saveFailed));
                  }
                }}
              />
            ) : null}
          </View>
        );
      })}
    </LedgerPage>
  );
}
