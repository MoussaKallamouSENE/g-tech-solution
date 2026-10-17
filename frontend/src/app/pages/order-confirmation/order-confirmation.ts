import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AuthService, errorMessage } from '../../services/auth.service';
import { OrderService, formatPrice, formatDate } from '../../services/order.service';

@Component({
  selector: 'app-order-confirmation',
  imports: [RouterLink],
  templateUrl: './order-confirmation.html',
})
export class OrderConfirmation {
  private readonly orders = inject(OrderService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly orderId = signal<string>('');
  protected readonly orderNumber = signal<string>('');
  protected readonly orderTotal = signal(0);
  protected readonly orderDate = signal('');
  protected readonly formatPrice = formatPrice;

  constructor() {
    // Récupérer les données de la commande depuis l'état de navigation
    const nav = this.router.getCurrentNavigation();
    const state = nav?.extras?.state as { orderId: string; orderNumber: string; orderTotal: number; orderDate: string } | undefined;

    if (state) {
      this.orderId.set(state.orderId);
      this.orderNumber.set(state.orderNumber);
      this.orderTotal.set(state.orderTotal);
      this.orderDate.set(state.orderDate);
    } else {
      // Rediriger vers les commandes si pas de données
      this.router.navigate(['/commandes']);
    }
  }

  protected goToHome(): void {
    this.router.navigate(['/']);
  }

  protected viewOrder(): void {
    if (this.orderId()) {
      this.router.navigate(['/commandes', this.orderId()]);
    }
  }
}
