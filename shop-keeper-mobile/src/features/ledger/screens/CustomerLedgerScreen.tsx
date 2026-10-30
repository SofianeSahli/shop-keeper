import { useCallback } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { Linking, Pressable, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import { formatMessage } from '@/i18n/format';
import { formatMillimeAmount, formatShortDate } from '@/lib/money';
import {
  AmountText,
  DayHeader,
  EmptyState,
  Icon,
  InfoBanner,
  ListRow,
  RefreshingPill,
  ScreenScroll,
  SkeletonRows,
  StatusChip,
} from '@/components/ui';
import { groupByLocalDay } from '@/components/ui/logic/dayGroups';
import { useTopBarOptions } from '@/navigation/TopBarContext';
import { getCustomer } from '../services/customerService';
import { getCustomerLedgerEntries, listDebtorSummaries } from '../services/ledgerReadService';

async function loadCustomerLedger(customerId: string | undefined) {
  const customer = customerId ? await getCustomer(customerId) : null;
  if (!customer) return { customer: null, summary: null, entries: [] };
  const summaries = await listDebtorSummaries();
  const entries = await getCustomerLedgerEntries(customer.id);
  return { customer, summary: summaries.find((item) => item.customerId === customer.id) ?? null, entries };
}

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
  const loadLedger = useCallback(() => loadCustomerLedger(customerId), [customerId]);
  const query = useLedgerQuery(loadLedger, [customerId]);
  const customer = query.data?.customer ?? null;
  const summary = query.data?.summary ?? null;
  const entries = query.data?.entries ?? [];
  const isMissing = query.status === 'ready' && !customer;

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
    <ScreenScroll>
      {query.status === 'error' ? (
        <InfoBanner tone="error" title={query.error} actionLabel={t.retry} onAction={query.retry} />
      ) : null}
      {isMissing ? <InfoBanner tone="error" title={t.recordUnavailable} /> : null}
      {query.status === 'loading' ? (
        <>
          <SkeletonRows variant="stat" count={1} />
          <SkeletonRows variant="tile" count={4} />
        </>
      ) : null}

      {customer ? (
        <View className={`gap-3 rounded-card border p-4 ${balance > 0 ? 'border-debt-line bg-debt-soft' : 'border-line bg-surface'}`}>
          <View className="flex-row items-start gap-3">
            <View className="flex-1 gap-1">
              <View className="flex-row flex-wrap items-center gap-2">
                <Text className={`type-label ${balance > 0 ? 'text-debt-strong' : ''}`}>{t.remaining}</Text>
                <RefreshingPill visible={query.status === 'refreshing'} />
              </View>
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
              <Icon name="cash-outline" size={20} className="text-on-fill" />
              <Text className="type-button text-on-fill">{t.collect}</Text>
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
