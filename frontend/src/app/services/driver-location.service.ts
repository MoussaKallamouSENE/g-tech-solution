import { Injectable, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { DELIVERY_GPS_MAX_ACCURACY_METERS, DELIVERY_GPS_MAX_AGE_MS, DELIVERY_GPS_UPDATE_INTERVAL_MS } from '../config/delivery';
import { DeliveryService } from './delivery.service';

@Injectable({ providedIn: 'root' })
export class DriverLocationService {
  private readonly deliveries = inject(DeliveryService);
  readonly message = signal('');
  readonly error = signal('');
  readonly activeDeliveryId = signal<number | null>(null);
  private watchId: number | null = null;
  private timerId: number | null = null;
  private latestPosition: GeolocationPosition | null = null;
  private sending = false;

  async start(deliveryId: number, transitionToInDelivery: boolean): Promise<void> {
    if (this.activeDeliveryId() === deliveryId) {
      this.message.set('Le partage GPS est déjà actif sur cet appareil.');
      return;
    }
    const previousDeliveryId = this.activeDeliveryId();
    if (previousDeliveryId !== null) {
      await this.stop(previousDeliveryId);
    }

    this.error.set('');
    this.message.set('Demande d’autorisation GPS...');
    if (!navigator.geolocation) {
      const message = 'La géolocalisation n’est pas disponible sur cet appareil.';
      this.error.set(message);
      throw new Error(message);
    }

    let initialPosition: GeolocationPosition;
    try {
      initialPosition = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, {
          enableHighAccuracy: true,
          maximumAge: 0,
          timeout: 15_000,
        });
      });
    } catch (error) {
      const message = this.geolocationError(error);
      this.error.set(message);
      throw new Error(message);
    }

    if (transitionToInDelivery) {
      await firstValueFrom(this.deliveries.updateDriverStatus(deliveryId, 'in_delivery'));
    }
    await firstValueFrom(this.deliveries.startLocationSharing(deliveryId));
    this.activeDeliveryId.set(deliveryId);
    this.latestPosition = initialPosition;
    this.message.set('Partage GPS actif. Le navigateur peut limiter le suivi en arrière-plan.');

    this.watchId = navigator.geolocation.watchPosition(
      (position) => {
        this.latestPosition = position;
      },
      (error) => {
        this.error.set(this.geolocationError(error));
      },
      { enableHighAccuracy: true, maximumAge: 10_000, timeout: 20_000 },
    );
    this.timerId = window.setInterval(() => void this.sendLatestPosition(), DELIVERY_GPS_UPDATE_INTERVAL_MS);
    window.addEventListener('online', this.flushLatestPosition);
    await this.sendLatestPosition();
  }

  async stop(deliveryId: number): Promise<void> {
    this.stopLocalTracking();
    this.message.set('Arrêt du partage de localisation...');
    try {
      await firstValueFrom(this.deliveries.stopLocationSharing(deliveryId));
      this.message.set('Partage de localisation arrêté.');
      this.error.set('');
    } catch {
      const message = 'Le GPS est arrêté sur cet appareil, mais le serveur n’a pas confirmé l’arrêt. Vérifiez la connexion puis réessayez.';
      this.error.set(message);
      throw new Error(message);
    }
  }

  stopLocalTracking(): void {
    if (this.watchId !== null && navigator.geolocation) {
      navigator.geolocation.clearWatch(this.watchId);
      this.watchId = null;
    }
    if (this.timerId !== null) {
      window.clearInterval(this.timerId);
      this.timerId = null;
    }
    window.removeEventListener('online', this.flushLatestPosition);
    this.activeDeliveryId.set(null);
    this.latestPosition = null;
  }

  private readonly flushLatestPosition = (): void => {
    void this.sendLatestPosition();
  };

  private lastSentCoords: { latitude: number; longitude: number; accuracy: number } | null = null;

  private static coordsEqual(a: { latitude: number; longitude: number; accuracy: number }, b: { latitude: number; longitude: number; accuracy: number }): boolean {
    const tolerance = 0.0001; // ~11 mètres
    return Math.abs(a.latitude - b.latitude) < tolerance
      && Math.abs(a.longitude - b.longitude) < tolerance
      && a.accuracy === b.accuracy;
  }

  private async sendLatestPosition(): Promise<void> {
    const deliveryId = this.activeDeliveryId();
    const position = this.latestPosition;
    if (deliveryId === null || !position || this.sending) return;

    if (navigator.onLine === false) {
      this.message.set('Hors connexion. Une seule position récente est conservée temporairement sur cet appareil.');
      return;
    }
    if (Date.now() - position.timestamp > DELIVERY_GPS_MAX_AGE_MS) {
      this.message.set('Position GPS trop ancienne; attente d’une nouvelle lecture.');
      return;
    }
    if (position.coords.accuracy > DELIVERY_GPS_MAX_ACCURACY_METERS) {
      this.message.set('Précision GPS insuffisante; envoi différé pour préserver la qualité de la position.');
      return;
    }

    const coords = {
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      accuracy: Math.round(position.coords.accuracy),
    };

    // Évite les écritures inutiles si la position n’a pas significativement changé.
    if (this.lastSentCoords && DriverLocationService.coordsEqual(this.lastSentCoords, coords)) {
      return;
    }

    this.sending = true;
    try {
      await firstValueFrom(this.deliveries.updateLocation(deliveryId, coords));
      this.lastSentCoords = coords;
      this.message.set(`Position actualisée (±${coords.accuracy} m).`);
      this.error.set('');
    } catch (error) {
      if (error instanceof HttpErrorResponse) {
        if ([403, 404, 422].includes(error.status)) {
          this.stopLocalTracking();
          this.error.set('Le serveur a arrêté ou refusé le partage GPS pour cette livraison. Le suivi local a été interrompu.');
          this.message.set('');
          return;
        }
        if (error.status === 401) {
          this.stopLocalTracking();
          this.error.set('Session expirée. Reconnectez-vous pour reprendre le suivi GPS.');
          this.message.set('');
          return;
        }
      }
      this.message.set('Connexion indisponible. La dernière position restera en mémoire jusqu’à la prochaine tentative.');
    } finally {
      this.sending = false;
    }
  }

  private geolocationError(error: unknown): string {
    const code = (error as GeolocationPositionError | null)?.code;
    if (code === 1) return 'Autorisation GPS refusée. Autorisez la localisation pour démarrer le partage.';
    if (code === 2) return 'Position GPS indisponible. Vérifiez les réglages de localisation de l’appareil.';
    if (code === 3) return 'La demande GPS a expiré. Réessayez dans un endroit mieux couvert.';
    return 'Impossible d’obtenir la position GPS.';
  }
}
