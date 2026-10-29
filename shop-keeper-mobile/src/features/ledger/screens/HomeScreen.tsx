import { router } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
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
  RefreshingPill,
  ScreenScroll,
  SkeletonRows,
  StatCard,
} from '@/components/ui';
import { getDashboardSummary } from '../services/dashboardService';
import { listTopDebtors } from '../services/ledgerReadService';

const TOP_DEBTOR_COUNT = 3;

async function loadHome() {
  const summary = await getDashboardSummary();
  const topDebtors = await listTopDebtors(TOP_DEBTOR_COUNT);
  return { summary, topDebtors };
}

const OWES_FILTER = { pathname: '/ledger/customers', params: { filter: 'owes' } } as const;

export function HomeScreen() {
  const { t, language } = useLedgerTranslations();
  const home = useLedgerQuery(loadHome);
  const summary = home.data?.summary;
  const topDebtors = home.data?.topDebtors ?? [];
  const isLoading = home.status === 'loading';

  return (
    <ScreenScroll>
      <View className="flex-row flex-wrap items-center justify-between gap-2">
        <Text className="type-label capitalize">{formatLongDay(new Date(), language)}</Text>
        <View className="flex-row items-center gap-2">
          <RefreshingPill visible={home.status === 'refreshing'} />
          <View className="ui-pill bg-paid-soft">
            <Icon name="phone-portrait-outline" size={14} className="text-paid" />
            <Text className="font-plex-semibold text-caption text-paid">{t.savedOnPhone}</Text>
          </View>
        </View>
      </View>

      {home.status === 'error' ? (
        <InfoBanner tone="error" title={home.error} actionLabel={t.retry} onAction={home.retry} />
      ) : null}

      {isLoading ? <SkeletonRows variant="stat" count={2} /> : null}
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

      {isLoading ? <SkeletonRows count={TOP_DEBTOR_COUNT} /> : null}
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
