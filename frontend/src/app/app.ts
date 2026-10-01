import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink],
  templateUrl: './app.html',
})
export class App {
  private readonly router = inject(Router);

  protected onSearch(event: Event, term: string): void {
    event.preventDefault();
    const search = term.trim();
    this.router.navigate(['/'], {
      queryParams: { search: search || null },
      fragment: 'produits',
    });
  }
}