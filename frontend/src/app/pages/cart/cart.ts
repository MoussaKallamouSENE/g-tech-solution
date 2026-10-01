import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartService } from '../../services/cart.service';

@Component({
  selector: 'app-cart',
  imports: [RouterLink],
  templateUrl: './cart.html',
})
export class Cart {
  protected readonly cart = inject(CartService);

  protected formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }
}