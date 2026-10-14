import { useMemo, useState } from 'react';
import { Link } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useDispatch, useSelector } from 'react-redux';
import { RootState } from '@/store';
import { refreshCompleted } from '../store/ledgerSlice';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { ConfirmAction } from '../components/feedback/ConfirmAction';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { LedgerPage } from '../components/LedgerPage';
import { LedgerField } from '../components/forms/LedgerField';
import { Customer } from '../types/customer';
import { listCustomers, removeCustomer } from '../services/customerService';

export function CustomerListScreen() {
  const { t } = useLedgerTranslations();
  const dispatch = useDispatch();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const [search, setSearch] = useState('');
  const [actionError, setActionError] = useState('');
  const result = useMemo(() => {
    try {
      return { customers: listCustomers(search), error: '', version: refreshVersion };
    } catch (cause) {
      return {
        customers: [] as Customer[],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [refreshVersion, search, t]);
  const error = actionError || result.error;

  return (
    <LedgerPage key={result.version} title={t.customers}>
      <Link href="/ledger/customer-form" asChild>
        <Pressable accessibilityRole="button" className="ledger-primary-button">
          <Text className="font-semibold text-white">{t.newCustomer}</Text>
        </Pressable>
      </Link>
      <LedgerField label={t.search} value={search} onChangeText={setSearch} />
      {error ? <FeedbackState kind="error" message={error} /> : null}
      {result.customers.length === 0 && !error ? <FeedbackState kind="empty" message={t.emptyCustomers} /> : null}
      {result.customers.map((customer) => (
        <View key={customer.id} className="ledger-card">
          <View className="flex-row items-start justify-between gap-3">
            <View className="flex-1 gap-1">
              <Text className="text-lg font-semibold text-slate-900">{customer.displayLabel}</Text>
              {customer.realName ? <Text className="text-sm text-slate-600">{customer.realName}</Text> : null}
              {customer.phoneNumber ? <Text className="text-sm text-slate-600">{customer.phoneNumber}</Text> : null}
            </View>
            <Link href={{ pathname: '/ledger/customer-form', params: { id: customer.id } }} asChild>
              <Pressable accessibilityRole="button" className="ledger-touch-target rounded-lg bg-slate-100 px-3 py-2">
                <Text className="font-medium text-slate-800">{t.editCustomer}</Text>
              </Pressable>
            </Link>
          </View>
          <ConfirmAction
            buttonLabel={t.remove}
            title={t.confirmRemoval}
            message={t.removeCustomerMessage}
            confirmLabel={t.confirm}
            cancelLabel={t.cancel}
            onConfirm={() => {
              try {
                removeCustomer(customer.id);
                dispatch(refreshCompleted());
                setActionError('');
              } catch (cause) {
                setActionError(getLedgerErrorMessage(cause, t, t.saveFailed));
              }
            }}
          />
        </View>
      ))}
    </LedgerPage>
  );
}
