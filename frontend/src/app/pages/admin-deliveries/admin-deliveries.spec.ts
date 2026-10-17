import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { AdminDeliveriesPage } from './admin-deliveries';
import { Delivery, DeliveryService } from '../../services/delivery.service';

describe('AdminDeliveriesPage', () => {
  const activeDelivery = (latitude: number, longitude: number): Delivery => ({
    id: 17,
    status: 'in_delivery',
    assigned_at: '2026-10-15T10:00:00.000Z',
    picked_up_at: '2026-10-15T10:30:00.000Z',
    delivered_at: null,
    failure_reason: null,
    driver: { id: 3, name: 'Livreur Test' },
    order: {
      id: 5,
      number: 'GT-000005',
      status: 'out_for_delivery',
      customer_name: 'Client Test',
      customer_phone: '+221770000000',
      customer_address: 'Dakar, Plateau',
    },
    location_sharing_active: true,
    location_available: true,
    location_is_stale: false,
    latitude,
    longitude,
    location_accuracy: 12,
    location_updated_at: '2026-10-15T10:35:00.000Z',
    status_history: [],
  });

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AdminDeliveriesPage],
      providers: [
        provideRouter([]),
        {
          provide: DeliveryService,
          useValue: {
            getAdminDeliveries: vi.fn(() => of({
              current_page: 1,
              data: [],
              last_page: 1,
              per_page: 50,
              total: 0,
            })),
            getPendingOrders: vi.fn(() => of([])),
            getDrivers: vi.fn(() => of([])),
          },
        },
      ],
    }).compileComponents();
  });

  it('displays pending orders when none exist', async () => {
    const fixture = TestBed.createComponent(AdminDeliveriesPage);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('Aucune livraison affectée.');

    fixture.destroy();
  });

  it('displays GPS coordinates for active deliveries', async () => {
    const deliveries = [activeDelivery(14.6928, -17.4467)];
    const fixture = TestBed.createComponent(AdminDeliveriesPage);
    const service = TestBed.inject(DeliveryService);
    vi.spyOn(service, 'getAdminDeliveries').mockReturnValue(of({
      current_page: 1,
      data: deliveries,
      last_page: 1,
      per_page: 50,
      total: 1,
    }));
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('14.692800');
    expect(element.textContent).toContain('-17.446700');
    expect(element.textContent).toContain('Précision : 12 m');

    fixture.destroy();
  });
});
