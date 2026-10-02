import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Paginated } from '../models/catalogue';
import { Order, statusLabel } from './order.service';

export interface AdminStats {
  orders_total: number;
  orders_pending: number;
  revenue: number;
  low_stock: number;
}

export interface AdminOrder extends Order {
  user?: { id: number; name: string; email: string; phone: string | null };
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
}