import { Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { CartService } from './services/cart.service';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './app.html',
})
export class App {
  private readonly router = inject(Router);
  protected readonly cart = inject(CartService);
  protected readonly auth = inject(AuthService);
  protected readonly menuOpen = signal(false);
  protected readonly currentYear = new Date().getFullYear();

  protected readonly isAdmin = toSignal(
    this.router.events.pipe(
      filter((event) => event instanceof NavigationEnd),
      map(() => {
        this.menuOpen.set(false);
        return this.router.url.startsWith('/admin');
      }),
      startWith(this.router.url.startsWith('/admin')),
    ),
    { initialValue: false },
  );

  protected onSearch(event: Event, term: string): void {
    event.preventDefault();
    const search = term.trim();
    this.menuOpen.set(false);
    this.router.navigate(['/'], {
      queryParams: { search: search || null },
      fragment: 'produits',
    });
  }

  protected logout(): void {
    this.menuOpen.set(false);
    this.auth.logout().subscribe({
      complete: () => this.router.navigateByUrl('/'),
      error: () => this.router.navigateByUrl('/'),
    });
  }
}
