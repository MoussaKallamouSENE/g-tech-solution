import { Component, computed, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { Router, RouterLink } from '@angular/router';
import { CartService } from '../../services/cart.service';
import { AuthService, errorMessage } from '../../services/auth.service';
import { DeliveryZone, OrderService, formatPrice } from '../../services/order.service';

@Component({
  selector: 'app-checkout',
  imports: [RouterLink],
  templateUrl: './checkout.html',
})
export class Checkout {
  protected readonly cart = inject(CartService);
  protected readonly auth = inject(AuthService);
  private readonly orders = inject(OrderService);
  private readonly router = inject(Router);

  protected readonly zones = signal<DeliveryZone[]>([]);
  protected readonly zoneId = signal<number | null>(null);
  protected readonly error = signal('');
  protected readonly loading = signal(false);

  protected readonly fee = computed(() => this.zones().find((z) => z.id === this.zoneId())?.fee ?? 0);
  protected readonly total = computed(() => this.cart.total() + this.fee());
  protected readonly formatPrice = formatPrice;

  constructor() {
    this.orders.getZones().subscribe({ next: (zones) => this.zones.set(zones) });
  }

  protected selectZone(value: string): void {
    this.zoneId.set(value ? Number(value) : null);
  }

  protected submit(event: Event, name: string, phone: string, address: string, notes: string): void {
    event.preventDefault();
    this.error.set('');

    const zoneId = this.zoneId();
    if (zoneId === null) {
      this.error.set('Choisissez votre zone de livraison.');
      return;
    }
    if (this.cart.items().length === 0) {
      return;
    }

    this.loading.set(true);
    this.orders
      .createOrder({
        items: this.cart.items().map((item) => ({ product_id: item.id, quantity: item.quantity })),
        delivery_zone_id: zoneId,
        customer_name: name.trim(),
        customer_phone: phone.trim(),
        customer_address: address.trim(),
        notes: notes.trim(),
      })
      .subscribe({
        next: (order) => {
          this.cart.clear();
          this.router.navigate(['/commandes', order.id], { queryParams: { nouvelle: 1 } });
        },
        error: (err: HttpErrorResponse) => {
          this.error.set(errorMessage(err));
          this.loading.set(false);
        },
      });
  }
}