import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_BASE_URL } from '../config/api';

export interface AdminCategory {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  is_active: boolean;
  products_count: number;
}

export interface AdminZone {
  id: number;
  name: string;
  fee: number;
  is_active: boolean;
}

export interface CategoryPayload {
  name: string;
  description: string | null;
  is_active: boolean;
}

export interface ZonePayload {
  name: string;
  fee: number;
  is_active: boolean;
}

@Injectable({ providedIn: 'root' })
export class AdminShopService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${API_BASE_URL}/admin`;

  getCategories(): Observable<AdminCategory[]> {
    return this.http.get<AdminCategory[]>(`${this.apiUrl}/categories`);
  }

  createCategory(payload: CategoryPayload): Observable<AdminCategory> {
    return this.http.post<AdminCategory>(`${this.apiUrl}/categories`, payload);
  }

  updateCategory(id: number, payload: CategoryPayload): Observable<AdminCategory> {
    return this.http.put<AdminCategory>(`${this.apiUrl}/categories/${id}`, payload);
  }

  getZones(): Observable<AdminZone[]> {
    return this.http.get<AdminZone[]>(`${this.apiUrl}/delivery-zones`);
  }

  createZone(payload: ZonePayload): Observable<AdminZone> {
    return this.http.post<AdminZone>(`${this.apiUrl}/delivery-zones`, payload);
  }

  updateZone(id: number, payload: ZonePayload): Observable<AdminZone> {
    return this.http.put<AdminZone>(`${this.apiUrl}/delivery-zones/${id}`, payload);
  }
}