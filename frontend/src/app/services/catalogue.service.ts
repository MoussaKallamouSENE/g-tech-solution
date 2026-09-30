import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Category, Paginated, Product } from '../models/catalogue';

@Injectable({ providedIn: 'root' })
export class CatalogueService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost/api';

  getProducts(page = 1): Observable<Paginated<Product>> {
    return this.http.get<Paginated<Product>>(`${this.apiUrl}/products`, {
      params: { page },
    });
  }

  getProduct(id: number): Observable<Product> {
    return this.http.get<Product>(`${this.apiUrl}/products/${id}`);
  }

  getCategories(): Observable<Category[]> {
    return this.http.get<Category[]>(`${this.apiUrl}/categories`);
  }
}