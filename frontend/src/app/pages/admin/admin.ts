import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AdminService, AdminStats, STATUS_OPTIONS } from '../../services/admin.service';
import { Order, formatDate, formatPrice, statusClass, statusLabel } from '../../services/order.service';

@Component({
  selector: 'app-admin',
  imports: [RouterLink],
  templateUrl: './admin.html',
})
export class Admin {
  private readonly service = inject(AdminService);

  protected readonly stats = signal<AdminStats | null>(null);
  protected readonly orders = signal<Order[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly status = signal('');
  protected readonly search = signal('');
  protected readonly loading = signal(true);
  protected readonly denied = signal(false);

  protected readonly options = STATUS_OPTIONS;
  protected readonly formatPrice = formatPrice;
  protected readonly formatDate = formatDate;
  protected readonly statusLabel = statusLabel;
  protected readonly statusClass = statusClass;

  constructor() {
    this.service.getStats().subscribe({
      next: (stats) => this.stats.set(stats),
      error: (err: HttpErrorResponse) => this.onError(err),
    });
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.service.getOrders({ status: this.status(), search: this.search(), page: this.page() }).subscribe({
      next: (res) => {
        this.orders.set(res.data);
        this.total.set(res.total);
        this.lastPage.set(res.last_page);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.onError(err);
        this.loading.set(false);
      },
    });
  }

  private onError(err: HttpErrorResponse): void {
    if (err.status === 401 || err.status === 403) {
      this.denied.set(true);
    }
  }

  protected setStatus(value: string): void {
    this.status.set(value);
    this.page.set(1);
    this.load();
  }

  protected setSearch(value: string): void {
    this.search.set(value.trim());
    this.page.set(1);
    this.load();
  }

  protected go(page: number): void {
    this.page.set(page);
    this.load();
  }
}