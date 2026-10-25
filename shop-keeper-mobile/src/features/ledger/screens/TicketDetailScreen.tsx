import { useMemo, useState } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useDispatch, useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { formatLedgerDate, formatMillimeAmount } from '@/lib/money';
import { AmountText, Icon, InfoBanner, ListRow, ScreenScroll, StatusChip, isolateLtr } from '@/components/ui';
import { useTopBarOptions } from '@/navigation/TopBarContext';
import { refreshCompleted } from '../store/ledgerSlice';
import { ConfirmAction } from '../components/feedback/ConfirmAction';
import { getCustomerLabel } from '../components/customers/customerLabel';
import { isWalkInCustomer } from '../constants';
import { listAuditEvents } from '../services/auditService';
import { listCustomers } from '../services/customerService';
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
        customers: listCustomers('', true),
        auditEvents: listAuditEvents('ticket', id),
        error: '',
        version: refreshVersion,
      };
    } catch (cause) {
      return {
        ticket: null,
        payments: [],
        customers: [],
        auditEvents: [],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [id, refreshVersion, t]);
  const { ticket, payments, auditEvents } = result;
  useTopBarOptions({ title: t.ticketDetails });

  if (!ticket) {
    return (
      <ScreenScroll>
        <InfoBanner tone="error" title={result.error || t.recordUnavailable} />
      </ScreenScroll>
    );
  }

  const customerLabel = getCustomerLabel(ticket.customerId, result.customers, t);
  const activePayments = payments.filter((payment) => !payment.voidedAt);
  const paid = activePayments.reduce((sum, payment) => sum + payment.amountMillimes, 0);
  const remaining = Math.max(ticket.totalMillimes - paid, 0);
  const hasSalePayment = activePayments.some((payment) => payment.paymentKind === 'AT_SALE');
  const canCollect = !ticket.voidedAt && remaining > 0 && !isWalkInCustomer(ticket.customerId);

  function voidTicket(): void {
    try {
      ticketRepository.voidTicket(id);
      dispatch(refreshCompleted());
      setError('');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  return (
    <ScreenScroll key={result.version}>
      {error ? <InfoBanner tone="error" title={error} /> : null}
      {ticket.voidedAt ? <InfoBanner tone="warning" title={t.void} text={formatLedgerDate(ticket.voidedAt, language)} /> : null}

      <View className="ui-card">
        <View className="flex-row items-start justify-between gap-3">
          <View className="flex-1 gap-1">
            <Text className="type-heading">{customerLabel}</Text>
            <Text className="type-caption">{formatLedgerDate(ticket.createdAt, language)}</Text>
            <StatusChip status={ticket.status} />
          </View>
          <View className="items-end gap-1">
            <AmountText millimes={ticket.totalMillimes} size="heading" />
            {remaining > 0 ? (
              <Text className="font-plex-semibold text-caption text-debt">
                {formatMessage(t.remainingShort, { amount: formatMillimeAmount(remaining, language) })}
              </Text>
            ) : null}
          </View>
        </View>
      </View>

      <View className="ui-card-flush">
        {ticket.lines.map((line, index) => (
          <ListRow
            key={line.id}
            separated={index > 0}
            title={line.productNameSnapshot}
            meta={isolateLtr(
              formatMessage(t.lineFormula, {
                unit: formatMillimeAmount(line.unitPriceMillimes, language),
                quantity: line.quantity,
                total: formatMillimeAmount(line.lineTotalMillimes, language),
              }),
            )}
            trailing={<AmountText millimes={line.lineTotalMillimes} />}
          />
        ))}
      </View>

      {payments.length > 0 ? (
        <>
          <Text accessibilityRole="header" className="type-heading pt-1">
            {t.paymentHistory}
          </Text>
          <View className="ui-card-flush">
            {payments.map((payment, index) => (
              <ListRow
                key={payment.id}
                separated={index > 0}
                title={payment.paymentKind === 'AT_SALE' ? t.paidAtSale : t.paidCash}
                meta={formatLedgerDate(payment.appliedAt, language)}
                trailing={<AmountText millimes={payment.amountMillimes} signed tone={payment.voidedAt ? 'ink' : 'paid'} />}
                trailingCaption={payment.voidedAt ? t.cancelledEntry : undefined}
              />
            ))}
          </View>
        </>
      ) : null}

      {auditEvents.length > 0 ? (
        <>
          <Text accessibilityRole="header" className="type-heading pt-1">
            {t.auditHistory}
          </Text>
          <View className="ui-card-flush">
            {auditEvents.map((event, index) => (
              <ListRow key={event.id} separated={index > 0} title={t[event.action]} meta={formatLedgerDate(event.createdAt, language)} />
            ))}
          </View>
        </>
      ) : null}

      {!ticket.voidedAt ? (
        <View className="gap-3">
          {canCollect ? (
            <Pressable
              accessibilityRole="button"
              className="ui-button-paid"
              onPress={() => router.push({ pathname: '/ledger/payment-form', params: { customerId: ticket.customerId } })}
            >
              <Icon name="cash-outline" size={20} className="text-surface" />
              <Text className="type-button text-surface">{t.collect}</Text>
            </Pressable>
          ) : null}
          <Pressable
            accessibilityRole="button"
            className="ui-button-outline"
            onPress={() => router.push({ pathname: '/ledger/ticket-form', params: { id: ticket.id } })}
          >
            <Icon name="create-outline" size={20} className="text-primary" />
            <Text className="type-button text-primary">{t.editTicket}</Text>
          </Pressable>
          <ConfirmAction
            buttonLabel={t.voidTicket}
            title={t.voidTicket}
            message={hasSalePayment ? t.voidTicketWithSalePayment : t.voidTicketMessage}
            confirmLabel={t.confirm}
            cancelLabel={t.cancel}
            icon="close-circle-outline"
            onConfirm={voidTicket}
          />
        </View>
      ) : null}
    </ScreenScroll>
  );
}
