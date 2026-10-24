import { useMemo, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { formatMillimeAmount } from '@/lib/money';
import { AmountText, Avatar, BottomSheet, EmptyState, Icon, InfoBanner, ListRow, SearchField } from '@/components/ui';
import { DebtorSummary } from '../../services/customerBalanceService';
import { listDebtorSummaries } from '../../services/ledgerReadService';
import { Customer } from '../../types/customer';
import { CustomerForm } from './CustomerForm';

interface CustomerPickerCardProps {
  value: string;
  onChange: (customerId: string) => void;
  /** Validation message; draws the card in the debt color. */
  error?: string;
  /** Shows "Walk-in customer" when nothing is chosen (sale paid in full, spec US8). */
  walkInAllowed?: boolean;
}

function loadSummaries(): { summaries: DebtorSummary[]; error: string | null } {
  try {
    return { summaries: listDebtorSummaries(), error: null };
  } catch (cause) {
    return { summaries: [], error: cause instanceof Error ? cause.message : String(cause) };
  }
}

/** Pinned customer card with current debt, picker sheet and inline creation (spec US3). */
export function CustomerPickerCard({ value, onChange, error, walkInAllowed = false }: CustomerPickerCardProps) {
  const { t, language } = useLedgerTranslations();
  const [state, setState] = useState(loadSummaries);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [query, setQuery] = useState('');
  const selected = state.summaries.find((summary) => summary.customerId === value);
  const loadError = state.error ? getLedgerErrorMessage(new Error(state.error), t, t.loadFailed) : '';

  const filtered = useMemo(() => {
    const search = query.trim().toLocaleLowerCase();
    if (!search) return state.summaries;
    return state.summaries.filter(
      (summary) =>
        summary.displayLabel.toLocaleLowerCase().includes(search) ||
        (summary.phoneNumber ?? '').includes(search),
    );
  }, [query, state.summaries]);

  function closePicker(): void {
    setPickerOpen(false);
    setQuery('');
  }

  function choose(customerId: string): void {
    onChange(customerId);
    closePicker();
  }

  function openCreate(): void {
    closePicker(); // nested modals are unreliable on iOS: close the picker first
    setCreateOpen(true);
  }

  function handleCreated(customer: Customer): void {
    setState(loadSummaries());
    onChange(customer.id);
    setCreateOpen(false);
  }

  const border = error ? 'ui-input-error' : selected ? 'ui-input-selected' : 'border-dashed border-line-strong';
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
          accessibilityRole="button"
          accessibilityLabel={cardLabel}
          accessibilityHint={t.changeCustomer}
          className={`min-h-[64px] flex-1 flex-row items-center gap-3 rounded-control-lg border bg-surface px-3 ${border}`}
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
      {error ? (
        <Text accessibilityLiveRegion="polite" className="font-plex-semibold text-label text-debt">
          {error}
        </Text>
      ) : null}

      <BottomSheet visible={pickerOpen} title={t.chooseCustomer} onClose={closePicker}>
        <SearchField value={query} onChangeText={setQuery} placeholder={t.customerSearchPlaceholder} />
        {loadError ? <InfoBanner tone="error" title={loadError} /> : null}
        {filtered.length === 0 ? (
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
