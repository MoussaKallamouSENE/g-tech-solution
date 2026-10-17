import { Component, DestroyRef, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Delivery, DeliveryService } from '../../services/delivery.service';
import { DriverLocationService } from '../../services/driver-location.service';
import { AuthService, errorMessage } from '../../services/auth.service';

@Component({
  selector: 'app-driver-deliveries',
  imports: [DatePipe],
  templateUrl: './driver-deliveries.html',
})
export class DriverDeliveriesPage {
  private readonly service = inject(DeliveryService);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly auth = inject(AuthService);
  protected readonly location = inject(DriverLocationService);
  protected readonly deliveries = signal<Delivery[]>([]);
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly error = signal('');
  protected readonly message = signal('');
  private requestInFlight = false;

  constructor() {
    this.load();
  }

  protected load(): void {
    if (this.requestInFlight) return;
    this.requestInFlight = true;
    this.loading.set(true);
    this.service.getDriverDeliveries().pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (result) => {
        this.requestInFlight = false;
        this.deliveries.set(result.data);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.requestInFlight = false;
        this.error.set(errorMessage(err));
        this.loading.set(false);
      },
    });
  }

  protected async start(delivery: Delivery): Promise<void> {
    this.error.set('');
    this.saving.set(true);
    try {
      await this.location.start(delivery.id, delivery.status !== 'in_delivery');
      this.message.set('Tournée démarrée et partage GPS autorisé.');
      this.load();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Impossible de démarrer le partage GPS.');
      this.load();
    } finally {
      this.saving.set(false);
    }
  }

  protected async stop(delivery: Delivery): Promise<void> {
    this.saving.set(true);
    try {
      await this.location.stop(delivery.id);
      this.message.set('Partage GPS arrêté.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Impossible de confirmer l’arrêt du partage.');
    } finally {
      this.saving.set(false);
      this.load();
    }
  }

  protected finish(delivery: Delivery): void {
    if (!globalThis.confirm('Confirmez-vous que la commande a bien été remise au client ?')) return;
    this.updateStatus(delivery, 'delivered', { delivery_confirmed: true });
  }

  protected fail(delivery: Delivery, reason: string): void {
    this.updateStatus(delivery, 'failed', { reason: reason.trim() });
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

  protected statusBadgeClass(status: string): string {
    return ({
      to_prepare: 'bg-amber-50 text-amber-700',
      ready_for_delivery: 'bg-cobalt/10 text-cobalt',
      in_delivery: 'bg-cobalt text-white',
      delivered: 'bg-mint text-mint',
      failed: 'bg-red-50 text-red-700',
      cancelled: 'bg-red-50 text-red-700',
    } as Record<string, string>)[status] ?? 'bg-ink/5 text-ink';
  }

  protected date(value: string | null): string {
    return value ? new Date(value).toLocaleString('fr-FR') : '—';
  }

  protected ngOnDestroy(): void {
    const activeId = this.location.activeDeliveryId();
    if (activeId !== null) {
      void this.location.stop(activeId).catch(() => {
        console.warn('Le serveur n’a pas confirmé l’arrêt du partage GPS.');
      });
    }
  }

  private updateStatus(
    delivery: Delivery,
    status: 'delivered' | 'failed',
    extra: { reason?: string; delivery_confirmed?: boolean },
  ): void {
    this.error.set('');
    this.saving.set(true);
    this.service.updateDriverStatus(delivery.id, status, extra).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.location.stopLocalTracking();
        this.message.set(status === 'delivered' ? 'Remise au client confirmée.' : 'Échec de livraison enregistré.');
        this.saving.set(false);
        this.load();
      },
      error: (err: HttpErrorResponse) => {
        this.error.set(errorMessage(err));
        this.saving.set(false);
      },
    });
  }
}
