import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, of, switchMap, tap } from 'rxjs';
import { CatalogueService } from '../../services/catalogue.service';
import { Paginated, Product } from '../../models/catalogue';

@Component({
  selector: 'app-home',
  imports: [RouterLink],
  templateUrl: './home.html',
})
export class Home {
  private readonly route = inject(ActivatedRoute);
  private readonly catalogue = inject(CatalogueService);

  protected readonly products = signal<Product[]>([]);
  protected readonly total = signal(0);
  protected readonly search = signal('');
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  protected readonly title = computed(() =>
    this.search() ? `Résultats pour « ${this.search()} »` : 'Nos produits',
  );

  protected readonly countLabel = computed(() =>
    this.search()
      ? `${this.total()} produit(s) trouvé(s)`
      : `${this.total()} produit(s) disponible(s)`,
  );

  protected readonly emptyMessage = computed(() =>
    this.search()
      ? `Aucun produit ne correspond à « ${this.search()} ». Essayez un autre mot.`
      : 'Aucun produit disponible pour le moment. Revenez bientôt.',
  );

  constructor() {
    this.route.queryParamMap
      .pipe(
        tap((params) => {
          this.search.set(params.get('search') ?? '');
          this.loading.set(true);
          this.error.set(false);
        }),
        switchMap((params) =>
          this.catalogue.getProducts({ search: params.get('search') ?? '' }).pipe(
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

  protected formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }
}