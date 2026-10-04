import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Paginated } from '../models/catalogue';

export interface MaintenanceRequest {
  id: number;
  number: string;
  name: string;
  phone: string;
  email: string | null;
  device_type: string;
  brand: string | null;
  problem: string;
  status: string;
  admin_notes: string | null;
  created_at: string;
}

export interface MaintenancePayload {
  name: string;
  phone: string;
  email: string | null;
  device_type: string;
  brand: string | null;
  problem: string;
}

export const DEVICE_OPTIONS = [
  { value: 'portable', label: 'Ordinateur portable' },
  { value: 'bureau', label: 'Ordinateur de bureau' },
  { value: 'imprimante', label: 'Imprimante' },
  { value: 'mobile', label: 'Téléphone ou tablette' },
  { value: 'reseau', label: 'Réseau, Wi-Fi' },
  { value: 'autre', label: 'Autre' },
];

export const MAINTENANCE_STATUSES = [
  { value: 'new', label: 'Nouvelle' },
  { value: 'in_progress', label: 'En cours' },
  { value: 'ready', label: 'Prêt à récupérer' },
  { value: 'done', label: 'Terminée' },
  { value: 'cancelled', label: 'Annulée' },
];

export function deviceLabel(value: string): string {
  return DEVICE_OPTIONS.find((o) => o.value === value)?.label ?? value;
}

export function maintenanceStatusLabel(value: string): string {
  return MAINTENANCE_STATUSES.find((o) => o.value === value)?.label ?? value;
}

export function maintenanceStatusClass(value: string): string {
  if (value === 'done') {
    return 'bg-mint/15 text-mint';
  }
  if (value === 'cancelled') {
    return 'bg-red-50 text-red-600';
  }
  if (value === 'new') {
    return 'bg-amber-50 text-amber-700';
  }
  return 'bg-cobalt/10 text-cobalt';
}

@Injectable({ providedIn: 'root' })
export class MaintenanceService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost/api';

  create(payload: MaintenancePayload): Observable<{ number: string }> {
    return this.http.post<{ number: string }>(`${this.apiUrl}/maintenance-requests`, payload);
  }

  list(filters: { status?: string; search?: string; page?: number }): Observable<Paginated<MaintenanceRequest>> {
    let params = new HttpParams();
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== '' && value !== null) {
        params = params.set(key, String(value));
      }
    }
    return this.http.get<Paginated<MaintenanceRequest>>(`${this.apiUrl}/admin/maintenance-requests`, { params });
  }

  update(id: number, status: string, adminNotes: string | null): Observable<MaintenanceRequest> {
    return this.http.patch<MaintenanceRequest>(`${this.apiUrl}/admin/maintenance-requests/${id}`, {
      status,
      admin_notes: adminNotes,
    });
  }
}