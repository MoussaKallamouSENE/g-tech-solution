import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { AuthService, errorMessage } from '../../services/auth.service';

@Component({
  selector: 'app-forgot-password',
  imports: [RouterLink, ReactiveFormsModule],
  templateUrl: './forgot-password.html',
})
export class ForgotPassword {
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);

  protected readonly form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
  });
  protected readonly sending = signal(false);
  protected readonly message = signal('');
  protected readonly error = signal('');

  protected submit(): void {
    if (this.form.invalid || this.sending()) return;

    this.error.set('');
    this.message.set('');
    this.sending.set(true);

    this.auth
      .sendPasswordResetLink({ email: this.form.value.email! })
      .subscribe({
        next: () => {
          this.message.set(
            'Si un compte existe avec cette adresse, un lien de réinitialisation vous a été envoyé.'
          );
          this.sending.set(false);
        },
        error: () => {
          this.error.set('Impossible d’envoyer le lien de réinitialisation. Réessayez.');
          this.sending.set(false);
        },
      });
  }
}
