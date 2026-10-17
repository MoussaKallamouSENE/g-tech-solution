import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { Router, RouterLink } from '@angular/router';
import { AuthService, errorMessage } from '../../services/auth.service';
import { AuthBackgroundComponent } from '../../components/auth-background/auth-background';

@Component({
  selector: 'app-register',
  imports: [RouterLink, AuthBackgroundComponent],
  templateUrl: './register.html',
})
export class Register {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly error = signal('');
  protected readonly loading = signal(false);

  protected submit(
    event: Event,
    name: string,
    email: string,
    phone: string,
    password: string,
    confirm: string,
  ): void {
    event.preventDefault();
    this.error.set('');

    if (password !== confirm) {
      this.error.set('Les mots de passe ne correspondent pas.');
      return;
    }

    this.loading.set(true);
    this.auth
      .register({ name: name.trim(), email: email.trim(), phone: phone.trim(), password })
      .subscribe({
        next: () => this.router.navigateByUrl('/'),
        error: (err: HttpErrorResponse) => {
          this.error.set(errorMessage(err));
          this.loading.set(false);
        },
      });
  }
}