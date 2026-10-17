import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_BASE_URL } from '../config/api';
import { Paginated, Product } from '../models/catalogue';
import { Order, statusLabel } from './order.service';

export interface AdminStats {
  sales_by_day: { date: string; total: number; count: number }[];
  top_products: { name: string; sold: number }[];
  repairs_new: number;
  recent_orders: { id: number; number: string; customer_name: string; total: number; status: string; created_at: string }[];
  low_stock_products: { id: number; name: string; stock: number; stock_alert_threshold: number }[];
  orders_total: number;
  orders_pending: number;
  revenue: number;
  low_stock: number;
}

export interface AdminOrder extends Order {
  user?: { id: number; name: string; email: string; phone: string | null };
}

export interface AdminCustomer {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at: string;
  orders_count: number;
  spent: number | string | null;
}

export interface ProductPayload {
  category_id: number;
  name: string;
  reference: string;
  brand: string | null;
  description: string | null;
  price: number;
  stock: number;
  stock_alert_threshold: number;
  processor: string | null;
  ram: string | null;
  storage: string | null;
  graphics_card: string | null;
  screen: string | null;
  warranty_conditions: string | null;
  state: string;
  is_active: boolean;
}

export interface InventoryMovement {
  id: number;
  product_id: number | null;
  order_id: number | null;
  user_id: number | null;
  product_name: string;
  product_reference: string;
  purchase_receipt_id: number | null;
  type: 'opening_balance' | 'manual_adjustment' | 'order_confirmation' | 'order_cancellation' | 'purchase_receipt';
  quantity_change: number;
  stock_before: number;
  stock_after: number;
  note: string | null;
  created_at: string;
  user?: { id: number; name: string } | null;
  order?: { id: number; number: string } | null;
  purchase_receipt?: { id: number; number: string } | null;
}

export interface Supplier {
  id: number;
  name: string;
  contact_name: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  notes: string | null;
  is_active: boolean;
  purchase_receipts_count?: number;
}

export interface PurchaseReceiptLine {
  id: number;
  product_id: number | null;
  product_name: string;
  product_reference: string;
  quantity: number;
  unit_cost: number;
  total_cost: number;
}

export interface PurchaseReceipt {
  id: number;
  number: string;
  supplier_reference: string | null;
  received_at: string;
  total_cost: number;
  notes: string | null;
  supplier: { id: number; name: string };
  items: PurchaseReceiptLine[];
  user: { id: number; name: string } | null;
}

export const STATUS_OPTIONS = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'].map(
  (value) => ({ value, label: statusLabel(value) }),
);

export const STATUS_TRANSITIONS: Record<string, string[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['preparing', 'cancelled'],
  preparing: ['out_for_delivery', 'cancelled'],
  out_for_delivery: ['delivered', 'cancelled'],
  delivered: [],
  cancelled: [],
};

@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${API_BASE_URL}/admin`;

  getStats(): Observable<AdminStats> {
    return this.http.get<AdminStats>(`${this.apiUrl}/stats`);
  }

  getOrders(filters: { status?: string; search?: string; page?: number }): Observable<Paginated<Order>> {
    let params = new HttpParams();
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== '' && value !== null) {
        params = params.set(key, String(value));
      }
    }
    return this.http.get<Paginated<Order>>(`${this.apiUrl}/orders`, { params });
  }

  getOrder(id: number): Observable<AdminOrder> {
    return this.http.get<AdminOrder>(`${this.apiUrl}/orders/${id}`);
  }

  getCustomers(filters: { search?: string; page?: number }): Observable<Paginated<AdminCustomer>> {
    let params = new HttpParams();
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== '' && value !== null) {
        params = params.set(key, String(value));
      }
    }
    return this.http.get<Paginated<AdminCustomer>>(`${this.apiUrl}/customers`, { params });
  }

  updateStatus(id: number, status: string): Observable<AdminOrder> {
    return this.http.patch<AdminOrder>(`${this.apiUrl}/orders/${id}/status`, { status });
  }

  retryNotification(id: number, channel: 'email' | 'whatsapp'): Observable<AdminOrder> {
    return this.http.post<AdminOrder>(`${this.apiUrl}/orders/${id}/notifications/retry`, { channel });
  }

  getProducts(filters: { search?: string; low_stock?: boolean; page?: number }): Observable<Paginated<Product>> {
    let params = new HttpParams();
    if (filters.search) {
      params = params.set('search', filters.search);
    }
    if (filters.low_stock) {
      params = params.set('low_stock', '1');
    }
    if (filters.page) {
      params = params.set('page', String(filters.page));
    }
    return this.http.get<Paginated<Product>>(`${this.apiUrl}/products`, { params });
  }

  getProduct(id: number): Observable<Product> {
    return this.http.get<Product>(`${this.apiUrl}/products/${id}`);
  }

  getStockMovements(id: number): Observable<Paginated<InventoryMovement>> {
    return this.http.get<Paginated<InventoryMovement>>(`${this.apiUrl}/products/${id}/stock-movements`);
  }

  createProduct(payload: ProductPayload): Observable<Product> {
    return this.http.post<Product>(`${this.apiUrl}/products`, payload);
  }

  updateProduct(id: number, payload: ProductPayload): Observable<Product> {
    return this.http.put<Product>(`${this.apiUrl}/products/${id}`, payload);
  }

  uploadImage(id: number, file: File): Observable<Product> {
    const body = new FormData();
    body.append('image', file);
    return this.http.post<Product>(`${this.apiUrl}/products/${id}/image`, body);
  }

  deleteImage(id: number): Observable<Product> {
    return this.http.delete<Product>(`${this.apiUrl}/products/${id}/image`);
  }

  deleteProduct(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/products/${id}`);
  }

  patchProduct(id: number, changes: { stock?: number; is_active?: boolean }): Observable<Product> {
    return this.http.patch<Product>(`${this.apiUrl}/products/${id}`, changes);
  }

  getSuppliers(filters: { search?: string; active_only?: boolean } = {}): Observable<Paginated<Supplier>> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.active_only) params = params.set('active_only', '1');
    return this.http.get<Paginated<Supplier>>(`${this.apiUrl}/suppliers`, { params });
  }

  createSupplier(payload: Omit<Supplier, 'id' | 'purchase_receipts_count'>): Observable<Supplier> {
    return this.http.post<Supplier>(`${this.apiUrl}/suppliers`, payload);
  }

  updateSupplier(id: number, payload: Omit<Supplier, 'id' | 'purchase_receipts_count'>): Observable<Supplier> {
    return this.http.put<Supplier>(`${this.apiUrl}/suppliers/${id}`, payload);
  }

  getPurchaseReceipts(page = 1): Observable<Paginated<PurchaseReceipt>> {
    const params = new HttpParams().set('page', String(page));
    return this.http.get<Paginated<PurchaseReceipt>>(`${this.apiUrl}/purchase-receipts`, { params });
  }

  createPurchaseReceipt(payload: {
    idempotency_key: string;
    supplier_id: number;
    supplier_reference: string;
    received_at: string;
    notes: string;
    items: { product_id: number; quantity: number; unit_cost: number }[];
  }): Observable<PurchaseReceipt> {
    return this.http.post<PurchaseReceipt>(`${this.apiUrl}/purchase-receipts`, payload);
  }
}