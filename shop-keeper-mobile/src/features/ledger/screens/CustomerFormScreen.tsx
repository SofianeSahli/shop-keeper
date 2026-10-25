import { useLocalSearchParams } from 'expo-router';
import { CustomerForm } from '../components/customers/CustomerForm';
import { useDirtyFormGuard } from '../hooks/useDirtyFormGuard';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useTopBarOptions } from '@/navigation/TopBarContext';

export function CustomerFormScreen() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const { t } = useLedgerTranslations();
  useDirtyFormGuard();
  useTopBarOptions({ title: id ? t.editCustomer : t.newCustomer });
  return <CustomerForm key={id ?? 'new'} id={id} />;
}
