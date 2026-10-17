import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_BASE_URL } from '../config/api';
import { Paginated } from '../models/catalogue';

export type DeliveryStatus = 'to_prepare' | 'ready_for_delivery' | 'in_delivery' | 'delivered' | 'failed' | 'cancelled';

export interface DeliveryStatusHistory {
  id: number;
  status: DeliveryStatus;
  note: string | null;
  created_at: string;
  changed_by: string | null;
}

export interface Delivery {
  id: number;
  status: DeliveryStatus;
  assigned_at: string;
  picked_up_at: string | null;
  delivered_at: string | null;
  failure_reason: string | null;
  driver: { id: number; name: string };
  order: {
    id: number;
    number: string;
    status: string;
    customer_name: string;
    customer_phone: string;
    customer_address: string;
  };
  location_sharing_active: boolean;
  location_available: boolean;
  location_is_stale: boolean;
  latitude: number | null;
  longitude: number | null;
  location_accuracy: number | null;
  location_updated_at: string | null;
  status_history: DeliveryStatusHistory[];
}

export interface DeliveryDriver {
  id: number;
  name: string;
}

export interface CustomerDeliveryTracking {
  order: {
    id: number;
    number: string;
    status: string;
  };
  delivery: {
    id: number;
    status: DeliveryStatus;
    assigned_at: string;
    picked_up_at: string | null;
    driver: { name: string };
    location_sharing_active: boolean;
    location_available: boolean;
    location_is_stale: boolean;
    location_stale_after_seconds: number;
    latitude: number | null;
    longitude: number | null;
    location_accuracy: number | null;
    location_updated_at: string | null;
  } | null;
}

export interface PendingDeliveryOrder {
  id: number;
  number: string;
  status: string;
  customer_name: string;
  customer_phone: string;
  customer_address: string;
}

@Injectable({ providedIn: 'root' })
export class DeliveryService {
  private readonly http = inject(HttpClient);
  private readonly adminUrl = `${API_BASE_URL}/admin`;
  private readonly driverUrl = `${API_BASE_URL}/driver`;
  private readonly apiUrl = API_BASE_URL;

  getCustomerDeliveryTracking(orderId: number): Observable<CustomerDeliveryTracking> {
    return this.http.get<CustomerDeliveryTracking>(`${this.apiUrl}/orders/${orderId}/delivery`);
  }

  getAdminDeliveries(): Observable<Paginated<Delivery>> {
    return this.http.get<Paginated<Delivery>>(`${this.adminUrl}/deliveries`);
  }

  getPendingOrders(): Observable<PendingDeliveryOrder[]> {
    return this.http.get<PendingDeliveryOrder[]>(`${this.adminUrl}/delivery-orders/pending`);
  }

  getDrivers(): Observable<DeliveryDriver[]> {
    return this.http.get<DeliveryDriver[]>(`${this.adminUrl}/drivers`);
  }

  createDriver(payload: { name: string; email: string; phone: string; password: string }): Observable<DeliveryDriver> {
    return this.http.post<DeliveryDriver>(`${this.adminUrl}/drivers`, payload);
  }

  assignDriver(orderId: number, driverId: number): Observable<Delivery> {
    return this.http.post<Delivery>(`${this.adminUrl}/orders/${orderId}/delivery`, { driver_id: driverId });
  }

  markReady(deliveryId: number): Observable<Delivery> {
    return this.http.patch<Delivery>(`${this.adminUrl}/deliveries/${deliveryId}/status`, {
      status: 'ready_for_delivery',
    });
  }

  getDriverDeliveries(): Observable<Paginated<Delivery>> {
    return this.http.get<Paginated<Delivery>>(`${this.driverUrl}/deliveries`);
  }

  updateDriverStatus(
    deliveryId: number,
    status: 'in_delivery' | 'delivered' | 'failed',
    extra: { reason?: string; delivery_confirmed?: boolean } = {},
  ): Observable<Delivery> {
    return this.http.patch<Delivery>(`${this.driverUrl}/deliveries/${deliveryId}/status`, { status, ...extra });
  }

  startLocationSharing(deliveryId: number): Observable<Delivery> {
    return this.http.post<Delivery>(`${this.driverUrl}/deliveries/${deliveryId}/location/start`, {});
  }

  stopLocationSharing(deliveryId: number): Observable<Delivery> {
    return this.http.post<Delivery>(`${this.driverUrl}/deliveries/${deliveryId}/location/stop`, {});
  }

  updateLocation(deliveryId: number, location: { latitude: number; longitude: number; accuracy: number }): Observable<Delivery> {
    return this.http.put<Delivery>(`${this.driverUrl}/deliveries/${deliveryId}/location`, location);
  }
}
