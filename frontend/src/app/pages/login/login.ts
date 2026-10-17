import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService, errorMessage } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  imports: [RouterLink],
  templateUrl: './login.html',
})
export class Login {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly error = signal('');
  protected readonly loading = signal(false);

  constructor() {
    if (this.route.snapshot.queryParamMap.get('reason') === 'session-check-failed') {
      this.error.set('Impossible de vérifier votre session pour le moment. Vérifiez que le serveur est disponible, puis réessayez.');
    }
  }

  protected submit(event: Event, email: string, password: string): void {
    event.preventDefault();
    this.error.set('');
    this.loading.set(true);
    this.auth.login({ email: email.trim(), password }).subscribe({
      next: () => {
        const redirect = this.route.snapshot.queryParamMap.get('redirect') ?? '/';
        this.router.navigateByUrl(redirect);
      },
      error: (err: HttpErrorResponse) => {
        this.error.set(errorMessage(err));
        this.loading.set(false);
      },
    });
  }
}