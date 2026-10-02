import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Order, OrderService, formatDate, formatPrice, statusClass, statusLabel } from '../../services/order.service';

@Component({
  selector: 'app-orders',
  imports: [RouterLink],
  templateUrl: './orders.html',
})
export class Orders {
  private readonly service = inject(OrderService);

  protected readonly list = signal<Order[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  protected readonly formatPrice = formatPrice;
  protected readonly formatDate = formatDate;
  protected readonly statusLabel = statusLabel;
  protected readonly statusClass = statusClass;

  constructor() {
    this.service.getOrders().subscribe({
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