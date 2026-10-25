import { useCallback, useMemo } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { Linking, Pressable, Text, View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { formatMillimeAmount, formatShortDate } from '@/lib/money';
import { AmountText, DayHeader, EmptyState, Icon, InfoBanner, ListRow, ScreenScroll, StatusChip } from '@/components/ui';
import { groupByLocalDay } from '@/components/ui/logic/dayGroups';
import { useTopBarOptions } from '@/navigation/TopBarContext';
import { CustomerLedgerEntry, DebtorSummary } from '../services/customerBalanceService';
import { getCustomer } from '../services/customerService';
import { getCustomerLedgerEntries, listDebtorSummaries } from '../services/ledgerReadService';
import { Customer } from '../types/customer';

function EntryTile({ label, tone }: { label: string; tone: 'ticket' | 'payment' }) {
  return (
    <View
      className={`h-10 w-10 items-center justify-center rounded-control ${tone === 'ticket' ? 'bg-primary-soft' : 'bg-paid-soft'}`}
    >
      <Text className={`font-plex-bold text-heading ${tone === 'ticket' ? 'text-primary' : 'text-paid'}`}>{label}</Text>
    </View>
  );
}

export function CustomerLedgerScreen() {
  const { customerId } = useLocalSearchParams<{ customerId: string }>();
  const { t, language } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);

  const result = useMemo(() => {
    try {
      const customer = customerId ? getCustomer(customerId) : null;
      return {
        customer,
        summary: listDebtorSummaries().find((item) => item.customerId === customerId) ?? null,
        entries: customer ? getCustomerLedgerEntries(customer.id) : [],
        error: customer ? '' : t.recordUnavailable,
        version: refreshVersion,
      };
    } catch (cause) {
      return {
        customer: null as Customer | null,
        summary: null as DebtorSummary | null,
        entries: [] as CustomerLedgerEntry[],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [customerId, refreshVersion, t]);
  const { customer, summary, entries } = result;

  const editCustomer = useCallback(() => {
    if (customerId) router.push({ pathname: '/ledger/customer-form', params: { id: customerId } });
  }, [customerId]);
  useTopBarOptions({
    title: customer?.displayLabel,
    action: customer?.isActive ? { icon: 'create-outline', label: t.editCustomer, onPress: editCustomer } : undefined,
  });

  const groups = groupByLocalDay(entries, (entry) => entry.at);
  const balance = summary?.balanceMillimes ?? 0;

  return (
    <ScreenScroll key={result.version}>
      {result.error ? <InfoBanner tone="error" title={result.error} /> : null}

      {customer ? (
        <View className={`gap-3 rounded-card border p-4 ${balance > 0 ? 'border-debt-line bg-debt-soft' : 'border-line bg-surface'}`}>
          <View className="flex-row items-start gap-3">
            <View className="flex-1 gap-1">
              <Text className={`type-label ${balance > 0 ? 'text-debt-strong' : ''}`}>{t.remaining}</Text>
              <AmountText millimes={balance} size="display-lg" tone={balance > 0 ? 'debt' : 'paid'} fitToWidth />
              {summary && summary.openTicketCount > 0 ? (
                <Text className="type-caption text-debt-strong">
                  {formatMessage(t.openTicketsCount, { count: summary.openTicketCount })}
                  {summary.oldestOpenTicketAt
                    ? ` · ${formatMessage(t.oldestOpen, { date: formatShortDate(summary.oldestOpenTicketAt, language) })}`
                    : ''}
                </Text>
              ) : (
                <Text className="font-plex-semibold text-label text-paid">{t.upToDate}</Text>
              )}
            </View>
            {customer.phoneNumber ? (
              <Pressable
                accessibilityRole="button"
                accessibilityLabel={`${t.call} ${customer.displayLabel}`}
                className="h-12 w-12 items-center justify-center rounded-full bg-paid-soft"
                onPress={() => void Linking.openURL(`tel:${customer.phoneNumber}`)}
              >
                <Icon name="call" className="text-paid" />
              </Pressable>
            ) : null}
          </View>
          <View className="flex-row gap-3">
            <Pressable
              accessibilityRole="button"
              className="ui-button-outline flex-1"
              onPress={() => router.push({ pathname: '/ledger/ticket-form', params: { customerId: customer.id } })}
            >
              <Icon name="receipt-outline" size={20} className="text-primary" />
              <Text className="type-button text-primary">{t.ticket}</Text>
            </Pressable>
            <Pressable
              accessibilityRole="button"
              className="ui-button-paid flex-1"
              onPress={() => router.push({ pathname: '/ledger/payment-form', params: { customerId: customer.id } })}
            >
              <Icon name="cash-outline" size={20} className="text-surface" />
              <Text className="type-button text-surface">{t.collect}</Text>
            </Pressable>
          </View>
        </View>
      ) : null}

      {customer ? (
        <Text accessibilityRole="header" className="type-heading pt-1">
          {t.history}
        </Text>
      ) : null}
      {customer && entries.length === 0 ? <EmptyState icon="time-outline" message={t.noHistoryForCustomer} /> : null}
      {groups.map((group) => (
        <View key={group.dayKey} className="gap-2">
          <DayHeader dayKey={group.dayKey} />
          <View className="ui-card-flush">
            {group.items.map((entry, index) =>
              entry.type === 'ticket' ? (
                <ListRow
                  key={entry.id}
                  separated={index > 0}
                  leading={<EntryTile label={t.ticketInitial} tone="ticket" />}
                  title={formatMessage(t.ticketItemsCount, { count: entry.ticket.lines.length })}
                  detail={<StatusChip status={entry.ticket.status} />}
                  trailing={<AmountText millimes={entry.ticket.totalMillimes} />}
                  trailingCaption={
                    entry.remainingMillimes > 0
                      ? formatMessage(t.remainingShort, { amount: formatMillimeAmount(entry.remainingMillimes, language) })
                      : undefined
                  }
                  chevron
                  onPress={() => router.push({ pathname: '/ledger/ticket-detail', params: { id: entry.ticket.id } })}
                />
              ) : (
                <ListRow
                  key={entry.id}
                  separated={index > 0}
                  leading={<EntryTile label="−" tone="payment" />}
                  title={entry.payment.paymentKind === 'AT_SALE' ? t.paidAtSale : t.paidCash}
                  detail={<StatusChip status="PAYMENT" />}
                  trailing={<AmountText millimes={entry.payment.amountMillimes} signed tone="paid" />}
                  trailingCaption={
                    entry.ticketCreatedAt
                      ? formatMessage(t.onTicketOf, { date: formatShortDate(entry.ticketCreatedAt, language) })
                      : undefined
                  }
                  onPress={() => router.push({ pathname: '/ledger/payment-form', params: { id: entry.payment.id } })}
                />
              ),
            )}
          </View>
        </View>
      ))}

      {customer ? (
        <View className="ui-card-flush">
          <ListRow
            leading={<Icon name="time-outline" className="text-ink-2" />}
            title={t.auditHistory}
            chevron
            onPress={() => router.push('/ledger/history')}
          />
        </View>
      ) : null}
    </ScreenScroll>
  );
}
