export interface Product {
  id: string;
  barcode: string | null;
  name: string;
  sellingPriceMillimes: number;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface ProductInput {
  id?: string;
  barcode?: string | null;
  name: string;
  sellingPriceMillimes: number;
  isActive?: boolean;
}
