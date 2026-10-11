export type PaymentKind = 'TICKET' | 'CUSTOMER_BALANCE';

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
