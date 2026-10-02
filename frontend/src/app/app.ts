import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterOutlet } from '@angular/router';
import { CartService } from './services/cart.service';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink],
  templateUrl: './app.html',
})
export class App {
  private readonly router = inject(Router);
  protected readonly cart = inject(CartService);
  protected readonly auth = inject(AuthService);

  protected onSearch(event: Event, term: string): void {
    event.preventDefault();
    const search = term.trim();
    this.router.navigate(['/'], {
      queryParams: { search: search || null },
      fragment: 'produits',
    });
  }

  protected logout(): void {
    this.auth.logout().subscribe({
      complete: () => this.router.navigateByUrl('/'),
      error: () => this.router.navigateByUrl('/'),
    });
  }
}