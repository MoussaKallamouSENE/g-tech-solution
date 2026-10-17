import { Injectable, computed, signal } from '@angular/core';
import { Product } from '../models/catalogue';

const STORAGE_KEY = 'gtech_wishlist';

@Injectable({ providedIn: 'root' })
export class WishlistService {
  private readonly _items = signal<number[]>(this.load());

  readonly items = computed(() => this._items());
  readonly count = computed(() => this._items().length);

  constructor() {
    // Détecter les changements d'onglet pour recharger
    window.addEventListener('storage', () => {
      this._items.set(this.load());
    });
  }

  private load(): number[] {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const data = raw ? JSON.parse(raw) : [];
      return Array.isArray(data) ? data : [];
    } catch {
      return [];
    }
  }

  private commit(ids: number[]): void {
    this._items.set(ids);
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    } catch {
      // Stockage indisponible : le panier reste en mémoire pour cette visite.
    }
  }

  add(productId: number): void {
    if (this._items().includes(productId)) return;
    this.commit([...this._items(), productId]);
  }

  remove(productId: number): void {
    this.commit(this._items().filter((id) => id !== productId));
  }

  toggle(productId: number): void {
    if (this._items().includes(productId)) {
      this.remove(productId);
    } else {
      this.add(productId);
    }
  }

  isInWishlist(productId: number): boolean {
    return this._items().includes(productId);
  }
}
