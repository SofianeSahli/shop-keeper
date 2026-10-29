import { Ref, useMemo, useState } from 'react';
import { HostInstance, Pressable, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import { formatMessage } from '@/i18n/format';
import { formatMillimeAmount } from '@/lib/money';
import { AmountText, Avatar, BottomSheet, EmptyState, FieldErrorText, Icon, InfoBanner, ListRow, SearchField, SkeletonRows } from '@/components/ui';
import { listDebtorSummaries } from '../../services/ledgerReadService';
import { Customer } from '../../types/customer';
import { CustomerForm } from './CustomerForm';

interface CustomerPickerCardProps {
  value: string;
  onChange: (customerId: string) => void;
  error?: string;
  walkInAllowed?: boolean;
  ref?: Ref<HostInstance>;
}

export function CustomerPickerCard({ value, onChange, error, walkInAllowed = false, ref }: CustomerPickerCardProps) {
  const { t, language } = useLedgerTranslations();
  const debtors = useLedgerQuery(listDebtorSummaries);
  const summaries = useMemo(() => debtors.data ?? [], [debtors.data]);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [query, setQuery] = useState('');
  const selected = summaries.find((summary) => summary.customerId === value);

  const filtered = useMemo(() => {
    const search = query.trim().toLocaleLowerCase();
    if (!search) return summaries;
    return summaries.filter(
      (summary) =>
        summary.displayLabel.toLocaleLowerCase().includes(search) ||
        (summary.phoneNumber ?? '').includes(search),
    );
  }, [query, summaries]);

  function closePicker(): void {
    setPickerOpen(false);
    setQuery('');
  }

  function choose(customerId: string): void {
    onChange(customerId);
    closePicker();
  }

  function openCreate(): void {
    closePicker();
    setCreateOpen(true);
  }

  function handleCreated(customer: Customer): void {
    onChange(customer.id);
    setCreateOpen(false);
  }

  const border = error ? 'ui-input-error' : selected ? 'ui-input-selected' : 'border-dashed border-line-dashed';
  const cardLabel = selected
    ? `${t.customer}: ${selected.displayLabel}, ${formatMessage(t.alreadyOwes, {
        amount: formatMillimeAmount(selected.balanceMillimes, language),
      })}`
    : `${t.customer}: ${walkInAllowed ? t.walkInCustomer : t.chooseCustomer}`;

  return (
    <View className="gap-1.5">
      <Text className="type-label">{t.customer}</Text>
      <View className="flex-row gap-2">
        <Pressable
          ref={ref}
          accessibilityRole="button"
          accessibilityLabel={cardLabel}
          accessibilityHint={error ?? t.changeCustomer}
          className={`min-h-[64px] flex-1 flex-row items-center gap-3 rounded-control-lg border bg-surface-2 px-3 ${border}`}
          onPress={() => setPickerOpen(true)}
        >
          {selected ? (
            <>
              <Avatar label={selected.displayLabel} />
              <View className="flex-1 gap-0.5">
                <Text numberOfLines={1} className="type-body-strong">
                  {selected.displayLabel}
                </Text>
                {selected.balanceMillimes > 0 ? (
                  <Text numberOfLines={1} className="font-plex-semibold text-caption text-debt">
                    {formatMessage(t.alreadyOwes, { amount: formatMillimeAmount(selected.balanceMillimes, language) })}
                  </Text>
                ) : null}
              </View>
            </>
          ) : (
            <>
              <View className="h-10 w-10 items-center justify-center rounded-full bg-muted">
                <Icon name={walkInAllowed ? 'walk-outline' : 'person-outline'} className="text-ink-2" />
              </View>
              <View className="flex-1 gap-0.5">
                <Text numberOfLines={1} className="type-body-strong">
                  {walkInAllowed ? t.walkInCustomer : t.chooseCustomer}
                </Text>
                {walkInAllowed ? <Text className="type-caption">{t.chooseCustomer}</Text> : null}
              </View>
            </>
          )}
          <Icon name="chevron-forward" directional size={18} className="text-ink-2" />
        </Pressable>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={t.newCustomer}
          className="min-h-[64px] w-14 items-center justify-center gap-0.5 rounded-control-lg border border-dashed border-primary bg-primary-faint"
          onPress={openCreate}
        >
          <Icon name="person-add-outline" size={20} className="text-primary" />
          <Text numberOfLines={1} className="font-plex-semibold text-[11px] text-primary">
            {t.newShort}
          </Text>
        </Pressable>
      </View>
      <FieldErrorText message={error} />

      <BottomSheet visible={pickerOpen} title={t.chooseCustomer} onClose={closePicker}>
        <SearchField value={query} onChangeText={setQuery} placeholder={t.customerSearchPlaceholder} />
        {debtors.status === 'error' ? (
          <InfoBanner tone="error" title={debtors.error} actionLabel={t.retry} onAction={debtors.retry} />
        ) : null}
        {debtors.status === 'loading' ? <SkeletonRows count={4} /> : null}
        {debtors.status === 'loading' ? null : filtered.length === 0 ? (
          <EmptyState icon="people-outline" message={t.emptyCustomers} actionLabel={t.newCustomer} onAction={openCreate} />
        ) : (
          <View className="ui-card-flush">
            {filtered.map((summary, index) => (
              <ListRow
                key={summary.customerId}
                separated={index > 0}
                accessibilityRole="radio"
                selected={summary.customerId === value}
                leading={<Avatar label={summary.displayLabel} />}
                title={summary.displayLabel}
                trailing={
                  summary.balanceMillimes > 0 ? <AmountText millimes={summary.balanceMillimes} tone="debt" /> : undefined
                }
                onPress={() => choose(summary.customerId)}
              />
            ))}
          </View>
        )}
      </BottomSheet>

      <BottomSheet visible={createOpen} title={t.newCustomer} helper={t.draftKept} onClose={() => setCreateOpen(false)}>
        <CustomerForm embedded onSaved={handleCreated} />
      </BottomSheet>
    </View>
  );
}
