import { useMemo, useState } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { Text, View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { formatShortDate } from '@/lib/money';
import { AmountText, Avatar, EmptyState, Fab, FilterChips, InfoBanner, ListRow, ScreenScroll, SearchField } from '@/components/ui';
import { DebtorSummary, getCustomerFilterCounts } from '../services/customerBalanceService';
import { listDebtorSummaries } from '../services/ledgerReadService';

type CustomerFilter = 'owes' | 'upToDate' | 'all';
const FILTERS: readonly CustomerFilter[] = ['owes', 'upToDate', 'all'];
const DAY_MS = 24 * 60 * 60 * 1000;

function isCustomerFilter(value: unknown): value is CustomerFilter {
  return typeof value === 'string' && (FILTERS as readonly string[]).includes(value);
}

export function CustomerListScreen() {
  const { t, language } = useLedgerTranslations();
  const params = useLocalSearchParams<{ filter?: string }>();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const [filter, setFilter] = useState<CustomerFilter>(isCustomerFilter(params.filter) ? params.filter : 'owes');
  const [search, setSearch] = useState('');
  // Reference time for the "n days" meta line, read once per visit.
  const [now] = useState(() => Date.now());

  const result = useMemo(() => {
    try {
      return { summaries: listDebtorSummaries(), error: '', version: refreshVersion };
    } catch (cause) {
      return { summaries: [] as DebtorSummary[], error: getLedgerErrorMessage(cause, t, t.loadFailed), version: refreshVersion };
    }
  }, [refreshVersion, t]);

  const counts = getCustomerFilterCounts(result.summaries);
  const query = search.trim().toLocaleLowerCase();
  const visible = result.summaries.filter((summary) => {
    if (filter === 'owes' && summary.balanceMillimes === 0) return false;
    if (filter === 'upToDate' && summary.balanceMillimes > 0) return false;
    return !query || summary.displayLabel.toLocaleLowerCase().includes(query) || (summary.phoneNumber ?? '').includes(query);
  });

  function meta(summary: DebtorSummary): string | undefined {
    if (summary.balanceMillimes > 0 && summary.oldestOpenTicketAt) {
      const days = Math.max(0, Math.floor((now - new Date(summary.oldestOpenTicketAt).getTime()) / DAY_MS));
      return `${formatMessage(t.openTicketsCount, { count: summary.openTicketCount })} · ${formatMessage(t.daysAgo, { count: days })}`;
    }
    return summary.lastPaymentAt ? formatMessage(t.allPaidOn, { date: formatShortDate(summary.lastPaymentAt, language) }) : undefined;
  }

  return (
    <View className="flex-1">
      <ScreenScroll key={result.version}>
        <SearchField value={search} onChangeText={setSearch} placeholder={t.customerSearchPlaceholder} />
        <FilterChips
          options={[
            { value: 'owes', label: formatMessage(t.filterOwes, { count: counts.owes }) },
            { value: 'upToDate', label: formatMessage(t.filterUpToDate, { count: counts.upToDate }) },
            { value: 'all', label: t.filterAll },
          ]}
          value={filter}
          onChange={setFilter}
          accessibilityLabel={t.customers}
        />
        <Text className="type-caption">{t.sortedByDebt}</Text>
        {result.error ? <InfoBanner tone="error" title={result.error} /> : null}
        {visible.length === 0 && !result.error ? (
          <EmptyState
            icon={filter === 'owes' && !query ? 'happy-outline' : 'people-outline'}
            message={filter === 'owes' && !query ? t.nobodyOwes : t.emptyCustomers}
            actionLabel={result.summaries.length === 0 ? t.newCustomer : undefined}
            onAction={() => router.push('/ledger/customer-form')}
          />
        ) : null}
        {visible.length > 0 ? (
          <View className="ui-card-flush">
            {visible.map((summary, index) => (
              <ListRow
                key={summary.customerId}
                separated={index > 0}
                leading={<Avatar label={summary.displayLabel} />}
                title={summary.displayLabel}
                meta={meta(summary)}
                trailing={
                  summary.balanceMillimes > 0 ? (
                    <AmountText millimes={summary.balanceMillimes} tone="debt" />
                  ) : (
                    <Text className="font-plex-semibold text-label text-paid">{t.upToDate}</Text>
                  )
                }
                trailingCaption={summary.balanceMillimes > 0 ? t.owedSuffix : undefined}
                chevron
                onPress={() => router.push({ pathname: '/ledger/customer-ledger', params: { customerId: summary.customerId } })}
              />
            ))}
          </View>
        ) : null}
      </ScreenScroll>
      <Fab label={t.newCustomer} onPress={() => router.push('/ledger/customer-form')} />
    </View>
  );
}
