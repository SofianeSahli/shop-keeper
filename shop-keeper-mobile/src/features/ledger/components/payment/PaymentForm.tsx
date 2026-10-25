import { Pressable, Text, TextInput, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { formatMessage } from '@/i18n/format';
import { formatMillimeAmount, formatShortDate } from '@/lib/money';
import { Avatar, EmptyState, FilterChips, Icon } from '@/components/ui';
import type { FilterOption } from '@/components/ui';
import { getQuickAmountOptions } from '@/components/ui/logic/quickAmounts';
import { QUICK_AMOUNTS_MILLIMES } from '../../constants';
import { DebtorSummary } from '../../services/customerBalanceService';
import { PayableTicket } from './PaymentTicketSheet';

interface PaymentFormProps {
  customer: DebtorSummary | null;
  selected: PayableTicket | null;
  hasPayableTickets: boolean;
  amountText: string;
  /** Parsed amount in millimes, or null while the text is not a valid amount. */
  amountMillimes: number | null;
  amountError?: string;
  canChangeCustomer: boolean;
  onChangeCustomer: () => void;
  onChangeTicket: () => void;
  onAmountTextChange: (value: string) => void;
  onQuickAmount: (amountMillimes: number) => void;
}

/** Customer, ticket to pay, amount, quick amounts and the live "remaining after" line (spec US4). */
export function PaymentForm({
  customer,
  selected,
  hasPayableTickets,
  amountText,
  amountMillimes,
  amountError,
  canChangeCustomer,
  onChangeCustomer,
  onChangeTicket,
  onAmountTextChange,
  onQuickAmount,
}: PaymentFormProps) {
  const { t, language } = useLedgerTranslations();
  const money = (value: number) => formatMillimeAmount(value, language);
  const remaining = selected?.remainingMillimes ?? 0;
  const quickOptions = getQuickAmountOptions(remaining, QUICK_AMOUNTS_MILLIMES);
  const chips: FilterOption<string>[] = quickOptions.map((option) => ({
    value: String(option.id),
    label: option.id === 'whole' ? t.wholeTicket : `${option.id / 1000} ${t.currencySuffix}`,
    disabled: !option.enabled,
  }));
  const selectedChip =
    amountMillimes === null ? null : (quickOptions.find((option) => option.amountMillimes === amountMillimes && option.enabled)?.id ?? null);
  const after = amountMillimes === null ? null : remaining - amountMillimes;

  return (
    <View className="gap-3.5">
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={customer ? `${t.customer}: ${customer.displayLabel}` : t.chooseCustomer}
        accessibilityHint={canChangeCustomer ? t.changeCustomer : undefined}
        disabled={!canChangeCustomer}
        className="ui-card min-h-[64px] flex-row items-center gap-3"
        onPress={onChangeCustomer}
      >
        {customer ? <Avatar label={customer.displayLabel} /> : <Icon name="person-outline" className="text-ink-2" />}
        <View className="flex-1 gap-0.5">
          <Text numberOfLines={1} className="type-body-strong">
            {customer?.displayLabel ?? t.chooseCustomer}
          </Text>
          {customer ? (
            <Text numberOfLines={1} className="font-plex-semibold text-caption text-debt">
              {formatMessage(t.owesInTotal, { amount: money(customer.balanceMillimes), count: customer.openTicketCount })}
            </Text>
          ) : null}
        </View>
        {canChangeCustomer ? <Text className="font-plex-semibold text-label text-primary">{t.change}</Text> : null}
      </Pressable>

      {customer && !hasPayableTickets ? <EmptyState icon="checkmark-done-outline" message={t.noUnpaidTickets} /> : null}

      {selected ? (
        <>
          <Text className="type-label">{t.ticketToPay}</Text>
          <Pressable
            accessibilityRole="button"
            accessibilityHint={t.selectTicket}
            className="ui-card min-h-[64px] flex-row items-center gap-3"
            onPress={onChangeTicket}
          >
            <View className="h-10 w-10 items-center justify-center rounded-control bg-primary-soft">
              <Icon name="receipt-outline" size={20} className="text-primary" />
            </View>
            <View className="flex-1 gap-0.5">
              <Text className="type-body-strong">
                {formatMessage(t.ticketOfDate, { date: formatShortDate(selected.ticket.createdAt, language) })}
              </Text>
              <Text className="type-caption">
                {formatMessage(t.remainingOf, { remaining: money(remaining), total: money(selected.ticket.totalMillimes) })}
              </Text>
            </View>
            <Text className="font-plex-semibold text-label text-primary">{t.change}</Text>
          </Pressable>

          <Text className="type-label">{t.amountReceived}</Text>
          <View
            className={`min-h-[72px] flex-row items-center rounded-control-lg border bg-surface px-4 ${
              amountError ? 'border-[1.5px] border-debt' : 'border-line-strong'
            }`}
          >
            <TextInput
              accessibilityLabel={t.amountReceived}
              accessibilityHint={amountError}
              value={amountText}
              onChangeText={onAmountTextChange}
              keyboardType="decimal-pad"
              placeholder="0,000"
              placeholderClassName="text-ink-2"
              textAlign="left"
              className="min-h-[70px] flex-1 font-plex-bold text-display-lg text-ink tabular-nums"
            />
            <Text className="font-plex-bold text-title text-ink-2">{t.currencySuffix}</Text>
          </View>
          {amountError ? (
            <Text accessibilityLiveRegion="polite" className="font-plex text-label text-debt">
              {amountError}
            </Text>
          ) : null}
          <FilterChips
            options={chips}
            value={selectedChip === null ? null : String(selectedChip)}
            onChange={(value) => {
              const option = quickOptions.find((item) => String(item.id) === value);
              if (option) onQuickAmount(option.amountMillimes);
            }}
            accessibilityLabel={t.amountReceived}
          />
          {after !== null && after >= 0 && !amountError ? (
            after === 0 ? (
              <View className="ui-pill w-full self-stretch rounded-control bg-paid-soft py-3">
                <Icon name="checkmark-circle" size={18} className="text-paid" />
                <Text className="font-plex-bold text-body text-paid">
                  {t.ticketFullyPaid} · {t.paid}
                </Text>
              </View>
            ) : (
              <View className="flex-row items-center justify-between rounded-control bg-debt-soft px-3 py-3">
                <Text className="font-plex-semibold text-body text-debt-strong">{t.remainingAfter}</Text>
                <Text className="font-plex-bold text-body text-debt">{money(after)}</Text>
              </View>
            )
          ) : null}
        </>
      ) : null}
    </View>
  );
}
