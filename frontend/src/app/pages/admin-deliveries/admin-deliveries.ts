import { Component, DestroyRef, inject, OnInit, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { forkJoin, timer } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  Delivery,
  DeliveryDriver,
  DeliveryService,
  PendingDeliveryOrder,
} from '../../services/delivery.service';
import { errorMessage } from '../../services/auth.service';

@Component({
  selector: 'app-admin-deliveries',
  templateUrl: './admin-deliveries.html',
})
export class AdminDeliveriesPage implements OnInit {
  private readonly service = inject(DeliveryService);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly deliveries = signal<Delivery[]>([]);
  protected readonly pendingOrders = signal<PendingDeliveryOrder[]>([]);
  protected readonly drivers = signal<DeliveryDriver[]>([]);
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly error = signal('');
  protected readonly message = signal('');
  private requestInFlight = false;
  private destroyed = false;

  ngOnInit(): void {
    this.load();
    timer(30_000, 30_000).pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => this.load(false));
  }

  ngOnDestroy(): void {
    this.destroyed = true;
  }

  protected load(showLoading = true): void {
    if (this.requestInFlight || this.destroyed) return;
    this.requestInFlight = true;
    if (showLoading) this.loading.set(true);
    this.error.set('');
    forkJoin({
      deliveries: this.service.getAdminDeliveries(),
      pendingOrders: this.service.getPendingOrders(),
      drivers: this.service.getDrivers(),
    }).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (data) => {
        this.requestInFlight = false;
        this.deliveries.set(data.deliveries.data);
        this.pendingOrders.set(data.pendingOrders);
        this.drivers.set(data.drivers);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.requestInFlight = false;
        this.error.set(errorMessage(err));
        this.deliveries.set([]);
        this.loading.set(false);
      },
    });
  }

  protected assign(orderId: number, driverId: string): void {
    if (!driverId || this.saving()) return;
    this.saving.set(true);
    this.message.set('');
    this.service.assignDriver(orderId, Number(driverId)).subscribe({
      next: () => {
        this.message.set('Livreur associé à la commande.');
        this.saving.set(false);
        this.load(false);
      },
      error: (err: HttpErrorResponse) => {
        this.error.set(errorMessage(err));
        this.saving.set(false);
      },
    });
  }

  protected markReady(delivery: Delivery): void {
    this.saving.set(true);
    this.service.markReady(delivery.id).subscribe({
      next: () => {
        this.message.set(`La livraison ${delivery.order.number} est prête à partir.`);
        this.saving.set(false);
        this.load(false);
      },
      error: (err: HttpErrorResponse) => {
        this.error.set(errorMessage(err));
        this.saving.set(false);
      },
    });
  }

  protected createDriver(event: Event, name: string, email: string, phone: string, password: string): void {
    event.preventDefault();
    this.error.set('');
    this.message.set('');
    this.saving.set(true);
    this.service.createDriver({ name: name.trim(), email: email.trim(), phone: phone.trim(), password }).subscribe({
      next: (driver) => {
        this.message.set(`Compte livreur créé pour ${driver.name}.`);
        this.saving.set(false);
        this.load(false);
      },
      error: (err: HttpErrorResponse) => {
        this.error.set(errorMessage(err));
        this.saving.set(false);
      },
    });
  }

  protected statusLabel(status: string): string {
    return ({
      to_prepare: 'À préparer',
      ready_for_delivery: 'À livrer',
      in_delivery: 'En cours de livraison',
      delivered: 'Livrée',
      failed: 'Échec de livraison',
      cancelled: 'Annulée',
    } as Record<string, string>)[status] ?? status;
  }

  protected date(value: string | null): string {
    return value ? new Date(value).toLocaleString('fr-FR') : '—';
  }

  protected activeDeliveries(): Delivery[] {
    return this.deliveries().filter((delivery) =>
      delivery.status === 'in_delivery'
      && delivery.location_sharing_active
      && delivery.location_available
      && delivery.latitude !== null
      && delivery.longitude !== null,
    );
  }

  protected isLocationStale(delivery: Delivery): boolean {
    return delivery.location_is_stale
      || (!!delivery.location_updated_at
        && Date.now() - new Date(delivery.location_updated_at).getTime() > 5 * 60 * 1000);
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
