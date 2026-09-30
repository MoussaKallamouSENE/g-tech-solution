import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { CatalogueService } from '../../services/catalogue.service';
import { Product } from '../../models/catalogue';

@Component({
  selector: 'app-product-detail',
  imports: [RouterLink],
  templateUrl: './product-detail.html',
})
export class ProductDetail {
  private readonly route = inject(ActivatedRoute);
  private readonly catalogue = inject(CatalogueService);

  protected readonly product = signal<Product | null>(null);
  protected readonly loading = signal(true);

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.catalogue.getProduct(id).subscribe({
      next: (product) => {
        this.product.set(product);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  protected formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }
}