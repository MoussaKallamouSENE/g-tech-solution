import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { Home } from './home';
import { CatalogueService } from '../../services/catalogue.service';
import { CartService } from '../../services/cart.service';
import { Product } from '../../models/catalogue';

describe('Home catalogue', () => {
  const cartAdds: Product[] = [];
  const products = [
    product({ id: 1, name: 'Portable avec image', image_url: '/uploads/portable.webp' }),
    product({ id: 2, name: 'Produit sans image', stock: 0, image_url: null }),
  ];

  beforeEach(async () => {
    cartAdds.length = 0;
    await TestBed.configureTestingModule({
      imports: [Home],
      providers: [
        provideRouter([]),
        {
          provide: CatalogueService,
          useValue: {
            getCategories: () => of([]),
            getBrands: () => of([]),
            getProducts: () => of({
              current_page: 1,
              data: products,
              last_page: 2,
              per_page: 12,
              total: 13,
            }),
          },
        },
        {
          provide: CartService,
          useValue: { add: (item: Product) => cartAdds.push(item) },
        },
      ],
    }).compileComponents();
  });

  it('renders real product details, a missing-image fallback and server pagination', async () => {
    const fixture = TestBed.createComponent(Home);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.querySelectorAll('article.product-card').length).toBe(2);
    expect(element.textContent).toContain('Portable avec image');
    expect(element.textContent).toContain('Produit sans image');
    expect(element.querySelector('img[alt="Portable avec image"]')).not.toBeNull();
    expect(element.querySelector('nav[aria-label="Pagination des produits"]')).not.toBeNull();
    expect(element.querySelector('button[aria-label="Page 2"]')).not.toBeNull();

    const image = element.querySelector('img')!;
    image.dispatchEvent(new Event('error'));
    fixture.detectChanges();
    expect(element.querySelector('img')).toBeNull();
    expect(element.textContent).toContain('G-Tech');
  });

  it('adds available products to the existing cart and disables out-of-stock products', async () => {
    const fixture = TestBed.createComponent(Home);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const buttons = Array.from((fixture.nativeElement as HTMLElement).querySelectorAll<HTMLButtonElement>('article button'))
      .filter((button) => button.textContent?.trim() === 'Ajouter' || button.textContent?.trim() === 'Indisponible');
    buttons[0].click();
    fixture.detectChanges();

    expect(cartAdds).toEqual([products[0]]);
    expect(buttons[1].disabled).toBe(true);
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Produit ajouté au panier');
  });

  it('changes the server page and resets to page one when sorting', async () => {
    const fixture = TestBed.createComponent(Home);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
    const router = TestBed.inject(Router);
    const navigate = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    const element = fixture.nativeElement as HTMLElement;

    element.querySelector<HTMLButtonElement>('button[aria-label="Page 2"]')!.click();
    expect(navigate.mock.calls[0][1]).toMatchObject({
      queryParams: { page: '2' },
      queryParamsHandling: 'merge',
    });

    const sort = element.querySelector<HTMLSelectElement>('select[aria-label="Trier les produits"]')!;
    sort.value = 'price_asc';
    sort.dispatchEvent(new Event('change'));
    expect(navigate.mock.calls[1][1]).toMatchObject({
      queryParams: { sort: 'price_asc', page: '1' },
      queryParamsHandling: 'merge',
    });
  });

  it('shows the real product details in a modal above the catalogue and closes it with the cross', async () => {
    const fixture = await renderHome();
    const element = fixture.nativeElement as HTMLElement;

    detailsButton(element).click();
    fixture.detectChanges();

    const dialog = element.querySelector('[role="dialog"]');
    expect(dialog).not.toBeNull();
    expect(dialog!.getAttribute('aria-modal')).toBe('true');
    expect(dialog!.textContent).toContain('Portable avec image');
    expect(dialog!.textContent).toContain('FCFA');
    expect(dialog!.textContent).toContain('Portable pour les tests');
    expect(dialog!.textContent).toContain('Intel Core i5');
    expect(dialog!.textContent).toContain('16 Go');
    expect(dialog!.querySelector('img[alt="Portable avec image"]')).not.toBeNull();
    expect(document.body.style.overflow).toBe('hidden');

    dialog!.querySelector<HTMLButtonElement>('button[aria-label="Fermer la fiche produit"]')!.click();
    fixture.detectChanges();

    expect(element.querySelector('[role="dialog"]')).toBeNull();
    expect(document.body.style.overflow).toBe('');
  });

  it('closes the product details with the Escape key', async () => {
    const fixture = await renderHome();
    const element = fixture.nativeElement as HTMLElement;

    detailsButton(element).click();
    fixture.detectChanges();
    expect(element.querySelector('[role="dialog"]')).not.toBeNull();

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    fixture.detectChanges();

    expect(element.querySelector('[role="dialog"]')).toBeNull();
    expect(document.body.style.overflow).toBe('');
  });

  it('closes the product details when the backdrop is clicked', async () => {
    const fixture = await renderHome();
    const element = fixture.nativeElement as HTMLElement;

    detailsButton(element).click();
    fixture.detectChanges();

    const backdrop = element.querySelector<HTMLElement>('[role="dialog"]')!.parentElement!;
    backdrop.click();
    fixture.detectChanges();

    expect(element.querySelector('[role="dialog"]')).toBeNull();
  });
});

async function renderHome() {
  const fixture = TestBed.createComponent(Home);
  fixture.detectChanges();
  await fixture.whenStable();
  fixture.detectChanges();
  return fixture;
}

function detailsButton(element: HTMLElement): HTMLButtonElement {
  return Array.from(element.querySelectorAll<HTMLButtonElement>('article button')).find(
    (button) => button.textContent?.trim() === 'Détails',
  )!;
}

function product(overrides: Partial<Product> = {}): Product {
  return {
    id: 1,
    category_id: 1,
    name: 'Ordinateur test',
    slug: 'ordinateur-test',
    reference: 'TEST-001',
    brand: 'G-Tech',
    description: 'Portable pour les tests',
    price: 150000,
    stock: 5,
    stock_alert_threshold: 1,
    processor: 'Intel Core i5',
    ram: '16 Go',
    storage: 'SSD 512 Go',
    graphics_card: null,
    screen: null,
    state: 'neuf',
    image: null,
    is_active: true,
    ...overrides,
  };
}
