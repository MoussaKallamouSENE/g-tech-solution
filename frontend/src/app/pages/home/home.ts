import { Component, inject, signal } from '@angular/core';
import { CatalogueService } from '../../services/catalogue.service';
import { Product } from '../../models/catalogue';

@Component({
  selector: 'app-home',
  templateUrl: './home.html',
})
export class Home {
  private readonly catalogue = inject(CatalogueService);

  protected readonly products = signal<Product[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  constructor() {
    this.catalogue.getProducts().subscribe({
      next: (page) => {
        this.products.set(page.data);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  protected formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }
}