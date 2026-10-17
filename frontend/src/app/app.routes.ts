import { Routes } from '@angular/router';
import { adminGuard } from './guards/admin.guard';

export const routes: Routes = [
  { path: '', loadComponent: () => import('./pages/home/home').then((m) => m.Home) },
  {
    path: 'produits/:id',
    loadComponent: () => import('./pages/product-detail/product-detail').then((m) => m.ProductDetail),
  },
  { path: 'panier', loadComponent: () => import('./pages/cart/cart').then((m) => m.Cart) },
  { path: 'commande', loadComponent: () => import('./pages/checkout/checkout').then((m) => m.Checkout) },
  { path: 'commandes', loadComponent: () => import('./pages/orders/orders').then((m) => m.Orders) },
  {
    path: 'commandes/:id',
    loadComponent: () => import('./pages/order-detail/order-detail').then((m) => m.OrderDetail),
  },
  { path: 'services', loadComponent: () => import('./pages/services/services').then((m) => m.Services) },
  { path: 'contact', loadComponent: () => import('./pages/contact/contact').then((m) => m.Contact) },
  { path: 'connexion', loadComponent: () => import('./pages/login/login').then((m) => m.Login) },
  { path: 'inscription', loadComponent: () => import('./pages/register/register').then((m) => m.Register) },
  {
    path: 'admin',
    canActivate: [adminGuard],
    loadComponent: () => import('./pages/admin-layout/admin-layout').then((m) => m.AdminLayout),
    children: [
      {
        path: '',
        loadComponent: () => import('./pages/admin-dashboard/admin-dashboard').then((m) => m.AdminDashboard),
      },
      { path: 'commandes', loadComponent: () => import('./pages/admin/admin').then((m) => m.Admin) },
      {
        path: 'commandes/:id',
        loadComponent: () => import('./pages/admin-order/admin-order').then((m) => m.AdminOrderPage),
      },
      {
        path: 'produits',
        loadComponent: () => import('./pages/admin-products/admin-products').then((m) => m.AdminProducts),
      },
      {
        path: 'produits/nouveau',
        loadComponent: () => import('./pages/admin-product-form/admin-product-form').then((m) => m.AdminProductForm),
      },
      {
        path: 'produits/:id/modifier',
        loadComponent: () => import('./pages/admin-product-form/admin-product-form').then((m) => m.AdminProductForm),
      },
      {
        path: 'fournisseurs',
        loadComponent: () => import('./pages/admin-suppliers/admin-suppliers').then((m) => m.AdminSuppliers),
      },
      {
        path: 'clients',
        loadComponent: () => import('./pages/admin-customers/admin-customers').then((m) => m.AdminCustomers),
      },
      {
        path: 'maintenance',
        loadComponent: () => import('./pages/admin-maintenance/admin-maintenance').then((m) => m.AdminMaintenance),
      },
      {
        path: 'boutique',
        loadComponent: () => import('./pages/admin-settings/admin-settings').then((m) => m.AdminSettings),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];