import { useMemo, useState } from 'react';
import { router } from 'expo-router';
import { View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { locales } from '@/lib/money';
import { AmountText, Avatar, DayHeader, EmptyState, Fab, FilterChips, InfoBanner, ListRow, ScreenScroll, StatusChip } from '@/components/ui';
import { groupByLocalDay } from '@/components/ui/logic/dayGroups';
import { getCustomerLabel } from '../components/customers/customerLabel';
import { sumActivePaymentsByTicket } from '../services/customerBalanceService';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';
import { filterTicketsByStatus, TicketFilter } from '../services/ticketService';
import { Customer } from '../types/customer';
import { Ticket } from '../types/ticket';

export function TicketListScreen() {
  const { t, language } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const [filter, setFilter] = useState<TicketFilter>('ALL');

  const result = useMemo(() => {
    try {
      return {
        tickets: ticketRepository.listTickets(),
        customers: listCustomers('', true),
        paidByTicket: sumActivePaymentsByTicket(paymentRepository.listPayments()),
        error: '',
        version: refreshVersion,
      };
    } catch (cause) {
      return {
        tickets: [] as Ticket[],
        customers: [] as Customer[],
        paidByTicket: new Map<string, number>(),
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [refreshVersion, t]);

  const visible = filterTicketsByStatus(result.tickets, filter);
  const groups = groupByLocalDay(visible, (ticket) => ticket.createdAt);
  const time = new Intl.DateTimeFormat(locales[language], { hour: '2-digit', minute: '2-digit' });

  return (
    <View className="flex-1">
      <ScreenScroll key={result.version}>
        <FilterChips
          options={[
            { value: 'ALL', label: t.filterAll },
            { value: 'UNPAID', label: t.filterUnpaid },
            { value: 'PARTIALLY_PAID', label: t.filterPartial },
            { value: 'PAID', label: t.filterPaid },
          ]}
          value={filter}
          onChange={setFilter}
          accessibilityLabel={t.tickets}
        />
        {result.error ? <InfoBanner tone="error" title={result.error} /> : null}
        {visible.length === 0 && !result.error ? (
          <EmptyState
            icon="receipt-outline"
            message={result.tickets.length === 0 ? t.noTickets : t.noTicketsForFilter}
            actionLabel={result.tickets.length === 0 ? t.newTicket : undefined}
            onAction={() => router.push('/ledger/ticket-form')}
          />
        ) : null}
        {groups.map((group) => (
          <View key={group.dayKey} className="gap-2">
            <DayHeader dayKey={group.dayKey} />
            <View className="ui-card-flush">
              {group.items.map((ticket, index) => {
                const label = getCustomerLabel(ticket.customerId, result.customers, t);
                const remaining = Math.max(ticket.totalMillimes - (result.paidByTicket.get(ticket.id) ?? 0), 0);
                return (
                  <ListRow
                    key={ticket.id}
                    separated={index > 0}
                    leading={<Avatar label={label} />}
                    title={label}
                    meta={`${formatMessage(t.itemsCount, { count: ticket.lines.length })} · ${time.format(new Date(ticket.createdAt))}`}
                    detail={<StatusChip status={ticket.status} />}
                    trailing={
                      <AmountText millimes={ticket.status === 'PAID' ? ticket.totalMillimes : remaining} tone={ticket.status === 'PAID' ? 'ink' : 'debt'} />
                    }
                    chevron
                    onPress={() => router.push({ pathname: '/ledger/ticket-detail', params: { id: ticket.id } })}
                  />
                );
              })}
            </View>
          </View>
        ))}
      </ScreenScroll>
      <Fab label={t.newTicket} onPress={() => router.push('/ledger/ticket-form')} />
    </View>
  );
}
