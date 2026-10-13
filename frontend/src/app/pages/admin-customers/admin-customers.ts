import { Component, inject, signal } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpParams } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { Paginated } from '../../models/catalogue';
import { formatDate, formatPrice } from '../../services/order.service';

interface Customer {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at: string;
  orders_count: number;
  spent: number | string | null;
}

@Component({
  selector: 'app-admin-customers',
  imports: [RouterLink],
  templateUrl: './admin-customers.html',
})
export class AdminCustomers {
  private readonly http = inject(HttpClient);

  protected readonly customers = signal<Customer[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly search = signal('');
  protected readonly loading = signal(true);
  protected readonly denied = signal(false);

  protected readonly formatDate = formatDate;
  protected readonly formatPrice = formatPrice;

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    let params = new HttpParams().set('page', String(this.page()));
    if (this.search()) {
      params = params.set('search', this.search());
    }
    this.http.get<Paginated<Customer>>('http://localhost/api/admin/customers', { params }).subscribe({
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
        this.loading.set(false);
      },
    });
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