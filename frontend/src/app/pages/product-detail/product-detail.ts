import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { CatalogueService } from '../../services/catalogue.service';
import { CartService } from '../../services/cart.service';
import { Product } from '../../models/catalogue';

@Component({
  selector: 'app-product-detail',
  imports: [RouterLink],
  templateUrl: './product-detail.html',
})
export class ProductDetail {
  private readonly route = inject(ActivatedRoute);
  private readonly catalogue = inject(CatalogueService);
  private readonly cart = inject(CartService);

  protected readonly product = signal<Product | null>(null);
  protected readonly loading = signal(true);
  protected readonly quantity = signal(1);
  protected readonly added = signal(false);

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

  protected increase(product: Product): void {
    if (this.quantity() < product.stock) {
      this.quantity.update((q) => q + 1);
    }
    this.added.set(false);
  }

  protected decrease(): void {
    this.quantity.update((q) => Math.max(1, q - 1));
    this.added.set(false);
  }

  protected addToCart(product: Product): void {
    this.cart.add(product, this.quantity());
    this.added.set(true);
  }

  protected formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }
}