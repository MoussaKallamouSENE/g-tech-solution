import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
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
  state: string;
  is_active: boolean;
}

export const STATUS_OPTIONS = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'].map(
  (value) => ({ value, label: statusLabel(value) }),
);

@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost/api/admin';

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

  updateStatus(id: number, status: string): Observable<AdminOrder> {
    return this.http.patch<AdminOrder>(`${this.apiUrl}/orders/${id}/status`, { status });
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

  patchProduct(id: number, changes: { stock?: number; is_active?: boolean }): Observable<Product> {
    return this.http.patch<Product>(`${this.apiUrl}/products/${id}`, changes);
  }
}