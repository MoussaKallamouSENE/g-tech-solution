import { Routes } from '@angular/router';
import { Home } from './pages/home/home';
import { ProductDetail } from './pages/product-detail/product-detail';
import { Cart } from './pages/cart/cart';
import { Login } from './pages/login/login';
import { Register } from './pages/register/register';
import { Checkout } from './pages/checkout/checkout';
import { Orders } from './pages/orders/orders';
import { OrderDetail } from './pages/order-detail/order-detail';
import { Services } from './pages/services/services';
import { Admin } from './pages/admin/admin';
import { AdminLayout } from './pages/admin-layout/admin-layout';
import { AdminDashboard } from './pages/admin-dashboard/admin-dashboard';
import { AdminOrderPage } from './pages/admin-order/admin-order';
import { AdminProducts } from './pages/admin-products/admin-products';
import { AdminProductForm } from './pages/admin-product-form/admin-product-form';
import { AdminMaintenance } from './pages/admin-maintenance/admin-maintenance';
import { AdminCustomers } from './pages/admin-customers/admin-customers';
import { AdminSettings } from './pages/admin-settings/admin-settings';

export const routes: Routes = [
  { path: '', component: Home },
  { path: 'produits/:id', component: ProductDetail },
  { path: 'panier', component: Cart },
  { path: 'commande', component: Checkout },
  { path: 'commandes', component: Orders },
  { path: 'commandes/:id', component: OrderDetail },
  { path: 'services', component: Services },
  { path: 'connexion', component: Login },
  { path: 'inscription', component: Register },
  {
    path: 'admin',
    component: AdminLayout,
    children: [
      { path: '', component: AdminDashboard },
      { path: 'commandes', component: Admin },
      { path: 'commandes/:id', component: AdminOrderPage },
      { path: 'produits', component: AdminProducts },
      { path: 'produits/nouveau', component: AdminProductForm },
      { path: 'produits/:id/modifier', component: AdminProductForm },
      { path: 'clients', component: AdminCustomers },
      { path: 'maintenance', component: AdminMaintenance },
      { path: 'boutique', component: AdminSettings },
    ],
  },
  { path: '**', redirectTo: '' },
];