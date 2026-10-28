import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ScrollViewInstance } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useAsyncAction } from '@/features/feedback/useAsyncAction';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import { formatMessage } from '@/i18n/format';
import { formatMillimeAmount, formatMillimeInput } from '@/lib/money';
import { BusyButton, InfoBanner, ScreenScroll, SkeletonRows, useFieldErrors } from '@/components/ui';
import { useTopBarOptions } from '@/navigation/TopBarContext';
import { clearFormDirty, refreshCompleted, setFormDirty } from '../store/ledgerSlice';
import { PaymentForm } from '../components/payment/PaymentForm';
import { PaymentTicketSheet, PayableTicket } from '../components/payment/PaymentTicketSheet';
import { PaymentCustomerSheet } from '../components/payment/PaymentCustomerSheet';
import { ConfirmAction } from '../components/feedback/ConfirmAction';
import { parseMillimes } from '../components/forms/MillimeInput';
import { ledgerFieldErrorFor } from '../components/forms/ledgerFieldError';
import { getCustomerPaymentCandidates, summariseDebtors, sumActivePaymentsByTicket } from '../services/customerBalanceService';
import { listCustomers } from '../services/customerService';
import { paymentRepository } from '../services/paymentRepository';
import { ticketRepository } from '../services/ticketRepository';
import { isWalkInCustomer } from '../constants';
import { useDirtyFormGuard } from '../hooks/useDirtyFormGuard';
import { Customer } from '../types/customer';
import { Payment } from '../types/payment';
import { Ticket } from '../types/ticket';

interface PaymentFormData {
  customers: Customer[];
  tickets: Ticket[];
  payments: Payment[];
  payment: Payment | null;
}

async function loadPaymentForm(id: string | undefined): Promise<PaymentFormData> {
  const customers = await listCustomers('', true);
  const tickets = await ticketRepository.listTickets();
  const payments = await paymentRepository.listPayments();
  const payment = id ? await paymentRepository.getPayment(id) : null;
  return { customers, tickets, payments, payment };
}

export function PaymentFormScreen() {
  const { id, customerId } = useLocalSearchParams<{ id?: string; customerId?: string }>();
  const { t } = useLedgerTranslations();
  useDirtyFormGuard();
  useTopBarOptions({ title: id ? t.editPayment : t.collect });
  const loadForm = useCallback(() => loadPaymentForm(id), [id]);
  const query = useLedgerQuery(loadForm, [id]);

  if (query.status === 'loading') {
    return (
      <ScreenScroll>
        <SkeletonRows variant="tile" count={3} />
      </ScreenScroll>
    );
  }
  const isMissing = Boolean(id) && (!query.data?.payment || Boolean(query.data.payment.voidedAt));
  if (!query.data || isMissing) {
    return (
      <ScreenScroll>
        <InfoBanner
          tone="error"
          title={query.status === 'error' ? query.error : t.recordUnavailable}
          actionLabel={query.status === 'error' ? t.retry : undefined}
          onAction={query.retry}
        />
      </ScreenScroll>
    );
  }
  return <PaymentFormPage key={id ?? customerId ?? 'new'} initialCustomerId={customerId} initial={query.data} />;
}

const PAYMENT_FIELDS = ['customer', 'amount'] as const;

function parseAmountOrNull(value: string): number | null {
  try {
    return parseMillimes(value);
  } catch {
    return null;
  }
}

function PaymentFormPage({ initialCustomerId, initial }: { initialCustomerId?: string; initial: PaymentFormData }) {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const editedPayment = initial.payment && !initial.payment.voidedAt ? initial.payment : null;
  const lockedWalkInPayment = Boolean(editedPayment && editedPayment.paymentKind === 'AT_SALE' && isWalkInCustomer(editedPayment.customerId));

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
  const [customerSheet, setCustomerSheet] = useState(!editedPayment && !startCustomer);
  const [ticketSheet, setTicketSheet] = useState(false);

  const payable = payableFor(customerId);
  const selected = payable.find((item) => item.ticket.id === ticketId) ?? null;
  const customer = debtors.find((debtor) => debtor.customerId === customerId) ?? null;
  const amountMillimes = parseAmountOrNull(amountText);
  const liveAmountError =
    amountText !== '' && amountMillimes === null
      ? t.invalidAmount
      : selected && amountMillimes !== null && amountMillimes > selected.remainingMillimes
        ? t.paymentExceedsBalance
        : undefined;

  const scrollRef = useRef<ScrollViewInstance>(null);
  const fieldErrors = useFieldErrors({
    fieldOrder: PAYMENT_FIELDS,
    validate: () => ({
      customer: !customerId ? t.customerRequired : selected ? undefined : t.noUnpaidTickets,
      amount: selected ? (amountText.trim() ? liveAmountError : t.fieldRequired) : undefined,
    }),
    fieldErrorFor: ledgerFieldErrorFor(
      { paymentCustomerMismatch: 'customer', paymentExceedsBalance: 'amount', invalidAmount: 'amount' },
      t,
    ),
    scrollRef,
  });
  const amountError = liveAmountError ?? fieldErrors.errorOf('amount');

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
    fieldErrors.fieldChanged('customer');
    fieldErrors.fieldChanged('amount');
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

  const amountLabel = amountMillimes !== null ? formatMillimeAmount(amountMillimes, language) : '';

  const saveAction = useAsyncAction(() => ({
    checkFields: fieldErrors.checkFields,
    showFieldError: fieldErrors.showReportedError,
    work: () => {
      const input = { ticketId, customerId, amountMillimes: parseMillimes(amountText) };
      return editedPayment ? paymentRepository.updatePayment(editedPayment.id, input) : paymentRepository.createPayment(input);
    },
    loaderLabel: t.savingPayment,
    loaderDetail: customer ? `${customer.displayLabel} · ${amountLabel}` : amountLabel,
    successToast: { title: t.paymentSavedToast, detail: formatMessage(t.amountCollected, { amount: amountLabel }) },
    onSuccess: finish,
    failureThing: t.thingPayment,
    keepValuesMessage: t.valuesKept,
  }));

  const voidAction = useAsyncAction(() => ({
    work: () => paymentRepository.voidPayment(editedPayment?.id ?? ''),
    loaderLabel: t.voidingPayment,
    loaderDetail: amountLabel || null,
    successToast: { title: t.paymentVoidedToast },
    onSuccess: finish,
    failureThing: t.thingPayment,
    keepValuesMessage: t.nothingChanged,
  }));

  const footer = (
    <BusyButton
      variant="paid"
      label={amountMillimes !== null ? formatMessage(t.saveAmount, { amount: amountLabel }) : t.save}
      busy={saveAction.busy}
      disabled={lockedWalkInPayment || voidAction.busy}
      className="min-h-button-lg"
      onPress={() => void saveAction.run()}
    />
  );

  return (
    <ScreenScroll footer={footer} footerWithSafeArea scrollRef={scrollRef}>
      {lockedWalkInPayment ? <InfoBanner tone="warning" title={t.walkInLocked} /> : null}
      <PaymentForm
        customer={customer}
        selected={selected}
        hasPayableTickets={payable.length > 0}
        amountText={amountText}
        amountMillimes={amountMillimes}
        amountError={amountError}
        customerError={fieldErrors.errorOf('customer')}
        customerRef={fieldErrors.register('customer')}
        amountRef={fieldErrors.register('amount')}
        canChangeCustomer={!editedPayment}
        onChangeCustomer={() => setCustomerSheet(true)}
        onChangeTicket={() => setTicketSheet(true)}
        onAmountTextChange={(value) => {
          setAmountText(value);
          fieldErrors.fieldChanged('amount');
        }}
        onQuickAmount={(value) => {
          setAmountText(formatMillimeInput(value));
          fieldErrors.fieldChanged('amount');
        }}
      />
      {editedPayment && !lockedWalkInPayment ? (
        <ConfirmAction
          buttonLabel={t.voidPayment}
          title={t.voidPayment}
          message={t.confirmAction}
          confirmLabel={t.confirm}
          cancelLabel={t.cancel}
          busy={voidAction.busy}
          busyLabel={t.voidingPayment}
          onConfirm={() => void voidAction.run()}
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
