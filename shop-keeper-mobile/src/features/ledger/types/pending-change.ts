export type PendingChangeOperation = 'create' | 'update' | 'delete' | 'soft_delete';
export type PendingChangeStatus = 'pending' | 'applied' | 'rejected';

export interface PendingChange {
  id: string;
  entityType: 'product' | 'customer' | 'ticket' | 'payment' | 'product_removal';
  entityId: string;
  operation: PendingChangeOperation;
  payloadJson: Record<string, unknown>;
  status: PendingChangeStatus;
  createdAt: string;
  updatedAt: string;
}
