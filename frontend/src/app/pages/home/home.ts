import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, ParamMap, Router, RouterLink } from '@angular/router';
import { catchError, of, switchMap, tap } from 'rxjs';
import { CatalogueService, ProductFilters } from '../../services/catalogue.service';
import { Category, Paginated, Product } from '../../models/catalogue';

@Component({
  selector: 'app-home',
  imports: [RouterLink],
  templateUrl: './home.html',
})
export class Home {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly catalogue = inject(CatalogueService);

  protected readonly products = signal<Product[]>([]);
  protected readonly total = signal(0);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

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
        : 'Nos produits',
  );

  protected readonly countLabel = computed(() =>
    this.hasCriteria()
      ? `${this.total()} produit(s) trouvé(s)`
      : `${this.total()} produit(s) disponible(s)`,
  );

  protected readonly emptyMessage = computed(() =>
    this.hasCriteria()
      ? 'Aucun produit ne correspond à vos critères. Modifiez ou réinitialisez les filtres.'
      : 'Aucun produit disponible pour le moment. Revenez bientôt.',
  );

  constructor() {
    this.catalogue.getCategories().subscribe({
      next: (list) => this.categories.set(list),
      error: () => {},
    });
    this.catalogue.getBrands().subscribe({
      next: (list) => this.brands.set(list),
      error: () => {},
    });

    this.route.queryParamMap
      .pipe(
        tap((params) => {
          this.filters.set(this.parseFilters(params));
          this.loading.set(true);
          this.error.set(false);
        }),
        switchMap(() =>
          this.catalogue.getProducts(this.filters()).pipe(
            catchError(() => {
              this.error.set(true);
              return of(null as Paginated<Product> | null);
            }),
          ),
        ),
      )
      .subscribe((page) => {
        if (page) {
          this.products.set(page.data);
          this.total.set(page.total);
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
    this.update({ [key]: value.trim() || null });
  }

  protected setPrice(min: string, max: string): void {
    this.update({ min_price: min.trim() || null, max_price: max.trim() || null });
  }

  protected toggleStock(checked: boolean): void {
    this.update({ in_stock: checked ? '1' : null });
  }

  protected reset(): void {
    this.router.navigate([], { relativeTo: this.route });
  }

  protected optionClass(active: boolean): string {
    const base =
      'flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-cobalt ';
    return base + (active ? 'bg-cobalt/10 font-semibold text-cobalt' : 'text-ink/80 hover:bg-mist');
  }

  protected formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }
}