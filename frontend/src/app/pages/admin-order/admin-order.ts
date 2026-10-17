import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AdminOrder, AdminService, STATUS_OPTIONS, STATUS_TRANSITIONS } from '../../services/admin.service';
import { OrderService, emailStatusClass, emailStatusLabel, formatDate, formatPrice, statusClass, statusLabel, whatsappStatusClass, whatsappStatusLabel } from '../../services/order.service';
import { errorMessage } from '../../services/auth.service';

@Component({
  selector: 'app-admin-order',
  imports: [RouterLink],
  templateUrl: './admin-order.html',
})
export class AdminOrderPage {
  private readonly route = inject(ActivatedRoute);
  private readonly service = inject(AdminService);
  private readonly orders = inject(OrderService);

  protected readonly order = signal<AdminOrder | null>(null);
  protected readonly loading = signal(true);
  protected readonly denied = signal(false);
  protected readonly loadError = signal('');
  protected readonly saving = signal(false);
  protected readonly message = signal('');
  protected readonly error = signal('');

  protected readonly availableOptions = (status: string) =>
    STATUS_OPTIONS.filter((option) => option.value === status || STATUS_TRANSITIONS[status]?.includes(option.value));
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

  private readonly orderId = Number(this.route.snapshot.paramMap.get('id'));

  protected downloadInvoice(o: AdminOrder): void {
    this.invoiceError.set('');
    this.invoiceBusy.set(true);
    this.orders.downloadInvoice(o.id, o.number).subscribe({
      next: () => this.invoiceBusy.set(false),
      error: () => {
        this.invoiceError.set('Impossible de télécharger la facture. Réessayez.');
        this.invoiceBusy.set(false);
      },
    });
  }

  constructor() {
    this.loadOrder();
  }

  protected loadOrder(): void {
    this.loading.set(true);
    this.loadError.set('');
    this.denied.set(false);

    if (!Number.isSafeInteger(this.orderId) || this.orderId < 1) {
      this.loadError.set('Identifiant de commande invalide.');
      this.loading.set(false);
      return;
    }

    this.service.getOrder(this.orderId).subscribe({
      next: (order) => {
        this.order.set(order);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        if (err.status === 401 || err.status === 403) {
          this.denied.set(true);
        } else if (err.status === 404) {
          this.loadError.set('Cette commande est introuvable.');
        } else {
          this.loadError.set(errorMessage(err));
        }
        this.loading.set(false);
      },
    });
  }

  protected save(status: string): void {
    const current = this.order();
    if (!current || status === current.status || !STATUS_TRANSITIONS[current.status]?.includes(status)) {
      return;
    }

    if (
      (status === 'confirmed' || status === 'cancelled') &&
      !globalThis.confirm(
        status === 'confirmed'
          ? 'Confirmer cette commande ? Le stock sera déduit immédiatement.'
          : 'Annuler cette commande ? Les produits seront remis en stock si le stock a déjà été déduit.',
      )
    ) {
      return;
    }

    this.message.set('');
    this.error.set('');
    this.saving.set(true);
    this.service.updateStatus(current.id, status).subscribe({
      next: (updated) => {
        this.order.set({ ...current, ...updated });
        const label = statusLabel(status);
        this.message.set(status === 'confirmed' ? `Commande confirmée : le stock a été mis à jour (${label}).` : `Statut mis à jour : ${label}.`);
        this.saving.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.error.set(errorMessage(err));
        this.saving.set(false);
      },
    });
  }
}