import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AdminService } from '../../services/admin.service';
import { errorMessage } from '../../services/auth.service';
import { Product } from '../../models/catalogue';
import { formatPrice } from '../../services/order.service';

@Component({
  selector: 'app-admin-products',
  imports: [RouterLink],
  templateUrl: './admin-products.html',
})
export class AdminProducts {
  private readonly service = inject(AdminService);

  protected readonly products = signal<Product[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly search = signal('');
  protected readonly lowStock = signal(false);
  protected readonly loading = signal(true);
  protected readonly denied = signal(false);
  protected readonly notice = signal('');
  protected readonly problem = signal('');

  protected readonly formatPrice = formatPrice;

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.service.getProducts({ search: this.search(), low_stock: this.lowStock(), page: this.page() }).subscribe({
      next: (res) => {
        this.products.set(res.data);
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

  protected toggleLowStock(checked: boolean): void {
    this.lowStock.set(checked);
    this.page.set(1);
    this.load();
  }

  protected go(page: number): void {
    this.page.set(page);
    this.load();
  }

  protected isLow(p: Product): boolean {
    return p.stock <= p.stock_alert_threshold;
  }

  protected saveStock(p: Product, value: string): void {
    this.notice.set('');
    this.problem.set('');
    const stock = Number(value);
    if (value.trim() === '' || !Number.isInteger(stock) || stock < 0) {
      this.problem.set('Le stock doit être un nombre entier positif ou nul.');
      return;
    }
    this.service.patchProduct(p.id, { stock }).subscribe({
      next: (updated) => {
        this.replace(updated);
        this.notice.set(`Stock de « ${updated.name} » mis à jour.`);
      },
      error: (err: HttpErrorResponse) => this.problem.set(errorMessage(err)),
    });
  }

  protected readonly confirmId = signal<number | null>(null);

  protected askDelete(p: Product): void {
    this.notice.set('');
    this.problem.set('');
    this.confirmId.set(p.id);
  }

  protected cancelDelete(): void {
    this.confirmId.set(null);
  }

  protected confirmDelete(p: Product): void {
    this.service.deleteProduct(p.id).subscribe({
      next: () => {
        this.confirmId.set(null);
        this.products.update((list) => list.filter((x) => x.id !== p.id));
        this.total.update((n) => Math.max(n - 1, 0));
        this.notice.set(`« ${p.name} » a été supprimé.`);
      },
      error: (err: HttpErrorResponse) => {
        this.confirmId.set(null);
        this.problem.set(errorMessage(err));
      },
    });
  }

  protected toggleActive(p: Product): void {
    this.notice.set('');
    this.problem.set('');
    this.service.patchProduct(p.id, { is_active: !p.is_active }).subscribe({
      next: (updated) => {
        this.replace(updated);
        this.notice.set(`« ${updated.name} » est maintenant ${updated.is_active ? 'visible' : 'masqué'} sur le site.`);
      },
      error: (err: HttpErrorResponse) => this.problem.set(errorMessage(err)),
    });
  }

  private replace(updated: Product): void {
    this.products.update((list) => list.map((x) => (x.id === updated.id ? { ...x, ...updated } : x)));
  }
}