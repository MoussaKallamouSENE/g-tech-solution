import { Routes } from '@angular/router';
import { Home } from './pages/home/home';
import { ProductDetail } from './pages/product-detail/product-detail';
import { Cart } from './pages/cart/cart';
import { Login } from './pages/login/login';
import { Register } from './pages/register/register';

export const routes: Routes = [
  { path: '', component: Home },
  { path: 'produits/:id', component: ProductDetail },
  { path: 'panier', component: Cart },
  { path: 'connexion', component: Login },
  { path: 'inscription', component: Register },
  { path: '**', redirectTo: '' },
];