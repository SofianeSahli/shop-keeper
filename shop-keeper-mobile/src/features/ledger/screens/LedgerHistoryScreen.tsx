import { Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import { formatLedgerDate } from '@/lib/money';
import { EmptyState, InfoBanner, RefreshingPill, ScreenScroll, SkeletonRows, isolateLtr } from '@/components/ui';
import { listAuditEvents } from '../services/auditService';

function loadAllAuditEvents() {
  return listAuditEvents();
}

export function LedgerHistoryScreen() {
  const { t, language } = useLedgerTranslations();
  const query = useLedgerQuery(loadAllAuditEvents);
  const events = query.data ?? [];

  return (
    <ScreenScroll>
      <View className="items-end">
        <RefreshingPill visible={query.status === 'refreshing'} />
      </View>
      {query.status === 'error' ? (
        <InfoBanner tone="error" title={query.error} actionLabel={t.retry} onAction={query.retry} />
      ) : null}
      {query.status === 'loading' ? <SkeletonRows variant="tile" count={4} /> : null}
      {events.length === 0 && query.status === 'ready' ? <EmptyState icon="time-outline" message={t.noHistory} /> : null}
      {events.map((event) => (
        <View key={event.id} className="ui-card gap-1.5">
          <Text className="type-body-strong">
            {t[event.entityType]} · {t[event.action]}
          </Text>
          <Text className="type-caption">{formatLedgerDate(event.createdAt, language)}</Text>
          {event.before ? (
            <Text className="type-caption text-ink">
              {t.before}: {isolateLtr(JSON.stringify(event.before))}
            </Text>
          ) : null}
          {event.after ? (
            <Text className="type-caption text-ink">
              {t.after}: {isolateLtr(JSON.stringify(event.after))}
            </Text>
          ) : null}
        </View>
      ))}
    </ScreenScroll>
  );
}
