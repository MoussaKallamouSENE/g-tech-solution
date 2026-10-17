import { Injectable, computed, signal } from '@angular/core';
import { Product } from '../models/catalogue';

export interface CartItem {
  id: number;
  name: string;
  brand: string | null;
  price: number;
  stock: number;
  quantity: number;
  image_url?: string | null;
}

const STORAGE_KEY = 'gtech_cart';
const MAX_ORDER_QUANTITY = 10;

@Injectable({ providedIn: 'root' })
export class CartService {
  private readonly _items = signal<CartItem[]>(this.load());

  readonly items = this._items.asReadonly();
  readonly count = computed(() => this._items().reduce((sum, item) => sum + item.quantity, 0));
  readonly total = computed(() =>
    this._items().reduce((sum, item) => sum + item.price * item.quantity, 0),
  );

  add(product: Product, quantity = 1): void {
    if (product.stock <= 0 || !Number.isFinite(quantity)) {
      return;
    }
    const addedQuantity = Math.trunc(quantity);
    if (addedQuantity < 1) {
      return;
    }

    const items = [...this._items()];
    const existing = items.find((item) => item.id === product.id);
    const maxQuantity = Math.min(product.stock, MAX_ORDER_QUANTITY);

    if (existing) {
      this.commit(
        items.map((item) =>
          item.id === product.id
            ? {
                ...item,
                quantity: Math.min(item.quantity + addedQuantity, maxQuantity),
                stock: product.stock,
                price: product.price,
                image_url: product.image_url ?? item.image_url,
              }
            : item,
        ),
      );
    } else {
      items.push({
        id: product.id,
        name: product.name,
        brand: product.brand,
        price: product.price,
        stock: product.stock,
        quantity: Math.min(addedQuantity, maxQuantity),
        image_url: product.image_url,
      });
      this.commit(items);
    }
  }

  setQuantity(id: number, quantity: number): void {
    if (!Number.isFinite(quantity)) {
      return;
    }
    const normalizedQuantity = Math.trunc(quantity);
    if (normalizedQuantity < 1) {
      this.remove(id);
      return;
    }

    const target = this._items().find((item) => item.id === id);
    if (!target) {
      return;
    }

    const maxQuantity = Math.min(target.stock, MAX_ORDER_QUANTITY);
    if (maxQuantity < 1) {
      this.remove(id);
      return;
    }

    this.commit(
      this._items().map((item) =>
        item.id === id ? { ...item, quantity: Math.min(normalizedQuantity, maxQuantity) } : item,
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

  maxQuantity(item: CartItem): number {
    return Math.min(item.stock, MAX_ORDER_QUANTITY);
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
      return data
        .filter(
          (item) =>
            item &&
            typeof item.id === 'number' &&
            typeof item.price === 'number' &&
            Number.isInteger(item.stock) &&
            item.stock > 0 &&
            Number.isInteger(item.quantity) &&
            item.quantity > 0,
        )
        .map((item) => ({
          ...item,
          quantity: Math.min(item.quantity, item.stock, MAX_ORDER_QUANTITY),
        }));
    } catch {
      return [];
    }
  }
}