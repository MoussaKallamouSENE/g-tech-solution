import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface DeliveryZone {
  id: number;
  name: string;
  fee: number;
}

export interface OrderItem {
  id: number;
  product_id: number | null;
  product_name: string;
  unit_price: number;
  quantity: number;
  total: number;
}

export interface Order {
  id: number;
  number: string;
  status: string;
  payment_method: string;
  customer_name: string;
  customer_phone: string;
  customer_address: string;
  notes: string | null;
  subtotal: number;
  delivery_fee: number;
  total: number;
  created_at: string;
  items_count?: number;
  items?: OrderItem[];
  zone?: DeliveryZone | null;
}

export interface OrderPayload {
  items: { product_id: number; quantity: number }[];
  delivery_zone_id: number;
  customer_name: string;
  customer_phone: string;
  customer_address: string;
  notes: string;
}

const STATUS_LABELS: Record<string, string> = {
  pending: 'En attente de confirmation',
  confirmed: 'Confirmée',
  preparing: 'En préparation',
  out_for_delivery: 'En cours de livraison',
  delivered: 'Livrée',
  cancelled: 'Annulée',
};

export function statusLabel(status: string): string {
  return STATUS_LABELS[status] ?? status;
}

export function statusClass(status: string): string {
  if (status === 'delivered') {
    return 'bg-mint/15 text-mint';
  }
  if (status === 'cancelled') {
    return 'bg-red-50 text-red-600';
  }
  if (status === 'pending') {
    return 'bg-amber-50 text-amber-700';
  }
  return 'bg-cobalt/10 text-cobalt';
}

export function formatPrice(price: number): string {
  return price.toLocaleString('fr-FR') + ' FCFA';
}

export function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

@Injectable({ providedIn: 'root' })
export class OrderService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost/api';

  getZones(): Observable<DeliveryZone[]> {
    return this.http.get<DeliveryZone[]>(`${this.apiUrl}/delivery-zones`);
  }

  createOrder(payload: OrderPayload): Observable<Order> {
    return this.http.post<Order>(`${this.apiUrl}/orders`, payload);
  }

  getOrders(): Observable<Order[]> {
    return this.http.get<Order[]>(`${this.apiUrl}/orders`);
  }

  getOrder(id: number): Observable<Order> {
    return this.http.get<Order>(`${this.apiUrl}/orders/${id}`);
  }
}