import { useLocalSearchParams } from 'expo-router';
import { CustomerForm } from '../components/customers/CustomerForm';
import { useDirtyFormGuard } from '../hooks/useDirtyFormGuard';

export function CustomerFormScreen() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  useDirtyFormGuard();
  return <CustomerForm key={id ?? 'new'} id={id} />;
}
