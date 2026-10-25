export type TicketStatus = 'UNPAID' | 'PARTIALLY_PAID' | 'PAID';

export interface TicketLine {
  id: string;
  ticketId: string;
  productId: string | null;
  productNameSnapshot: string;
  barcodeSnapshot: string | null;
  unitPriceMillimes: number;
  quantity: number;
  lineTotalMillimes: number;
}

export interface Ticket {
  id: string;
  customerId: string;
  createdAt: string;
  updatedAt: string;
  totalMillimes: number;
  status: TicketStatus;
  voidedAt: string | null;
  lines: TicketLine[];
  
}

export interface TicketDraftItem {
  id: string;
  productId: string | null;
  productName: string;
  barcode: string | null;
  unitPriceMillimes: number;
  quantity: number;
}
