import { Routes } from '@angular/router';
import { Home } from './pages/home/home';
import { ProductDetail } from './pages/product-detail/product-detail';
import { Cart } from './pages/cart/cart';
import { Login } from './pages/login/login';
import { Register } from './pages/register/register';
import { Checkout } from './pages/checkout/checkout';
import { Orders } from './pages/orders/orders';
import { OrderDetail } from './pages/order-detail/order-detail';

export const routes: Routes = [
  { path: '', component: Home },
  { path: 'produits/:id', component: ProductDetail },
  { path: 'panier', component: Cart },
  { path: 'commande', component: Checkout },
  { path: 'commandes', component: Orders },
  { path: 'commandes/:id', component: OrderDetail },
  { path: 'connexion', component: Login },
  { path: 'inscription', component: Register },
  { path: '**', redirectTo: '' },
];