import { isWalkInCustomer } from '../constants';
import { Payment } from '../types/payment';
import { SalePaymentChoice } from '../types/sale';
import { Ticket } from '../types/ticket';
import { createPaymentRecord } from './paymentService';
import { insertPaymentRecords } from './paymentRecords';
import { TicketRepository, ticketRepository } from './ticketRepository';

export type SaleChoiceError = 'kridiNeedsCustomer' | 'partialAmountInvalid' | 'partialEqualsTotal';

/** Developer messages thrown by `recordSale`; `errorMessages.ts` maps them to the translation keys. */
export const SALE_ERROR_MESSAGES: Record<SaleChoiceError, string> = {
  kridiNeedsCustomer: 'Credit needs a customer.',
  partialAmountInvalid: 'Partial amount must be above 0 and below the total.',
  partialEqualsTotal: 'Partial amount equals the total: record the sale as paid.',
};

/**
 * Checks the payment status chosen at sale (spec 003, FR-015 to FR-018). A missing customer and the
 * walk-in customer are the same thing here: neither can carry credit.
 */
export function validateSaleChoice(
  choice: SalePaymentChoice,
  totalMillimes: number,
  customerId: string | null,
): SaleChoiceError | null {
  const hasRealCustomer = Boolean(customerId) && !isWalkInCustomer(customerId);
  if (choice.kind === 'PAID') return null;
  if (choice.kind === 'PARTIAL') {
    const amount = choice.amountMillimes;
    if (amount === null || !Number.isSafeInteger(amount) || amount <= 0 || amount > totalMillimes) {
      return 'partialAmountInvalid';
    }
    if (amount === totalMillimes) return 'partialEqualsTotal';
  }
  return hasRealCustomer ? null : 'kridiNeedsCustomer';
}

/** Amount received at the counter: the total, the partial amount, or nothing for kridi. */
export function getSalePaymentAmount(choice: SalePaymentChoice, totalMillimes: number): number {
  if (choice.kind === 'PAID') return totalMillimes;
  if (choice.kind === 'PARTIAL') return choice.amountMillimes ?? 0;
  return 0;
}

export type SaleSaveLabel =
  | { key: 'collectAmount'; params: { amount: number } }
  | { key: 'collectWithRemainder'; params: { paid: number; rest: number } }
  | { key: 'putOnKridi'; params: { amount: number } };

/** The save button says what will happen (FR-019); amounts are millimes, formatted by the screen. */
export function getSaleSaveLabel(choice: SalePaymentChoice, totalMillimes: number): SaleSaveLabel {
  if (choice.kind === 'PAID') return { key: 'collectAmount', params: { amount: totalMillimes } };
  if (choice.kind === 'UNPAID') return { key: 'putOnKridi', params: { amount: totalMillimes } };
  const paid = Math.min(Math.max(choice.amountMillimes ?? 0, 0), totalMillimes);
  return { key: 'collectWithRemainder', params: { paid, rest: totalMillimes - paid } };
}

/**
 * Records a sale: the ticket and, for Paid or Partial, its `AT_SALE` payment, in one local
 * transaction with their audit events and operation-log entries (FR-016, FR-021). If any write
 * fails, nothing is kept.
 */
export function recordSale(
  ticket: Ticket,
  choice: SalePaymentChoice,
  repository: TicketRepository = ticketRepository,
): { ticket: Ticket; payment: Payment | null } {
  const error = validateSaleChoice(choice, ticket.totalMillimes, ticket.customerId);
  if (error) throw new Error(SALE_ERROR_MESSAGES[error]);

  return repository.transaction((db) => {
    repository.insertTicketRecords(ticket, db);
    const amountMillimes = getSalePaymentAmount(choice, ticket.totalMillimes);
    const payment =
      amountMillimes > 0
        ? insertPaymentRecords(
            createPaymentRecord({
              ticketId: ticket.id,
              customerId: ticket.customerId,
              amountMillimes,
              paymentKind: 'AT_SALE',
              appliedAt: ticket.createdAt,
            }),
            db,
          )
        : null;
    return { ticket: repository.getTicket(ticket.id) ?? ticket, payment };
  });
}
