import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import {
  Order,
  OrderService,
  emailStatusClass,
  emailStatusLabel,
  formatDate,
  formatPrice,
  statusClass,
  statusLabel,
  whatsappStatusClass,
  whatsappStatusLabel,
} from '../../services/order.service';

@Component({
  selector: 'app-order-detail',
  imports: [RouterLink],
  templateUrl: './order-detail.html',
})
export class OrderDetail {
  private readonly route = inject(ActivatedRoute);
  private readonly service = inject(OrderService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly order = signal<Order | null>(null);
  protected readonly loading = signal(true);
  protected readonly isNew = this.route.snapshot.queryParamMap.get('nouvelle') === '1';

  protected readonly formatPrice = formatPrice;
  protected readonly formatDate = formatDate;
  protected readonly statusLabel = statusLabel;
  protected readonly statusClass = statusClass;
  protected readonly emailStatusLabel = emailStatusLabel;
  protected readonly emailStatusClass = emailStatusClass;
  protected readonly whatsappStatusLabel = whatsappStatusLabel;
  protected readonly whatsappStatusClass = whatsappStatusClass;

  protected readonly invoiceBusy = signal(false);
  protected readonly invoiceError = signal('');

  protected downloadInvoice(o: Order): void {
    this.invoiceError.set('');
    this.invoiceBusy.set(true);
    this.service.downloadInvoice(o.id, o.number).subscribe({
      next: () => this.invoiceBusy.set(false),
      error: () => {
        this.invoiceError.set('Impossible de télécharger la facture. Réessayez.');
        this.invoiceBusy.set(false);
      },
    });
  }

  constructor() {
    if (!this.auth.user()) {
      this.router.navigate(['/connexion'], { queryParams: { redirect: this.router.url } });
      this.loading.set(false);
      return;
    }

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