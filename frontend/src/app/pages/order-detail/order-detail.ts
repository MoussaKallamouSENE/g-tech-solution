import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Order, OrderService, formatDate, formatPrice, statusClass, statusLabel } from '../../services/order.service';

@Component({
  selector: 'app-order-detail',
  imports: [RouterLink],
  templateUrl: './order-detail.html',
})
export class OrderDetail {
  private readonly route = inject(ActivatedRoute);
  private readonly service = inject(OrderService);

  protected readonly order = signal<Order | null>(null);
  protected readonly loading = signal(true);
  protected readonly isNew = this.route.snapshot.queryParamMap.get('nouvelle') === '1';

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
      error: () => this.loading.set(false),
    });
  }
}