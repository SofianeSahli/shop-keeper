import { useEffect, useMemo, useState } from 'react';
import { Pressable, Text } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { formatMillimeAmount, formatMillimeInput } from '@/lib/money';
import { InfoBanner, ScreenScroll } from '@/components/ui';
import { useTopBarOptions } from '@/navigation/TopBarContext';
import { clearFormDirty, refreshCompleted, setFormDirty } from '../store/ledgerSlice';
import { PaymentForm } from '../components/payment/PaymentForm';
import { PaymentTicketSheet, PayableTicket } from '../components/payment/PaymentTicketSheet';
import { PaymentCustomerSheet } from '../components/payment/PaymentCustomerSheet';
import { ConfirmAction } from '../components/feedback/ConfirmAction';
import { parseMillimes } from '../components/forms/MillimeInput';
import { getCustomerPaymentCandidates, summariseDebtors, sumActivePaymentsByTicket } from '../services/customerBalanceService';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';
import { isWalkInCustomer } from '../constants';
import { useDirtyFormGuard } from '../hooks/useDirtyFormGuard';
import { Customer } from '../types/customer';
import { Payment } from '../types/payment';
import { Ticket } from '../types/ticket';

export function PaymentFormScreen() {
  const { id, customerId } = useLocalSearchParams<{ id?: string; customerId?: string }>();
  return <PaymentFormPage key={id ?? customerId ?? 'new'} id={id} initialCustomerId={customerId} />;
}

function parseAmountOrNull(value: string): number | null {
  try {
    return parseMillimes(value);
  } catch {
    return null;
  }
}

function loadInitial(id: string | undefined) {
  const customers = listCustomers('', true);
  const tickets = ticketRepository.listTickets();
  const payments = paymentRepository.listPayments();
  const payment = id ? paymentRepository.getPayment(id) : null;
  return { customers, tickets, payments, payment };
}

function PaymentFormPage({ id, initialCustomerId }: { id?: string; initialCustomerId?: string }) {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  useDirtyFormGuard();
  useTopBarOptions({ title: id ? t.editPayment : t.collect });

  const [initial] = useState(() => {
    try {
      const data = loadInitial(id);
      const missing = Boolean(id) && (!data.payment || data.payment.voidedAt);
      return { ...data, error: missing ? t.loadFailed : '' };
    } catch (cause) {
      return {
        customers: [] as Customer[],
        tickets: [] as Ticket[],
        payments: [] as Payment[],
        payment: null as Payment | null,
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
      };
    }
  });
  const editedPayment = initial.payment && !initial.payment.voidedAt ? initial.payment : null;
  const lockedWalkInPayment = Boolean(editedPayment && editedPayment.paymentKind === 'AT_SALE' && isWalkInCustomer(editedPayment.customerId));

  // Balances ignore the payment being edited, so its own amount is available again.
  const otherPayments = useMemo(
    () => (editedPayment ? initial.payments.filter((payment) => payment.id !== editedPayment.id) : initial.payments),
    [editedPayment, initial.payments],
  );
  const debtors = useMemo(
    () => summariseDebtors(initial.customers, initial.tickets, otherPayments).filter((debtor) => debtor.balanceMillimes > 0),
    [initial.customers, initial.tickets, otherPayments],
  );
  const paidByTicket = useMemo(() => sumActivePaymentsByTicket(otherPayments), [otherPayments]);

  const payableFor = (customerId: string): PayableTicket[] => {
    const candidates = getCustomerPaymentCandidates(customerId, initial.tickets, otherPayments);
    const edited = editedPayment ? initial.tickets.find((ticket) => ticket.id === editedPayment.ticketId) : undefined;
    const list = edited && !candidates.some((ticket) => ticket.id === edited.id) ? [edited, ...candidates] : candidates;
    return list.map((ticket) => ({ ticket, remainingMillimes: ticket.totalMillimes - (paidByTicket.get(ticket.id) ?? 0) }));
  };

  const startCustomer = editedPayment?.customerId ?? initialCustomerId ?? '';
  const startTicket = editedPayment?.ticketId ?? payableFor(startCustomer)[0]?.ticket.id ?? '';
  const startAmount = editedPayment
    ? formatMillimeInput(editedPayment.amountMillimes)
    : (() => {
        const first = payableFor(startCustomer)[0];
        return first ? formatMillimeInput(first.remainingMillimes) : '';
      })();

  const [customerId, setCustomerId] = useState(startCustomer);
  const [ticketId, setTicketId] = useState(startTicket);
  const [amountText, setAmountText] = useState(startAmount);
  const [customerSheet, setCustomerSheet] = useState(!editedPayment && !startCustomer && !initial.error);
  const [ticketSheet, setTicketSheet] = useState(false);
  const [error, setError] = useState(initial.error);

  const payable = payableFor(customerId);
  const selected = payable.find((item) => item.ticket.id === ticketId) ?? null;
  const customer = debtors.find((debtor) => debtor.customerId === customerId) ?? null;
  const amountMillimes = parseAmountOrNull(amountText);
  const amountError =
    amountText !== '' && amountMillimes === null
      ? t.invalidAmount
      : selected && amountMillimes !== null && amountMillimes > selected.remainingMillimes
        ? t.paymentExceedsBalance
        : undefined;

  useEffect(() => {
    dispatch(setFormDirty({ section: 'payments', isDirty: amountText !== startAmount || customerId !== startCustomer }));
  }, [amountText, customerId, dispatch, startAmount, startCustomer]);

  useEffect(() => {
    return () => {
      dispatch(clearFormDirty());
    };
  }, [dispatch]);

  function chooseCustomer(nextId: string): void {
    setCustomerId(nextId);
    setCustomerSheet(false);
    const first = payableFor(nextId)[0];
    setTicketId(first?.ticket.id ?? '');
    setAmountText(first ? formatMillimeInput(first.remainingMillimes) : '');
  }

  function chooseTicket(nextId: string): void {
    setTicketSheet(false);
    setTicketId(nextId);
    const next = payable.find((item) => item.ticket.id === nextId);
    if (next) setAmountText(formatMillimeInput(next.remainingMillimes));
  }

  function finish(): void {
    dispatch(clearFormDirty());
    dispatch(refreshCompleted());
    router.replace('/ledger/payments');
  }

  function save(): void {
    try {
      if (!ticketId) throw new Error(t.selectTicket);
      const input = { ticketId, customerId, amountMillimes: parseMillimes(amountText) };
      if (editedPayment) paymentRepository.updatePayment(editedPayment.id, input);
      else paymentRepository.createPayment(input);
      finish();
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  function voidPayment(): void {
    if (!editedPayment) return;
    try {
      paymentRepository.voidPayment(editedPayment.id);
      finish();
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  const canSave = Boolean(selected) && amountMillimes !== null && !amountError && !lockedWalkInPayment;
  const footer = (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: !canSave }}
      disabled={!canSave}
      className={`ui-button-paid min-h-button-lg ${canSave ? '' : 'ui-button-disabled'}`}
      onPress={save}
    >
      <Text className="type-button text-surface">
        {amountMillimes !== null ? formatMessage(t.saveAmount, { amount: formatMillimeAmount(amountMillimes, language) }) : t.save}
      </Text>
    </Pressable>
  );

  return (
    <ScreenScroll footer={footer} footerWithSafeArea>
      {error ? <InfoBanner tone="error" title={error} /> : null}
      {lockedWalkInPayment ? <InfoBanner tone="warning" title={t.walkInLocked} /> : null}
      <PaymentForm
        customer={customer}
        selected={selected}
        hasPayableTickets={payable.length > 0}
        amountText={amountText}
        amountMillimes={amountMillimes}
        amountError={amountError}
        canChangeCustomer={!editedPayment}
        onChangeCustomer={() => setCustomerSheet(true)}
        onChangeTicket={() => setTicketSheet(true)}
        onAmountTextChange={setAmountText}
        onQuickAmount={(value) => setAmountText(formatMillimeInput(value))}
      />
      {editedPayment && !lockedWalkInPayment ? (
        <ConfirmAction
          buttonLabel={t.voidPayment}
          title={t.voidPayment}
          message={t.confirmAction}
          confirmLabel={t.confirm}
          cancelLabel={t.cancel}
          onConfirm={voidPayment}
        />
      ) : null}

      <PaymentCustomerSheet
        visible={customerSheet}
        debtors={debtors}
        selectedCustomerId={customerId}
        onSelect={chooseCustomer}
        onClose={() => setCustomerSheet(false)}
      />
      <PaymentTicketSheet
        visible={ticketSheet}
        tickets={payable}
        selectedTicketId={ticketId}
        onConfirm={chooseTicket}
        onClose={() => setTicketSheet(false)}
      />
    </ScreenScroll>
  );
}
