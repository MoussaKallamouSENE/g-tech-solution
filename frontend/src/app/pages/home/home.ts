import { Component, computed, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, ParamMap, Router, RouterLink } from '@angular/router';
import { catchError, forkJoin, of, switchMap, tap } from 'rxjs';
import { CatalogueService, ProductFilters } from '../../services/catalogue.service';
import { Category, Paginated, Product } from '../../models/catalogue';
import { CartService } from '../../services/cart.service';
import { errorMessage as getErrorMessage } from '../../services/auth.service';
import { ProductModal } from '../../components/product-modal/product-modal';

@Component({
  selector: 'app-home',
  imports: [RouterLink, ProductModal],
  templateUrl: './home.html',
})
export class Home {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly catalogue = inject(CatalogueService);
  private readonly cart = inject(CartService);

  protected readonly products = signal<Product[]>([]);
  protected readonly total = signal(0);
  protected readonly currentPage = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly pageSize = signal(12);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);
  protected readonly apiError = signal('');
  protected readonly metadataError = signal(false);
  protected readonly failedImages = signal<number[]>([]);
  protected readonly addedProductId = signal<number | null>(null);
  protected readonly detailProduct = signal<Product | null>(null);

  protected readonly categories = signal<Category[]>([]);
  protected readonly brands = signal<string[]>([]);
  protected readonly filters = signal<ProductFilters>({});
  protected readonly filtersOpen = signal(false);

  protected readonly states = ['neuf', 'occasion', 'reconditionné'];
  protected readonly sorts = [
    { value: '', label: 'Plus récents' },
    { value: 'price_asc', label: 'Prix croissant' },
    { value: 'price_desc', label: 'Prix décroissant' },
    { value: 'name', label: 'Nom A-Z' },
  ];

  protected readonly search = computed(() => this.filters().search ?? '');
  protected readonly visiblePages = computed(() => {
    const last = this.lastPage();
    const start = Math.max(1, Math.min(this.currentPage() - 2, last - 4));
    return Array.from({ length: Math.min(5, last) }, (_, index) => start + index);
  });

  protected readonly activeCount = computed(() => {
    const f = this.filters();
    return [f.category, f.brand, f.state, f.min_price, f.max_price, f.in_stock].filter(
      (v) => v !== undefined && v !== null && v !== '' && v !== false,
    ).length;
  });

  protected readonly hasCriteria = computed(() => !!this.search() || this.activeCount() > 0);

  protected readonly title = computed(() =>
    this.search()
      ? `Résultats pour « ${this.search()} »`
      : this.activeCount() > 0
        ? 'Résultats filtrés'
        : 'Tous nos produits informatiques',
  );

  protected readonly countLabel = computed(() => `${this.total()} résultat(s)`);

  protected readonly resultRange = computed(() => {
    const count = this.products().length;
    if (count === 0) return '';
    const first = (this.currentPage() - 1) * this.pageSize() + 1;
    return `Affichage ${first}–${first + count - 1} sur ${this.total()}`;
  });

  protected readonly emptyMessage = computed(() =>
    this.hasCriteria()
      ? 'Aucun produit ne correspond à vos critères. Modifiez ou réinitialisez les filtres.'
      : 'Aucun produit disponible pour le moment. Revenez bientôt.',
  );

  constructor() {
    this.loadMetadata();

    this.route.queryParamMap
      .pipe(
        tap((params) => {
          this.filters.set(this.parseFilters(params));
          this.loading.set(true);
          this.error.set(false);
        }),
        switchMap(() =>
          this.catalogue.getProducts(this.filters()).pipe(
            catchError((error: HttpErrorResponse) => {
              this.error.set(true);
              this.apiError.set(getErrorMessage(error));
              return of(null as Paginated<Product> | null);
            }),
          ),
        ),
      )
      .subscribe((page) => {
        if (page) {
          this.products.set(page.data);
          this.total.set(page.total);
          this.currentPage.set(page.current_page);
          this.lastPage.set(page.last_page);
          this.pageSize.set(page.per_page);
        }
        this.loading.set(false);
      });
  }

  private parseFilters(params: ParamMap): ProductFilters {
    const num = (value: string | null): number | null =>
      value !== null && value !== '' && !isNaN(Number(value)) ? Number(value) : null;

    return {
      search: params.get('search') ?? '',
      category: params.get('category') ?? '',
      brand: params.get('brand') ?? '',
      state: params.get('state') ?? '',
      min_price: num(params.get('min_price')),
      max_price: num(params.get('max_price')),
      in_stock: params.get('in_stock') === '1',
      sort: params.get('sort') ?? '',
      page: Math.max(1, Number(params.get('page')) || 1),
    };
  }

  private update(changes: Record<string, string | null>): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: changes,
      queryParamsHandling: 'merge',
    });
  }

  protected setFilter(key: string, value: string): void {
    this.addedProductId.set(null);
    this.update({ [key]: value.trim() || null, page: '1' });
  }

  protected submitSearch(event: Event, value: string): void {
    event.preventDefault();
    this.setFilter('search', value);
  }

  protected setPrice(min: string, max: string): void {
    this.update({
      min_price: min.trim() || null,
      max_price: max.trim() || null,
      page: '1',
    });
  }

  protected toggleStock(checked: boolean): void {
    this.update({ in_stock: checked ? '1' : null, page: '1' });
  }

  protected goToPage(page: number): void {
    if (page >= 1 && page <= this.lastPage()) {
      this.update({ page: String(page) });
    }
  }

  protected reset(): void {
    this.router.navigate([], { relativeTo: this.route });
  }

  protected retry(): void {
    this.loading.set(true);
    this.error.set(false);
    this.apiError.set('');
    this.catalogue.getProducts(this.filters()).pipe(
      catchError((error: HttpErrorResponse) => {
        this.error.set(true);
        this.apiError.set(getErrorMessage(error));
        return of(null);
      }),
    ).subscribe((page) => {
      if (page) {
        this.products.set(page.data);
        this.total.set(page.total);
        this.currentPage.set(page.current_page);
        this.lastPage.set(page.last_page);
        this.pageSize.set(page.per_page);
      }
      this.loading.set(false);
    });
  }

  protected retryMetadata(): void {
    this.loadMetadata();
  }

  private loadMetadata(): void {
    this.metadataError.set(false);
    forkJoin({
      categories: this.catalogue.getCategories().pipe(catchError(() => {
        this.metadataError.set(true);
        return of(null);
      })),
      brands: this.catalogue.getBrands().pipe(catchError(() => {
        this.metadataError.set(true);
        return of(null);
      })),
    }).subscribe({
      next: ({ categories, brands }) => {
        if (categories) this.categories.set(categories);
        if (brands) this.brands.set(brands);
      },
    });
  }

  protected imageFailed(id: number): void {
    this.failedImages.update((ids) => ids.includes(id) ? ids : [...ids, id]);
  }

  protected hasImageFailed(id: number): boolean {
    return this.failedImages().includes(id);
  }

  protected addToCart(event: Event, product: Product): void {
    event.preventDefault();
    event.stopPropagation();
    if (product.stock < 1) return;
    this.cart.add(product);
    this.addedProductId.set(product.id);
    window.setTimeout(() => {
      if (this.addedProductId() === product.id) this.addedProductId.set(null);
    }, 2500);
  }

  /**
   * Ouvre la fiche produit en surimpression du catalogue.
   */
  protected openDetails(product: Product): void {
    this.addedProductId.set(null);
    this.detailProduct.set(product);
  }

  /**
   * Referme la fiche produit : le catalogue retrouve sa position de défilement.
   */
  protected closeDetails(): void {
    this.detailProduct.set(null);
  }

  protected optionClass(active: boolean): string {
    const base =
      'flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm focus-visible:outline-2 focus-visible:outline-cobalt ';
    return base + (active ? 'bg-cobalt/10 font-semibold text-cobalt' : 'text-ink/80 hover:bg-mist');
  }

  protected chipClass(active: boolean): string {
    const base =
      'shrink-0 rounded-full border px-4 py-2 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-cobalt ';
    return base + (active ? 'border-cobalt bg-cobalt text-white' : 'border-line bg-white text-ink hover:border-cobalt');
  }

  protected formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }
}