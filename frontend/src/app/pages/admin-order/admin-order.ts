import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AdminOrder, AdminService, STATUS_OPTIONS } from '../../services/admin.service';
import { formatDate, formatPrice, statusClass, statusLabel } from '../../services/order.service';
import { errorMessage } from '../../services/auth.service';

@Component({
  selector: 'app-admin-order',
  imports: [RouterLink],
  templateUrl: './admin-order.html',
})
export class AdminOrderPage {
  private readonly route = inject(ActivatedRoute);
  private readonly service = inject(AdminService);

  protected readonly order = signal<AdminOrder | null>(null);
  protected readonly loading = signal(true);
  protected readonly denied = signal(false);
  protected readonly saving = signal(false);
  protected readonly message = signal('');
  protected readonly error = signal('');

  protected readonly options = STATUS_OPTIONS;
  protected readonly formatPrice = formatPrice;
  protected readonly formatDate = formatDate;
  protected readonly statusLabel = statusLabel;
  protected readonly statusClass = statusClass;

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.service.getOrder(id).subscribe({
      next: (order) => {
        this.order.set(order);
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

  protected save(status: string): void {
    const current = this.order();
    if (!current) {
      return;
    }
    this.message.set('');
    this.error.set('');
    this.saving.set(true);
    this.service.updateStatus(current.id, status).subscribe({
      next: (updated) => {
        this.order.set({ ...current, ...updated });
        this.message.set('Statut mis à jour.');
        this.saving.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.error.set(errorMessage(err));
        this.saving.set(false);
      },
    });
  }
}