import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter } from '@angular/router';
import { Observable, of, Subject } from 'rxjs';
import { OrderDetail } from './order-detail';
import { AuthService } from '../../services/auth.service';
import { CustomerDeliveryTracking, DeliveryService } from '../../services/delivery.service';
import { Order, OrderService } from '../../services/order.service';

describe('OrderDetail delivery tracking', () => {
  const order: Order = {
    id: 5,
    number: 'GT-000005',
    invoice_number: 'GTS-FA-2026-0123456789ABCDEF',
    status: 'out_for_delivery',
    payment_method: 'cash_on_delivery',
    customer_name: 'Client Test',
    customer_phone: '+221770000000',
    customer_address: 'Dakar, Plateau, rue 1',
    notes: null,
    subtotal: 2000,
    delivery_fee: 500,
    total: 2500,
    created_at: '2026-10-15T10:00:00.000Z',
  };

  const tracking: CustomerDeliveryTracking = {
    order: { id: 5, number: 'GT-000005', status: 'out_for_delivery' },
    delivery: {
      id: 17,
      status: 'in_delivery',
      assigned_at: '2026-10-15T10:00:00.000Z',
      picked_up_at: '2026-10-15T10:30:00.000Z',
      driver: { name: 'Livreur Test' },
      location_sharing_active: true,
      location_available: true,
      location_is_stale: false,
      location_stale_after_seconds: 300,
      latitude: 14.6928,
      longitude: -17.4467,
      location_accuracy: 12,
      location_updated_at: '2026-10-15T10:35:00.000Z',
    },
  };

  const getCustomerDeliveryTracking = vi.fn<() => Observable<CustomerDeliveryTracking>>(() => of(tracking));

  beforeEach(async () => {
    vi.clearAllMocks();
    getCustomerDeliveryTracking.mockReset();
    getCustomerDeliveryTracking.mockReturnValue(of(tracking));
    await TestBed.configureTestingModule({
      imports: [OrderDetail],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              queryParamMap: { get: () => null },
              paramMap: { get: () => '5' },
            },
          },
        },
        { provide: AuthService, useValue: { user: () => ({ id: 1, role: 'customer' }) } },
        {
          provide: OrderService,
          useValue: { getOrder: vi.fn(() => of(order)), downloadInvoice: vi.fn(() => of(undefined)) },
        },
        {
          provide: DeliveryService,
          useValue: { getCustomerDeliveryTracking },
        },
      ],
    }).compileComponents();
  });

  it('shows delivery details and GPS coordinates when position is available', () => {
    const fixture = TestBed.createComponent(OrderDetail);
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('Suivre ma livraison');
    expect(element.textContent).toContain('Livreur Test');
    expect(element.textContent).toContain('14.692800, -17.446700');
    expect(element.textContent).toContain('Voir sur OpenStreetMap');
    expect(element.textContent).toContain('Précision : 12 m');
    expect(element.querySelector('.leaflet-container')).toBeNull();

    fixture.destroy();
  });

  it('reports that no GPS position is available when tracking is active but no position yet', () => {
    const now = new Date().toISOString();
    const trackingNoPosition: CustomerDeliveryTracking = {
      ...tracking,
      delivery: {
        ...tracking.delivery!,
        location_available: false,
        latitude: null,
        longitude: null,
        location_updated_at: now,
        location_is_stale: false,
      },
    };
    getCustomerDeliveryTracking.mockReturnValue(of(trackingNoPosition));

    const fixture = TestBed.createComponent(OrderDetail);
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('Le partage est actif, mais aucune position GPS n’est encore disponible.');
    expect(element.textContent).not.toContain('14.692800');
    expect(element.querySelector('.leaflet-container')).toBeNull();

    fixture.destroy();
  });

  it('reports stale position when available but old', () => {
    const trackingStale: CustomerDeliveryTracking = {
      ...tracking,
      delivery: { ...tracking.delivery!, location_is_stale: true },
    };
    getCustomerDeliveryTracking.mockReturnValue(of(trackingStale));

    const fixture = TestBed.createComponent(OrderDetail);
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('La dernière position est ancienne');
    expect(element.querySelector('.leaflet-container')).toBeNull();

    fixture.destroy();
  });

  it('does not start overlapping tracking requests and cancels the active request on destroy', () => {
    const request = new Subject<CustomerDeliveryTracking>();
    getCustomerDeliveryTracking.mockReturnValue(request);
    vi.useFakeTimers();

    try {
      const fixture = TestBed.createComponent(OrderDetail);
      fixture.detectChanges();
      expect(getCustomerDeliveryTracking).toHaveBeenCalledTimes(1);

      vi.advanceTimersByTime(60_000);
      expect(getCustomerDeliveryTracking).toHaveBeenCalledTimes(1);

      fixture.destroy();
      expect(request.observed).toBe(false);
    } finally {
      vi.useRealTimers();
    }
  });

  it('shows no driver when none assigned', () => {
    const trackingNoDelivery: CustomerDeliveryTracking = {
      order: { id: 5, number: 'GT-000005', status: 'confirmed' },
      delivery: null,
    };
    getCustomerDeliveryTracking.mockReturnValue(of(trackingNoDelivery));

    const fixture = TestBed.createComponent(OrderDetail);
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('Aucun livreur n’est encore affecté à cette commande.');
    expect(element.querySelector('.leaflet-container')).toBeNull();

    fixture.destroy();
  });
});
