import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { vi } from 'vitest';
import { API_BASE_URL } from '../config/api';
import { DriverLocationService } from './driver-location.service';

describe('DriverLocationService', () => {
  let http: HttpTestingController;
  let service: DriverLocationService;
  let originalOnline: PropertyDescriptor | undefined;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
    service = TestBed.inject(DriverLocationService);
    originalOnline = Object.getOwnPropertyDescriptor(navigator, 'onLine');
  });

  afterEach(() => {
    service.stopLocalTracking();
    http.verify();
    if (originalOnline) {
      Object.defineProperty(navigator, 'onLine', originalOnline);
    }
  });

  it('explains denied GPS permission and does not start server sharing', async () => {
    Object.defineProperty(navigator, 'geolocation', {
      configurable: true,
      value: {
        getCurrentPosition: (_success: PositionCallback, error: PositionErrorCallback) => error({ code: 1 } as GeolocationPositionError),
        watchPosition: vi.fn(),
        clearWatch: vi.fn(),
      },
    });

    await expect(service.start(17, true)).rejects.toThrow('Autorisation GPS refusée');
    expect(service.error()).toContain('Autorisation GPS refusée');
    expect(service.activeDeliveryId()).toBeNull();
  });

  it('keeps only the current position in memory while offline and stops local tracking', async () => {
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: false });
    const position = {
      timestamp: Date.now(),
      coords: { latitude: 14.6928, longitude: -17.4467, accuracy: 12 },
    } as GeolocationPosition;
    const getCurrentPosition = vi.fn((success: PositionCallback) => success(position));
    Object.defineProperty(navigator, 'geolocation', {
      configurable: true,
      value: {
        getCurrentPosition,
        watchPosition: vi.fn(() => 1),
        clearWatch: vi.fn(),
      },
    });

    const start = service.start(17, false);
    await Promise.resolve();
    http.expectOne(`${API_BASE_URL}/driver/deliveries/17/location/start`).flush({});
    await start;

    expect(service.activeDeliveryId()).toBe(17);
    expect(service.message()).toContain('Hors connexion');
    await service.start(17, false);
    expect(getCurrentPosition).toHaveBeenCalledTimes(1);
    http.expectNone(`${API_BASE_URL}/driver/deliveries/17/location`);

    const stop = service.stop(17);
    http.expectOne(`${API_BASE_URL}/driver/deliveries/17/location/stop`).flush({});
    await stop;
    expect(service.activeDeliveryId()).toBeNull();
    expect(service.message()).toBe('Partage de localisation arrêté.');
  });

  it('stops local GPS polling when the server reports that sharing is no longer allowed', async () => {
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
    const clearWatch = vi.fn();
    Object.defineProperty(navigator, 'geolocation', {
      configurable: true,
      value: {
        getCurrentPosition: (success: PositionCallback) => success({
          timestamp: Date.now(),
          coords: { latitude: 14.6928, longitude: -17.4467, accuracy: 12 },
        } as GeolocationPosition),
        watchPosition: vi.fn(() => 1),
        clearWatch,
      },
    });

    const start = service.start(17, false);
    await Promise.resolve();
    http.expectOne(`${API_BASE_URL}/driver/deliveries/17/location/start`).flush({});
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
    http.expectOne(`${API_BASE_URL}/driver/deliveries/17/location`).flush(
      { message: 'Le partage GPS n’est pas actif.' },
      { status: 422, statusText: 'Unprocessable Entity' },
    );
    await start;

    expect(service.activeDeliveryId()).toBeNull();
    expect(clearWatch).toHaveBeenCalledWith(1);
    expect(service.error()).toContain('Le serveur a arrêté ou refusé le partage GPS');
  });
});
