import { useMemo } from 'react';
import { Text, View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatLedgerDate } from '@/lib/money';
import { EmptyState, InfoBanner, ScreenScroll, isolateLtr } from '@/components/ui';
import { listAuditEvents } from '../services/auditService';


export function LedgerHistoryScreen() {
  const { t, language } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const result = useMemo(() => {
    try {
      return { events: listAuditEvents(), error: '', version: refreshVersion };
    } catch (cause) {
      return {
        events: [],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [refreshVersion, t]);
  const { events, error } = result;

  return (
    <ScreenScroll key={result.version}>
      {error ? <InfoBanner tone="error" title={error} /> : null}
      {events.length === 0 && !error ? <EmptyState icon="time-outline" message={t.noHistory} /> : null}
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
