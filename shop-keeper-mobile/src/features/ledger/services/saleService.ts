import { isWalkInCustomer } from '../constants';
import { Payment } from '../types/payment';
import { SalePaymentChoice } from '../types/sale';
import { Ticket } from '../types/ticket';
import { createPaymentRecord } from './paymentService';
import { insertPaymentRecords } from './paymentRecords';
import { TicketRepository, ticketRepository } from './ticketRepository';

export type SaleChoiceError = 'kridiNeedsCustomer' | 'partialAmountInvalid' | 'partialEqualsTotal';

export const SALE_ERROR_MESSAGES: Record<SaleChoiceError, string> = {
  kridiNeedsCustomer: 'Credit needs a customer.',
  partialAmountInvalid: 'Partial amount must be above 0 and below the total.',
  partialEqualsTotal: 'Partial amount equals the total: record the sale as paid.',
};

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

export function getSalePaymentAmount(choice: SalePaymentChoice, totalMillimes: number): number {
  if (choice.kind === 'PAID') return totalMillimes;
  if (choice.kind === 'PARTIAL') return choice.amountMillimes ?? 0;
  return 0;
}

export type SaleSaveLabel =
  | { key: 'collectAmount'; params: { amount: number } }
  | { key: 'collectWithRemainder'; params: { paid: number; rest: number } }
  | { key: 'putOnKridi'; params: { amount: number } };

export function getSaleSaveLabel(choice: SalePaymentChoice, totalMillimes: number): SaleSaveLabel {
  if (choice.kind === 'PAID') return { key: 'collectAmount', params: { amount: totalMillimes } };
  if (choice.kind === 'UNPAID') return { key: 'putOnKridi', params: { amount: totalMillimes } };
  const paid = Math.min(Math.max(choice.amountMillimes ?? 0, 0), totalMillimes);
  return { key: 'collectWithRemainder', params: { paid, rest: totalMillimes - paid } };
}

export async function recordSale(
  ticket: Ticket,
  choice: SalePaymentChoice,
  repository: TicketRepository = ticketRepository,
): Promise<{ ticket: Ticket; payment: Payment | null }> {
  const error = validateSaleChoice(choice, ticket.totalMillimes, ticket.customerId);
  if (error) throw new Error(SALE_ERROR_MESSAGES[error]);

  return repository.transaction(async (db, tickets) => {
    await tickets.insertTicketRecords(ticket, db);
    const amountMillimes = getSalePaymentAmount(choice, ticket.totalMillimes);
    const payment =
      amountMillimes > 0
        ? await insertPaymentRecords(
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
    return { ticket: (await tickets.getTicket(ticket.id)) ?? ticket, payment };
  });
}
