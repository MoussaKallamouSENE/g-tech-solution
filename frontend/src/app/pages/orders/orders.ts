import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { Order, OrderService, formatDate, formatPrice, statusClass, statusLabel } from '../../services/order.service';

@Component({
  selector: 'app-orders',
  imports: [RouterLink],
  templateUrl: './orders.html',
})
export class Orders {
  private readonly service = inject(OrderService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly list = signal<Order[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  protected readonly status = signal('all');
  protected readonly sort = signal('newest');

  protected readonly formatPrice = formatPrice;
  protected readonly formatDate = formatDate;
  protected readonly statusLabel = statusLabel;
  protected readonly statusClass = statusClass;

  protected readonly visibleList = computed(() => {
    const list = this.list();
    const statusFilter = this.status();
    const sortMode = this.sort();

    const filtered = statusFilter === 'all' ? list : list.filter((order) => order.status === statusFilter);

    const sorted = [...filtered];
    switch (sortMode) {
      case 'oldest':
        sorted.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
        break;
      case 'total_asc':
        sorted.sort((a, b) => a.total - b.total);
        break;
      case 'total_desc':
        sorted.sort((a, b) => b.total - a.total);
        break;
      case 'newest':
      default:
        sorted.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
        break;
    }

    return sorted;
  });

  constructor() {
    if (!this.auth.user()) {
      this.router.navigate(['/connexion'], { queryParams: { redirect: '/commandes' } });
      this.loading.set(false);
      this.error.set(true);
      return;
    }

    this.load();
  }

  protected setStatus(value: string): void {
    this.status.set(value);
    this.load();
  }

  protected setSort(value: string): void {
    this.sort.set(value);
  }

  private load(): void {
    this.loading.set(true);
    this.service.getOrders({ status: this.status() === 'all' ? undefined : this.status(), sort: this.sort() }).subscribe({
      next: (orders) => {
        this.list.set(orders);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }
}