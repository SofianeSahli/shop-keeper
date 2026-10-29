import { useState } from 'react';
import { router } from 'expo-router';
import { View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import { formatMessage } from '@/i18n/format';
import { locales } from '@/lib/money';
import {
  AmountText,
  Avatar,
  DayHeader,
  EmptyState,
  Fab,
  FilterChips,
  InfoBanner,
  ListRow,
  RefreshingPill,
  ScreenScroll,
  SkeletonRows,
  StatusChip,
} from '@/components/ui';
import { groupByLocalDay } from '@/components/ui/logic/dayGroups';
import { getCustomerLabel } from '../components/customers/customerLabel';
import { sumActivePaymentsByTicket } from '../services/customerBalanceService';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';
import { filterTicketsByStatus, TicketFilter } from '../services/ticketService';

async function loadTicketList() {
  const tickets = await ticketRepository.listTickets();
  const customers = await listCustomers('', true);
  const payments = await paymentRepository.listPayments();
  return { tickets, customers, paidByTicket: sumActivePaymentsByTicket(payments) };
}

export function TicketListScreen() {
  const { t, language } = useLedgerTranslations();
  const [filter, setFilter] = useState<TicketFilter>('ALL');
  const query = useLedgerQuery(loadTicketList);
  const tickets = query.data?.tickets ?? [];
  const customers = query.data?.customers ?? [];
  const paidByTicket = query.data?.paidByTicket;

  const visible = filterTicketsByStatus(tickets, filter);
  const groups = groupByLocalDay(visible, (ticket) => ticket.createdAt);
  const time = new Intl.DateTimeFormat(locales[language], { hour: '2-digit', minute: '2-digit' });

  return (
    <View className="flex-1">
      <ScreenScroll>
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
        <View className="items-end">
          <RefreshingPill visible={query.status === 'refreshing'} />
        </View>
        {query.status === 'error' ? (
          <InfoBanner tone="error" title={query.error} actionLabel={t.retry} onAction={query.retry} />
        ) : null}
        {query.status === 'loading' ? <SkeletonRows count={5} /> : null}
        {visible.length === 0 && query.status === 'ready' ? (
          <EmptyState
            icon="receipt-outline"
            message={tickets.length === 0 ? t.noTickets : t.noTicketsForFilter}
            actionLabel={tickets.length === 0 ? t.newTicket : undefined}
            onAction={() => router.push('/ledger/ticket-form')}
          />
        ) : null}
        {groups.map((group) => (
          <View key={group.dayKey} className="gap-2">
            <DayHeader dayKey={group.dayKey} />
            <View className="ui-card-flush">
              {group.items.map((ticket, index) => {
                const label = getCustomerLabel(ticket.customerId, customers, t);
                const remaining = Math.max(ticket.totalMillimes - (paidByTicket?.get(ticket.id) ?? 0), 0);
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
