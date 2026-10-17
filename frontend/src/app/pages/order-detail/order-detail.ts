import { Component, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Subscription, timer } from 'rxjs';
import { AuthService } from '../../services/auth.service';
import { CustomerDeliveryTracking, DeliveryService } from '../../services/delivery.service';
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
export class OrderDetail implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly service = inject(OrderService);
  private readonly deliveryService = inject(DeliveryService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  private trackingRequestInFlight = false;
  private trackingPoll?: Subscription;
  private destroyed = false;

  protected readonly order = signal<Order | null>(null);
  protected readonly loading = signal(true);
  protected readonly tracking = signal<CustomerDeliveryTracking | null>(null);
  protected readonly trackingLoading = signal(false);
  protected readonly trackingError = signal('');
  protected readonly message = signal('');
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
    this.service.downloadInvoice(o.id, o.invoice_number).subscribe({
      next: () => this.invoiceBusy.set(false),
      error: () => {
        this.invoiceError.set('Impossible de télécharger la facture. Réessayez.');
        this.invoiceBusy.set(false);
      },
    });
  }

  protected viewInvoice(o: Order): void {
    this.invoiceError.set('');
    this.invoiceBusy.set(true);
    this.service.openInvoice(o.id, o.invoice_number).subscribe({
      next: () => this.invoiceBusy.set(false),
      error: (error: unknown) => {
        this.invoiceError.set(error instanceof Error ? error.message : 'Impossible d’ouvrir la facture. Réessayez.');
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
        this.loadTracking(id);
      },
      error: () => this.loading.set(false),
    });
  }

  ngOnInit(): void {
    if (!this.shouldPollTracking()) return;
    this.trackingPoll = timer(30_000, 30_000)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        const order = this.order();
        if (order && this.shouldPollTracking()) {
          this.loadTracking(order.id, false);
        } else {
          this.trackingPoll?.unsubscribe();
        }
      });
  }

  ngOnDestroy(): void {
    this.destroyed = true;
  }

  protected formatDateTime(value: string | null): string {
    return value ? new Date(value).toLocaleString('fr-FR') : '—';
  }

  protected deliveryStatusLabel(status: string): string {
    return ({
      to_prepare: 'À préparer',
      ready_for_delivery: 'À livrer',
      in_delivery: 'En cours de livraison',
      delivered: 'Livrée',
      failed: 'Échec de livraison',
      cancelled: 'Annulée',
    } as Record<string, string>)[status] ?? status;
  }

  private loadTracking(orderId: number, showLoading = true): void {
    if (this.trackingRequestInFlight || this.destroyed) return;
    this.trackingRequestInFlight = true;
    if (showLoading) this.trackingLoading.set(true);
    this.trackingError.set('');
    this.deliveryService.getCustomerDeliveryTracking(orderId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (tracking) => {
          this.trackingRequestInFlight = false;
          this.tracking.set(tracking);
          if (!this.shouldPollTracking()) this.trackingPoll?.unsubscribe();
          const order = this.order();
          if (order && order.status !== tracking.order.status) {
            this.order.set({ ...order, status: tracking.order.status });
          }
          this.trackingLoading.set(false);
        },
        error: () => {
          this.trackingRequestInFlight = false;
          this.trackingError.set('Impossible de récupérer le suivi de livraison. Réessayez dans un instant.');
          this.trackingLoading.set(false);
        },
      });
  }

  protected retryTracking(): void {
    const order = this.order();
    if (order) this.loadTracking(order.id);
  }

  protected isLocationStale(delivery: NonNullable<CustomerDeliveryTracking['delivery']>): boolean {
    return delivery.location_is_stale
      || (!!delivery.location_updated_at
        && Date.now() - new Date(delivery.location_updated_at).getTime() > delivery.location_stale_after_seconds * 1000);
  }

  private shouldPollTracking(): boolean {
    const tracking = this.tracking();
    if (!tracking) return true;
    if (['delivered', 'cancelled'].includes(tracking.order.status)) return false;
    return !tracking.delivery || !['delivered', 'failed', 'cancelled'].includes(tracking.delivery.status);
  }

  protected copyCoords(latitude: number, longitude: number): void {
    navigator.clipboard.writeText(`${latitude}, ${longitude}`).then(() => {
      this.message.set('Coordonnées GPS copiées.');
      setTimeout(() => this.message.set(''), 3000);
    }, () => {
      this.message.set('Impossible de copier les coordonnées.');
    });
  }

  protected openMapLink(latitude: number, longitude: number): void {
    const url = `https://www.openstreetmap.org/#map=13/${latitude}/${longitude}`;
    const win = window.open(url, '_blank');
    if (!win) {
      this.message.set('Autorisez les fenêtres contextuelles pour ouvrir la carte.');
    }
  }
}
