import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_BASE_URL } from '../config/api';
import { Category, Paginated, Product } from '../models/catalogue';

export interface ProductFilters {
  search?: string;
  category?: string;
  brand?: string;
  state?: string;
  min_price?: number | null;
  max_price?: number | null;
  in_stock?: boolean;
  sort?: string;
  page?: number;
}

@Injectable({ providedIn: 'root' })
export class CatalogueService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = API_BASE_URL;

  getProducts(filters: ProductFilters = {}): Observable<Paginated<Product>> {
    let params = new HttpParams();
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== null && value !== '' && value !== false) {
        params = params.set(key, String(value));
      }
    }
    return this.http.get<Paginated<Product>>(`${this.apiUrl}/products`, { params });
  }

  getProduct(id: number): Observable<Product> {
    return this.http.get<Product>(`${this.apiUrl}/products/${id}`);
  }

  getCategories(): Observable<Category[]> {
    return this.http.get<Category[]>(`${this.apiUrl}/categories`);
  }

  getBrands(): Observable<string[]> {
    return this.http.get<string[]>(`${this.apiUrl}/brands`);
  }
}