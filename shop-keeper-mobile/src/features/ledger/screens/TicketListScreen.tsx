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
import { Ticket } from '../types/ticket';
import { listCustomers } from '../services/customerService';
import { ticketRepository } from '../services/ticketRepository';

export function TicketListScreen() {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const [actionError, setActionError] = useState('');
  const result = useMemo(() => {
    try {
      return {
        tickets: ticketRepository.listTickets(),
        customers: listCustomers('', true),
        error: '',
        version: refreshVersion,
      };
    } catch (cause) {
      return {
        tickets: [] as Ticket[],
        customers: [] as Customer[],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [refreshVersion, t]);
  const error = actionError || result.error;

  return (
    <LedgerPage key={result.version} title={t.tickets}>
      <Link href="/ledger/ticket-form" asChild>
        <Pressable accessibilityRole="button" className="ledger-primary-button">
          <Text className="font-semibold text-white">{t.newTicket}</Text>
        </Pressable>
      </Link>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      {result.tickets.length === 0 && !error ? <FeedbackState kind="empty" message={t.noTickets} /> : null}
      {result.tickets.map((ticket) => {
        const customer = result.customers.find((item) => item.id === ticket.customerId);
        const statusLabel = ticket.status === 'PAID'
          ? t.paid
          : ticket.status === 'PARTIALLY_PAID'
            ? t.partiallyPaid
            : t.unpaid;
        return (
          <View key={ticket.id} className="ledger-card">
            <View className="flex-row items-start justify-between gap-3">
              <View className="flex-1 gap-1">
                <Link href={{ pathname: '/ledger/ticket-detail', params: { id: ticket.id } }}>
                  <Text className="text-lg font-semibold text-slate-900">
                    {customer?.displayLabel ?? ticket.customerId}
                  </Text>
                </Link>
                <Text className="text-sm text-slate-600">{formatLedgerDate(ticket.createdAt, language)}</Text>
                <Text className="font-semibold text-blue-800">{formatMillimeAmount(ticket.totalMillimes, language)}</Text>
                <Text className="text-sm text-slate-700">{t.status}: {statusLabel}</Text>
              </View>
              <Link href={{ pathname: '/ledger/ticket-form', params: { id: ticket.id } }} asChild>
                <Pressable accessibilityRole="button" className="ledger-touch-target rounded-lg bg-slate-100 px-3 py-2">
                  <Text className="font-medium text-slate-800">{t.editTicket}</Text>
                </Pressable>
              </Link>
            </View>
            <ConfirmAction
              buttonLabel={t.voidTicket}
              title={t.confirmRemoval}
              message={t.confirmAction}
              confirmLabel={t.confirm}
              cancelLabel={t.cancel}
              onConfirm={() => {
                try {
                  ticketRepository.voidTicket(ticket.id);
                  dispatch(refreshCompleted());
                  setActionError('');
                } catch (cause) {
                  setActionError(getLedgerErrorMessage(cause, t, t.saveFailed));
                }
              }}
            />
          </View>
        );
      })}
    </LedgerPage>
  );
}
