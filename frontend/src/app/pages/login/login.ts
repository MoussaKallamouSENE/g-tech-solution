import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService, errorMessage } from '../../services/auth.service';
import { AuthBackgroundComponent } from '../../components/auth-background/auth-background';

@Component({
  selector: 'app-login',
  imports: [RouterLink, AuthBackgroundComponent],
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
        const user = this.auth.user();
        const redirect = this.route.snapshot.queryParamMap.get('redirect');
        const defaultRoute = user?.role === 'admin' ? '/admin' : user?.role === 'driver' ? '/livreur' : '/';
        this.router.navigateByUrl(this.isAllowedRedirect(user?.role, redirect) ? redirect! : defaultRoute);
      },
      error: (err: HttpErrorResponse) => {
        this.error.set(errorMessage(err));
        this.loading.set(false);
      },
    });
  }

  private isAllowedRedirect(role: string | undefined, redirect: string | null): redirect is string {
    if (!redirect?.startsWith('/') || redirect.startsWith('//')) return false;

    if (role === 'admin') return redirect === '/admin' || redirect.startsWith('/admin/');
    if (role === 'driver') return redirect === '/livreur' || redirect.startsWith('/livreur/');
    return !/^\/(admin|livreur)(\/|$|[?#])/.test(redirect);
  }
}