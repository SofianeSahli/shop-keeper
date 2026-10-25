/** `AT_SALE`: received when the ticket was recorded (spec 003, US8). */
export type PaymentKind = 'TICKET' | 'CUSTOMER_BALANCE' | 'AT_SALE';

export interface Payment {
  id: string;
  ticketId: string;
  customerId: string;
  amountMillimes: number;
  paymentKind: PaymentKind;
  appliedAt: string;
  updatedAt: string;
  voidedAt: string | null;
}

export interface PaymentInput {
  id?: string;
  ticketId: string;
  customerId: string;
  amountMillimes: number;
  paymentKind?: PaymentKind;
  appliedAt?: string;
}
