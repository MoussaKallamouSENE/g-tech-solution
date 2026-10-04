import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import {
  MAINTENANCE_STATUSES,
  MaintenanceRequest,
  MaintenanceService,
  deviceLabel,
  maintenanceStatusClass,
  maintenanceStatusLabel,
} from '../../services/maintenance.service';
import { errorMessage } from '../../services/auth.service';
import { formatDate } from '../../services/order.service';

@Component({
  selector: 'app-admin-maintenance',
  imports: [RouterLink],
  templateUrl: './admin-maintenance.html',
})
export class AdminMaintenance {
  private readonly service = inject(MaintenanceService);

  protected readonly items = signal<MaintenanceRequest[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly status = signal('');
  protected readonly search = signal('');
  protected readonly loading = signal(true);
  protected readonly denied = signal(false);
  protected readonly notice = signal('');
  protected readonly problem = signal('');

  protected readonly statuses = MAINTENANCE_STATUSES;
  protected readonly deviceLabel = deviceLabel;
  protected readonly statusLabel = maintenanceStatusLabel;
  protected readonly statusClass = maintenanceStatusClass;
  protected readonly formatDate = formatDate;

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.service.list({ status: this.status(), search: this.search(), page: this.page() }).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.total.set(res.total);
        this.lastPage.set(res.last_page);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        if (err.status === 401 || err.status === 403) {
          this.denied.set(true);
        }
        this.loading.set(false);
      },
    });
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

  protected save(item: MaintenanceRequest, status: string, notes: string): void {
    this.notice.set('');
    this.problem.set('');
    this.service.update(item.id, status, notes.trim() || null).subscribe({
      next: (updated) => {
        this.items.update((list) => list.map((x) => (x.id === updated.id ? updated : x)));
        this.notice.set(`Demande ${updated.number} mise à jour.`);
      },
      error: (err: HttpErrorResponse) => this.problem.set(errorMessage(err)),
    });
  }
}