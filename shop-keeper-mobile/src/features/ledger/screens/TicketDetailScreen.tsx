import { Link, useLocalSearchParams } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useMemo, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatLedgerDate, formatMillimeAmount } from '@/lib/money';
import { refreshCompleted } from '../store/ledgerSlice';
import { ConfirmAction } from '../components/feedback/ConfirmAction';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { LedgerPage } from '../components/LedgerPage';
import { listAuditEvents } from '../services/auditService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';

export function TicketDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const [error, setError] = useState('');
  const result = useMemo(() => {
    try {
      return {
        ticket: ticketRepository.getTicket(id),
        payments: paymentRepository.listPayments(undefined, true).filter((payment) => payment.ticketId === id),
        auditEvents: listAuditEvents('ticket', id),
        error: '',
        version: refreshVersion,
      };
    } catch (cause) {
      return {
        ticket: null,
        payments: [],
        auditEvents: [],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [id, refreshVersion, t]);
  const { ticket, payments, auditEvents } = result;

  if (!ticket) {
    return <LedgerPage title={t.tickets}><FeedbackState kind="error" message={result.error || t.loadFailed} /></LedgerPage>;
  }

  const statusLabel = ticket.status === 'PAID'
    ? t.paid
    : ticket.status === 'PARTIALLY_PAID'
      ? t.partiallyPaid
      : t.unpaid;

  return (
    <LedgerPage key={result.version} title={t.ticketDetails}>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      <View className="ledger-card">
        <Text className="text-sm text-slate-600">{formatLedgerDate(ticket.createdAt, language)}</Text>
        <Text className="font-semibold text-slate-900">{t.status}: {statusLabel}</Text>
        {ticket.voidedAt ? <Text className="text-red-700">{t.voidTicket}</Text> : null}
        {ticket.lines.map((line) => (
          <View key={line.id} className="flex-row justify-between gap-3 border-t border-slate-100 pt-2">
            <View className="flex-1">
              <Text className="font-medium text-slate-900">{line.productNameSnapshot}</Text>
              {line.barcodeSnapshot ? <Text className="text-xs text-slate-500">{line.barcodeSnapshot}</Text> : null}
              <Text className="text-sm text-slate-600">
                {line.quantity} × {formatMillimeAmount(line.unitPriceMillimes, language)}
              </Text>
            </View>
            <Text className="font-semibold text-slate-900">{formatMillimeAmount(line.lineTotalMillimes, language)}</Text>
          </View>
        ))}
        <Text className="text-right text-lg font-bold text-blue-900">
          {formatMillimeAmount(ticket.totalMillimes, language)}
        </Text>
      </View>
      <Text className="text-lg font-bold text-slate-900">{t.paymentHistory}</Text>
      {payments.map((payment) => (
        <View key={payment.id} className="ledger-card flex-row justify-between">
          <Text className="text-slate-700">{formatLedgerDate(payment.appliedAt, language, false)}</Text>
          <Text className="font-semibold text-slate-900">{formatMillimeAmount(payment.amountMillimes, language)}</Text>
          {payment.voidedAt ? <Text className="text-red-700">{t.voidPayment}</Text> : null}
        </View>
      ))}
      <Text className="text-lg font-bold text-slate-900">{t.auditHistory}</Text>
      {auditEvents.map((event) => (
        <View key={event.id} className="ledger-card">
          <Text className="font-semibold text-slate-900">{t[event.action]}</Text>
          <Text className="text-sm text-slate-600">{formatLedgerDate(event.createdAt, language)}</Text>
        </View>
      ))}
      {!ticket.voidedAt ? (
        <>
          <Link href={{ pathname: '/ledger/ticket-form', params: { id } }} asChild>
            <Pressable accessibilityRole="button" className="ledger-primary-button">
              <Text className="font-semibold text-white">{t.editTicket}</Text>
            </Pressable>
          </Link>
          <ConfirmAction
            buttonLabel={t.voidTicket}
            title={t.confirmRemoval}
            message={t.confirmAction}
            confirmLabel={t.confirm}
            cancelLabel={t.cancel}
            onConfirm={() => {
              try {
                ticketRepository.voidTicket(id);
                dispatch(refreshCompleted());
                setError('');
              } catch (cause) {
                setError(getLedgerErrorMessage(cause, t, t.saveFailed));
              }
            }}
          />
        </>
      ) : null}
    </LedgerPage>
  );
}
