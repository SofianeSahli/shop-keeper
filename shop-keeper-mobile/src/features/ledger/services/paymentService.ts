import { Payment, PaymentInput } from '../types/payment';
import { Ticket } from '../types/ticket';
import { getTicketStatus } from './ticketService';
import { createId } from '@/lib/id';
import { validateRequiredText } from '@/utils/validation';

export function validateTicketPayment(
  ticket: Ticket,
  amountMillimes: number,
  totalPaidMillimes = 0,
): string | null {
  if (!Number.isSafeInteger(amountMillimes) || amountMillimes <= 0) {
    return 'Payment amount must be a positive integer in millimes.';
  }

  if (ticket.voidedAt) {
    return 'Payments cannot be added to a voided ticket.';
  }
  if (totalPaidMillimes >= ticket.totalMillimes) {
    return 'This ticket is already fully paid.';
  }
  const remaining = ticket.totalMillimes - totalPaidMillimes;

  if (amountMillimes > remaining) {
    return 'Payment exceeds the outstanding ticket balance.';
  }

  return null;
}

export function createPaymentRecord(input: PaymentInput): Payment {
  if (!Number.isSafeInteger(input.amountMillimes) || input.amountMillimes <= 0) {
    throw new Error('Payment amount must be a positive integer in millimes.');
  }
  const ticketId = validateRequiredText(input.ticketId, 'Ticket');
  const customerId = validateRequiredText(input.customerId, 'Customer');

  return {
    id: input.id ?? createId(),
    ticketId,
    customerId,
    amountMillimes: input.amountMillimes,
    paymentKind: input.paymentKind ?? 'TICKET',
    appliedAt: input.appliedAt ?? new Date().toISOString(),
    updatedAt: new Date().toISOString(),
    voidedAt: null,
  };
}

export function deriveTicketStatus(ticket: Ticket, totalPaidMillimes: number): Ticket['status'] {
  return getTicketStatus(totalPaidMillimes, ticket.totalMillimes);
}
