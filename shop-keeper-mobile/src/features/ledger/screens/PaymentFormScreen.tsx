import { useMemo, useState } from 'react';
import { Pressable, Text } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMillimeInput } from '@/lib/money';
import { refreshCompleted } from '../store/ledgerSlice';
import { LedgerPage } from '../components/LedgerPage';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { PaymentForm } from '../components/payment/PaymentForm';
import { getCustomerPaymentCandidates } from '../services/customerBalanceService';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';
import { Customer } from '../types/customer';
import { Payment } from '../types/payment';
import { Ticket } from '../types/ticket';
import { parseMillimes } from '../components/forms/MillimeInput';

export function PaymentFormScreen() {
  const { id, customerId } = useLocalSearchParams<{ id?: string; customerId?: string }>();
  return <PaymentFormPage key={id ?? customerId ?? 'new'} id={id} initialCustomerId={customerId} />;
}

function PaymentFormPage({ id, initialCustomerId }: { id?: string; initialCustomerId?: string }) {
  const { t } = useLedgerTranslations();
  const dispatch = useDispatch();
  const [initial] = useState(() => {
    try {
      const availableCustomers = listCustomers('', true);
      const activeTickets = ticketRepository.listTickets();
      const activePayments = paymentRepository.listPayments();
      const payment = id ? paymentRepository.getPayment(id) : null;
      if (id && (!payment || payment.voidedAt)) {
        return {
          customers: availableCustomers,
          tickets: activeTickets,
          payments: activePayments,
          payment: null,
          error: t.loadFailed,
          initialTicketId: '',
        };
      }
      const customerId = payment?.customerId ?? initialCustomerId ?? availableCustomers[0]?.id ?? '';
      const candidates = getCustomerPaymentCandidates(customerId, activeTickets, activePayments);
      return {
        customers: availableCustomers,
        tickets: activeTickets,
        payments: activePayments,
        payment,
        error: '',
        initialTicketId: payment?.ticketId ?? candidates[0]?.id ?? '',
      };
    } catch (cause) {
      return {
        customers: [] as Customer[],
        tickets: [] as Ticket[],
        payments: [] as Payment[],
        payment: null,
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        initialTicketId: '',
      };
    }
  });
  const [customers] = useState<Customer[]>(initial.customers);
  const [tickets] = useState<Ticket[]>(initial.tickets);
  const [payments] = useState<Payment[]>(initial.payments);
  const [customerId, setCustomerId] = useState(initial.payment?.customerId ?? customers[0]?.id ?? '');
  const [ticketId, setTicketId] = useState(initial.initialTicketId ?? '');
  const [amount, setAmount] = useState(
    initial.payment ? formatMillimeInput(initial.payment.amountMillimes) : '',
  );
  const [error, setError] = useState(initial.error);

  const candidateTickets = useMemo(() => {
    const paymentsWithoutEdited = id ? payments.filter((payment) => payment.id !== id) : payments;
    const candidates = getCustomerPaymentCandidates(customerId, tickets, paymentsWithoutEdited);
    if (id && ticketId && !candidates.some((ticket) => ticket.id === ticketId)) {
      const selected = tickets.find((ticket) => ticket.id === ticketId);
      if (selected) return [selected, ...candidates];
    }
    return candidates;
  }, [customerId, id, payments, ticketId, tickets]);

  function changeCustomer(nextId: string): void {
    setCustomerId(nextId);
    const paymentsWithoutEdited = id ? payments.filter((payment) => payment.id !== id) : payments;
    const candidates = getCustomerPaymentCandidates(nextId, tickets, paymentsWithoutEdited);
    setTicketId(candidates[0]?.id ?? '');
  }

  function save(): void {
    try {
      if (!ticketId) throw new Error(t.selectTicket);
      const input = { ticketId, customerId, amountMillimes: parseMillimes(amount) };
      if (id) paymentRepository.updatePayment(id, input);
      else paymentRepository.createPayment(input);
      dispatch(refreshCompleted());
      router.replace('/ledger/payments');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  return (
    <LedgerPage title={id ? t.editPayment : t.createPayment}>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      <PaymentForm
        customers={customers}
        tickets={candidateTickets}
        selectedCustomerId={customerId}
        selectedTicketId={ticketId}
        amount={amount}
        customerLabel={t.chooseCustomer}
        ticketLabel={t.selectTicket}
        amountLabel={t.paymentAmount}
        cancelLabel={t.cancel}
        openTicketSelectionInitially={!id && Boolean(initialCustomerId)}
        onCustomerChange={changeCustomer}
        onTicketChange={setTicketId}
        onAmountChange={setAmount}
      />
      <Pressable accessibilityRole="button" className="ledger-primary-button" onPress={save}>
        <Text className="font-semibold text-white">{t.save}</Text>
      </Pressable>
    </LedgerPage>
  );
}
