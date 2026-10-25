import { useCallback, useState } from 'react';
import { router, useFocusEffect } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { formatLongDay, formatShortDate } from '@/lib/money';
import {
  ActionTile,
  AmountText,
  Avatar,
  EmptyState,
  Icon,
  InfoBanner,
  ListRow,
  ScreenScroll,
  StatCard,
} from '@/components/ui';
import { DashboardSummary, getDashboardSummary } from '../services/dashboardService';
import { DebtorSummary } from '../services/customerBalanceService';
import { listTopDebtors } from '../services/ledgerReadService';

interface HomeData {
  summary: DashboardSummary | null;
  topDebtors: DebtorSummary[];
  error: string;
}

const OWES_FILTER = { pathname: '/ledger/customers', params: { filter: 'owes' } } as const;

export function HomeScreen() {
  const { t, language } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const [data, setData] = useState<HomeData>({ summary: null, topDebtors: [], error: '' });

  const load = useCallback(() => {
    try {
      setData({ summary: getDashboardSummary(), topDebtors: listTopDebtors(3), error: '' });
    } catch (cause) {
      setData({ summary: null, topDebtors: [], error: getLedgerErrorMessage(cause, t, t.loadFailed) });
    }
    // refreshVersion re-runs the load after any saved change.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [t, refreshVersion]);

  useFocusEffect(load);

  const { summary, topDebtors, error } = data;

  return (
    <ScreenScroll>
      <View className="flex-row flex-wrap items-center justify-between gap-2">
        <Text className="type-label capitalize">{formatLongDay(new Date(), language)}</Text>
        <View className="ui-pill bg-paid-soft">
          <Icon name="phone-portrait-outline" size={14} className="text-paid" />
          <Text className="font-plex-semibold text-caption text-paid">{t.savedOnPhone}</Text>
        </View>
      </View>

      {error ? <InfoBanner tone="error" title={error} actionLabel={t.retry} onAction={load} /> : null}

      {summary ? (
        <View className="flex-row gap-3">
          <StatCard
            caption={t.todaySales}
            amountMillimes={summary.todaySalesMillimes}
            meta={formatMessage(t.ticketsCount, { count: summary.todayTicketCount })}
          />
          <StatCard
            caption={t.kridiToCollect}
            amountMillimes={summary.outstandingBalanceMillimes}
            meta={formatMessage(t.openTicketsCount, { count: summary.openTicketCount })}
            tone="debt"
            accessibilityHint={t.customers}
            onPress={() => router.push(OWES_FILTER)}
          />
        </View>
      ) : null}

      <ActionTile
        variant="primary"
        icon="receipt-outline"
        title={t.newTicket}
        subtitle={t.newTicketHint}
        onPress={() => router.push('/ledger/ticket-form')}
      />
      <View className="flex-row gap-3">
        <ActionTile
          variant="secondary"
          icon="cash-outline"
          tint="paid"
          title={t.collect}
          onPress={() => router.push('/ledger/payment-form')}
        />
        <ActionTile
          variant="secondary"
          icon="cube-outline"
          title={t.product}
          onPress={() => router.push('/ledger/product-form')}
        />
      </View>

      {summary ? (
        <View className="ui-card-flush">
          <View className="flex-row items-center justify-between ps-4 pe-2 pt-2">
            <Text accessibilityRole="header" className="type-heading">
              {t.topDebtors}
            </Text>
            <Pressable
              accessibilityRole="link"
              className="min-h-touch justify-center px-2"
              onPress={() => router.push(OWES_FILTER)}
            >
              <Text className="font-plex-semibold text-label text-primary">{t.seeAll}</Text>
            </Pressable>
          </View>
          {topDebtors.length === 0 ? (
            <EmptyState icon="happy-outline" message={t.nobodyOwes} />
          ) : (
            topDebtors.map((debtor) => (
              <ListRow
                key={debtor.customerId}
                separated
                leading={<Avatar label={debtor.displayLabel} />}
                title={debtor.displayLabel}
                meta={[
                  formatMessage(t.ticketsCount, { count: debtor.openTicketCount }),
                  debtor.oldestOpenTicketAt
                    ? formatMessage(t.sinceDate, { date: formatShortDate(debtor.oldestOpenTicketAt, language) })
                    : '',
                ]
                  .filter(Boolean)
                  .join(' · ')}
                trailing={<AmountText millimes={debtor.balanceMillimes} tone="debt" />}
                chevron
                onPress={() =>
                  router.push({ pathname: '/ledger/customer-ledger', params: { customerId: debtor.customerId } })
                }
              />
            ))
          )}
        </View>
      ) : null}
    </ScreenScroll>
  );
}
