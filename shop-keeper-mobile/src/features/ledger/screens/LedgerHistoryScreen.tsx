import { useMemo } from 'react';
import { Text, View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatLedgerDate } from '@/lib/money';
import { listAuditEvents } from '../services/auditService';
import { LedgerPage } from '../components/LedgerPage';
import { FeedbackState } from '../components/feedback/FeedbackState';

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
    <LedgerPage key={result.version} title={t.auditHistory}>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      {events.map((event) => (
        <View key={event.id} className="ledger-card">
          <Text className="font-semibold text-slate-900">
            {t[event.entityType]} · {t[event.action]}
          </Text>
          <Text className="text-sm text-slate-600">{formatLedgerDate(event.createdAt, language)}</Text>
          <Text className="text-xs text-slate-500">{event.entityId}</Text>
          {event.before ? (
            <Text className="text-sm text-slate-700">{t.before}: {JSON.stringify(event.before)}</Text>
          ) : null}
          {event.after ? (
            <Text className="text-sm text-slate-700">{t.after}: {JSON.stringify(event.after)}</Text>
          ) : null}
        </View>
      ))}
      {events.length === 0 ? <Text className="text-slate-600">{t.noHistory}</Text> : null}
    </LedgerPage>
  );
}
