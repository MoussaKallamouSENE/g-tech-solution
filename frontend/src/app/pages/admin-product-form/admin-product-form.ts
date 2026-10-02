import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AdminService, ProductPayload } from '../../services/admin.service';
import { CatalogueService } from '../../services/catalogue.service';
import { errorMessage } from '../../services/auth.service';
import { Category, Product } from '../../models/catalogue';

@Component({
  selector: 'app-admin-product-form',
  imports: [RouterLink],
  templateUrl: './admin-product-form.html',
})
export class AdminProductForm {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly admin = inject(AdminService);
  private readonly catalogue = inject(CatalogueService);

  protected readonly id = Number(this.route.snapshot.paramMap.get('id')) || null;
  protected readonly product = signal<Product | null>(null);
  protected readonly categories = signal<Category[]>([]);
  protected readonly ready = signal(false);
  protected readonly denied = signal(false);
  protected readonly saving = signal(false);
  protected readonly error = signal('');

  protected readonly states = ['neuf', 'occasion', 'reconditionné'];

  constructor() {
    this.catalogue.getCategories().subscribe({ next: (list) => this.categories.set(list) });

    if (this.id) {
      this.admin.getProduct(this.id).subscribe({
        next: (product) => {
          this.product.set(product);
          this.ready.set(true);
        },
        error: (err: HttpErrorResponse) => {
          if (err.status === 401 || err.status === 403) {
            this.denied.set(true);
          }
          this.ready.set(true);
        },
      });
    } else {
      this.ready.set(true);
    }
  }

  protected submit(event: Event, form: HTMLFormElement): void {
    event.preventDefault();
    this.error.set('');

    const data = new FormData(form);
    const text = (key: string): string => String(data.get(key) ?? '').trim();
    const optional = (key: string): string | null => text(key) || null;

    const payload: ProductPayload = {
      category_id: Number(text('category_id')),
      name: text('name'),
      reference: text('reference'),
      brand: optional('brand'),
      description: optional('description'),
      price: Number(text('price')),
      stock: Number(text('stock')),
      stock_alert_threshold: Number(text('stock_alert_threshold')),
      processor: optional('processor'),
      ram: optional('ram'),
      storage: optional('storage'),
      graphics_card: optional('graphics_card'),
      screen: optional('screen'),
      state: text('state'),
      is_active: data.has('is_active'),
    };

    this.saving.set(true);
    const request = this.id ? this.admin.updateProduct(this.id, payload) : this.admin.createProduct(payload);
    request.subscribe({
      next: () => this.router.navigateByUrl('/admin/produits'),
      error: (err: HttpErrorResponse) => {
        this.error.set(errorMessage(err));
        this.saving.set(false);
      },
    });
  }
}