import { Injectable, computed, signal } from '@angular/core';
import { Product } from '../models/catalogue';

export interface CartItem {
  id: number;
  name: string;
  brand: string | null;
  price: number;
  stock: number;
  quantity: number;
}

const STORAGE_KEY = 'gtech_cart';

@Injectable({ providedIn: 'root' })
export class CartService {
  private readonly _items = signal<CartItem[]>(this.load());

  readonly items = this._items.asReadonly();
  readonly count = computed(() => this._items().reduce((sum, item) => sum + item.quantity, 0));
  readonly total = computed(() =>
    this._items().reduce((sum, item) => sum + item.price * item.quantity, 0),
  );

  add(product: Product, quantity = 1): void {
    if (product.stock <= 0) {
      return;
    }
    const items = [...this._items()];
    const existing = items.find((item) => item.id === product.id);

    if (existing) {
      existing.quantity = Math.min(existing.quantity + quantity, product.stock);
      existing.stock = product.stock;
      existing.price = product.price;
    } else {
      items.push({
        id: product.id,
        name: product.name,
        brand: product.brand,
        price: product.price,
        stock: product.stock,
        quantity: Math.min(quantity, product.stock),
      });
    }
    this.commit(items);
  }

  setQuantity(id: number, quantity: number): void {
    if (quantity < 1) {
      this.remove(id);
      return;
    }
    this.commit(
      this._items().map((item) =>
        item.id === id ? { ...item, quantity: Math.min(quantity, item.stock) } : item,
      ),
    );
  }

  remove(id: number): void {
    this.commit(this._items().filter((item) => item.id !== id));
  }

  clear(): void {
    this.commit([]);
  }

  quantityOf(id: number): number {
    return this._items().find((item) => item.id === id)?.quantity ?? 0;
  }

  private commit(items: CartItem[]): void {
    this._items.set(items);
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    } catch {
      // Stockage indisponible : le panier reste en mémoire pour cette visite.
    }
  }

  private load(): CartItem[] {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const data = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(data)) {
        return [];
      }
      return data.filter(
        (item) =>
          item &&
          typeof item.id === 'number' &&
          typeof item.price === 'number' &&
          typeof item.stock === 'number' &&
          typeof item.quantity === 'number' &&
          item.quantity > 0,
      );
    } catch {
      return [];
    }
  }
}