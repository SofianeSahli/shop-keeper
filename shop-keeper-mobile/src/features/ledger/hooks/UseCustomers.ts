// hooks/useCustomers.ts
import { useCallback, useState } from 'react';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { listCustomers } from '../services/customerService';
import { Customer } from '../types/customer';

type CustomersState = {
  customers: Customer[];
  error: string;
};

export function useCustomers() {
  const { t } = useLedgerTranslations();
  const [state, setState] = useState<CustomersState>(() => {
    try {
      return { customers: listCustomers(), error: '' };
    } catch (cause) {
      return { customers: [], error: getLedgerErrorMessage(cause, t, t.loadFailed) };
    }
  });

  const reload = useCallback(() => {
    setState((previous) => {
      try {
        return { customers: listCustomers(), error: '' };
      } catch (cause) {
        // keep the current list, only record the error
        return {
          customers: previous.customers,
          error: getLedgerErrorMessage(cause, t, t.loadFailed),
        };
      }
    });
  }, [t]);

  return { customers: state.customers, error: state.error, reload };
}