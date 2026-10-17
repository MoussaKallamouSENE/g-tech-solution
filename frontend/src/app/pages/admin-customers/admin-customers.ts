import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AdminCustomer, AdminService } from '../../services/admin.service';
import { errorMessage } from '../../services/auth.service';
import { formatDate, formatPrice } from '../../services/order.service';

@Component({
  selector: 'app-admin-customers',
  imports: [RouterLink],
  templateUrl: './admin-customers.html',
})
export class AdminCustomers {
  private readonly service = inject(AdminService);

  protected readonly customers = signal<AdminCustomer[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly search = signal('');
  protected readonly loading = signal(true);
  protected readonly denied = signal(false);
  protected readonly error = signal('');

  protected readonly formatDate = formatDate;
  protected readonly formatPrice = formatPrice;

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set('');
    this.service.getCustomers({ page: this.page(), search: this.search() }).subscribe({
      next: (res) => {
        this.customers.set(res.data);
        this.total.set(res.total);
        this.lastPage.set(res.last_page);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        if (err.status === 401 || err.status === 403) {
          this.denied.set(true);
        }
        this.error.set(errorMessage(err));
        this.loading.set(false);
      },
    });
  }

  protected applySearch(value: string): void {
    this.search.set(value.trim());
    this.page.set(1);
    this.load();
  }

  protected clearSearch(): void {
    this.search.set('');
    this.page.set(1);
    this.load();
  }

  protected go(page: number): void {
    this.page.set(page);
    this.load();
  }
}