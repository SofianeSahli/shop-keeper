import { useCallback } from 'react';
import { useLocalSearchParams } from 'expo-router';
import { InfoBanner, ScreenScroll, SkeletonRows } from '@/components/ui';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useTopBarOptions } from '@/navigation/TopBarContext';
import { CustomerForm } from '../components/customers/CustomerForm';
import { useDirtyFormGuard } from '../hooks/useDirtyFormGuard';
import { getCustomer } from '../services/customerService';

export function CustomerFormScreen() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const { t } = useLedgerTranslations();
  useDirtyFormGuard();
  useTopBarOptions({ title: id ? t.editCustomer : t.newCustomer });
  const loadCustomer = useCallback(async () => (id ? getCustomer(id) : null), [id]);
  const query = useLedgerQuery(loadCustomer, [id]);

  if (query.status === 'loading') {
    return (
      <ScreenScroll>
        <SkeletonRows variant="tile" count={3} />
      </ScreenScroll>
    );
  }
  if (query.data === undefined || (id && !query.data)) {
    return (
      <ScreenScroll>
        <InfoBanner
          tone="error"
          title={query.status === 'error' ? query.error : t.recordUnavailable}
          actionLabel={query.status === 'error' ? t.retry : undefined}
          onAction={query.retry}
        />
      </ScreenScroll>
    );
  }
  return <CustomerForm key={id ?? 'new'} customer={query.data} />;
}
