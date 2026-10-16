import { router, useLocalSearchParams } from 'expo-router';
import {CustomerForm} from "../components/customers/CustomerForm"
export function CustomerFormScreen() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  return <CustomerForm key={id ?? 'new'} id={id} />;
}
