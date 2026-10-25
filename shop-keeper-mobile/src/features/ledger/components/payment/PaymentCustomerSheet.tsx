import { useMemo, useState } from 'react';
import { View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatMessage } from '@/i18n/format';
import { AmountText, Avatar, BottomSheet, EmptyState, ListRow, SearchField } from '@/components/ui';
import { DebtorSummary } from '../../services/customerBalanceService';

interface PaymentCustomerSheetProps {
  visible: boolean;
  /** Customers who owe money, highest balance first; the walk-in customer never owes. */
  debtors: readonly DebtorSummary[];
  selectedCustomerId: string;
  onSelect: (customerId: string) => void;
  onClose: () => void;
}

export function PaymentCustomerSheet({ visible, debtors, selectedCustomerId, onSelect, onClose }: PaymentCustomerSheetProps) {
  const { t } = useLedgerTranslations();
  const [query, setQuery] = useState('');
  const filtered = useMemo(() => {
    const search = query.trim().toLocaleLowerCase();
    return search ? debtors.filter((debtor) => debtor.displayLabel.toLocaleLowerCase().includes(search)) : debtors;
  }, [debtors, query]);

  function close(): void {
    setQuery('');
    onClose();
  }

  return (
    <BottomSheet visible={visible} title={t.chooseCustomer} onClose={close}>
      <SearchField value={query} onChangeText={setQuery} placeholder={t.customerSearchPlaceholder} />
      {filtered.length === 0 ? (
        <EmptyState icon="happy-outline" message={debtors.length === 0 ? t.nobodyOwes : t.emptyCustomers} />
      ) : (
        <View className="ui-card-flush">
          {filtered.map((debtor, index) => (
            <ListRow
              key={debtor.customerId}
              separated={index > 0}
              accessibilityRole="radio"
              selected={debtor.customerId === selectedCustomerId}
              leading={<Avatar label={debtor.displayLabel} />}
              title={debtor.displayLabel}
              meta={formatMessage(t.openTicketsCount, { count: debtor.openTicketCount })}
              trailing={<AmountText millimes={debtor.balanceMillimes} tone="debt" />}
              onPress={() => {
                setQuery('');
                onSelect(debtor.customerId);
              }}
            />
          ))}
        </View>
      )}
    </BottomSheet>
  );
}
