import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, catchError, map, throwError } from 'rxjs';
import { API_BASE_URL } from '../config/api';

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
  product_specs_snapshot?: Record<string, string> | null;
  warranty_conditions_snapshot?: string | null;
}

export interface Order {
  id: number;
  number: string;
  invoice_number: string;
  status: string;
  email_status?: 'pending' | 'sending' | 'sent' | 'failed' | null;
  email_sent_at?: string | null;
  email_attempts?: number;
  email_error?: string | null;
  whatsapp_consent?: boolean;
  whatsapp_status?: 'not_configured' | 'skipped' | 'pending' | 'sending' | 'sent' | 'failed' | null;
  whatsapp_sent_at?: string | null;
  whatsapp_attempts?: number;
  whatsapp_error?: string | null;
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
  whatsapp_consent: boolean;
  customer_address: string;
  notes?: string | null;
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

export function emailStatusLabel(status?: string | null): string {
  if (status === 'sent') {
    return 'E-mail envoyé';
  }
  if (status === 'sending') {
    return 'Envoi de l’e-mail...';
  }
  if (status === 'failed') {
    return 'E-mail non envoyé';
  }
  if (status === 'pending') {
    return 'E-mail en attente';
  }
  return 'E-mail non traité';
}

export function emailStatusClass(status?: string | null): string {
  if (status === 'sent') {
    return 'bg-mint/15 text-mint';
  }
  if (status === 'failed') {
    return 'bg-red-50 text-red-600';
  }
  if (status === 'pending' || status === 'sending') {
    return 'bg-amber-50 text-amber-700';
  }
  return 'bg-slate-100 text-slate-600';
}

export function whatsappStatusLabel(status?: string | null): string {
  if (status === 'sent') {
    return 'WhatsApp envoyé';
  }
  if (status === 'sending') {
    return 'Envoi WhatsApp...';
  }
  if (status === 'failed') {
    return 'WhatsApp non envoyé';
  }
  if (status === 'pending') {
    return 'WhatsApp en attente';
  }
  if (status === 'skipped') {
    return 'WhatsApp non souhaité par le client';
  }
  return 'WhatsApp désactivé';
}

export function whatsappStatusClass(status?: string | null): string {
  if (status === 'sent') {
    return 'bg-mint/15 text-mint';
  }
  if (status === 'failed') {
    return 'bg-red-50 text-red-600';
  }
  if (status === 'pending' || status === 'sending') {
    return 'bg-amber-50 text-amber-700';
  }
  return 'bg-slate-100 text-slate-600';
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
  private readonly apiUrl = API_BASE_URL;

  getZones(): Observable<DeliveryZone[]> {
    return this.http.get<DeliveryZone[]>(`${this.apiUrl}/delivery-zones`);
  }

  createOrder(payload: OrderPayload): Observable<Order> {
    return this.http.post<Order>(`${this.apiUrl}/orders`, payload);
  }

  getInvoicePdf(id: number): Observable<Blob> {
    return this.http.get(`${this.apiUrl}/orders/${id}/invoice`, { responseType: 'blob' });
  }

  downloadInvoice(id: number, invoiceNumber: string): Observable<void> {
    return this.getInvoicePdf(id).pipe(
      map((blob) => {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${invoiceNumber.toLowerCase()}.pdf`;
        link.click();
        URL.revokeObjectURL(url);
      }),
    );
  }

  openInvoice(id: number, invoiceNumber: string): Observable<void> {
    const invoiceWindow = window.open('', '_blank');
    if (!invoiceWindow) {
      return throwError(() => new Error('Autorisez les fenêtres contextuelles pour consulter la facture.'));
    }

    return this.getInvoicePdf(id).pipe(
      map((blob) => {
        const url = URL.createObjectURL(blob);
        invoiceWindow.document.title = `Facture ${invoiceNumber}`;
        invoiceWindow.location.href = url;
        window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
      }),
      catchError((error: unknown) => {
        invoiceWindow.close();
        return throwError(() => error);
      }),
    );
  }

  getOrders(filters: { status?: string; sort?: string } = {}): Observable<Order[]> {
    let params = new HttpParams();
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== '' && value !== null) {
        params = params.set(key, String(value));
      }
    }
    return this.http.get<Order[]>(`${this.apiUrl}/orders`, { params });
  }

  getOrder(id: number): Observable<Order> {
    return this.http.get<Order>(`${this.apiUrl}/orders/${id}`);
  }
}