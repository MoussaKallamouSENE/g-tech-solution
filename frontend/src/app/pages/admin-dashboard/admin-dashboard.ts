import { Component, computed, inject, signal } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { API_BASE_URL } from '../../config/api';
import { formatDate, formatPrice, statusLabel } from '../../services/order.service';

interface Dashboard {
  sales_by_day: { date: string; total: number; count: number }[];
  previous_week_total: number;
  orders_total: number;
  orders_pending: number;
  repairs_new: number;
  deliveries_in_delivery: number;
  gps_active: number;
  low_stock: number;
  stock_units_available: number;
  purchases_month_total: number;
  purchases_month_count: number;
  top_products: { name: string; sold: number | string }[];
  recent_orders: { id: number; number: string; customer_name: string; total: number; status: string; created_at: string }[];
  low_stock_products: { id: number; name: string; stock: number; stock_alert_threshold: number }[];
  recent_purchases: {
    id: number;
    number: string;
    received_at: string;
    total_cost: number;
    items_count: number;
    supplier: { id: number; name: string };
  }[];
}

@Component({
  selector: 'app-admin-dashboard',
  imports: [RouterLink],
  templateUrl: './admin-dashboard.html',
})
export class AdminDashboard {
  private readonly http = inject(HttpClient);
  protected readonly auth = inject(AuthService);

  protected readonly data = signal<Dashboard | null>(null);
  protected readonly denied = signal(false);
  protected readonly error = signal(false);
  protected readonly errorMessage = signal('');

  protected readonly formatPrice = formatPrice;
  protected readonly formatDate = formatDate;
  protected readonly statusLabel = statusLabel;

  protected readonly weekTotal = computed(() =>
    (this.data()?.sales_by_day ?? []).reduce((sum, d) => sum + d.total, 0),
  );
  protected readonly weekCount = computed(() =>
    (this.data()?.sales_by_day ?? []).reduce((sum, d) => sum + d.count, 0),
  );
  protected readonly delta = computed<number | null>(() => {
    const previous = this.data()?.previous_week_total ?? 0;
    return previous > 0 ? Math.round(((this.weekTotal() - previous) / previous) * 100) : null;
  });
  protected readonly topMax = computed(() =>
    Math.max(...(this.data()?.top_products ?? []).map((p) => Number(p.sold)), 1),
  );
  protected readonly bars = computed(() => {
    const days = this.data()?.sales_by_day ?? [];
    const max = Math.max(...days.map((d) => d.total), 1);
    const peak = Math.max(...days.map((d) => d.total), 0);
    return days.map((d, i) => {
      const h = d.total > 0 ? Math.max(Math.round((d.total / max) * 140), 4) : 0;
      const weekday = new Date(d.date + 'T12:00:00')
        .toLocaleDateString('fr-FR', { weekday: 'short' })
        .replace('.', '');
      return {
        key: d.date,
        x: i * 100 + 22,
        cx: i * 100 + 50,
        y: 180 - h,
        h,
        peak: peak > 0 && d.total === peak,
        value: d.total,
        count: d.count,
        label: d.total >= 1000 ? Math.round(d.total / 1000) + 'k' : String(d.total),
        day: weekday.charAt(0).toUpperCase() + weekday.slice(1),
      };
    });
  });

  constructor() {
    this.loadDashboard();
  }

  protected loadDashboard(): void {
    this.http.get<Dashboard>(`${API_BASE_URL}/admin/dashboard`).subscribe({
      next: (data) => this.data.set(data),
      error: (err: HttpErrorResponse) => {
        if (err.status === 401 || err.status === 403) {
          this.denied.set(true);
          this.error.set(false);
          this.errorMessage.set('');
        } else if (err.status === 0) {
          this.error.set(true);
          this.errorMessage.set('Le serveur ne répond pas. Vérifiez que Docker est démarré.');
        } else if (err.status >= 500) {
          this.error.set(true);
          this.errorMessage.set('Le serveur a rencontré une erreur. Réessayez dans un instant.');
        } else {
          this.error.set(true);
          this.errorMessage.set('Une erreur est survenue. Réessayez.');
        }
      },
    });
  }

  protected retry(): void {
    this.error.set(false);
    this.errorMessage.set('');
    this.loadDashboard();
  }

  protected statusStyle(status: string): string {
    switch (status) {
      case 'pending':
        return 'bg-[#FFF4DB] text-[#8A5A00]';
      case 'delivered':
        return 'bg-[#DDF6EC] text-[#0B7A55]';
      case 'cancelled':
        return 'bg-[#FDE4E4] text-[#B42318]';
      case 'out_for_delivery':
        return 'bg-[#DDF0FB] text-[#0B5A8A]';
      default:
        return 'bg-[#E4EAFF] text-[#1E3AD1]';
    }
  }
}
