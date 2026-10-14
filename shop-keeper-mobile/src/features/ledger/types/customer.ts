export interface Customer {
  id: string;
  internalCode: string;
  displayLabel: string;
  realName?: string | null;
  phoneNumber?: string | null;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface CustomerInput {
  id?: string;
  displayLabel: string;
  realName?: string | null;
  phoneNumber?: string | null;
}
