import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { AuthService, errorMessage } from '../../services/auth.service';

@Component({
  selector: 'app-reset-password',
  imports: [RouterLink, ReactiveFormsModule],
  templateUrl: './reset-password.html',
})
export class ResetPassword {
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    token: ['', Validators.required],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', Validators.required],
  });
  protected readonly resetting = signal(false);
  protected readonly message = signal('');
  protected readonly error = signal('');

  constructor() {
    const email = this.route.snapshot.queryParamMap.get('email');
    const token = this.route.snapshot.queryParamMap.get('token');
    if (email) this.form.patchValue({ email });
    if (token) this.form.patchValue({ token });
  }

  protected submit(): void {
    if (this.form.invalid || this.resetting()) return;

    this.error.set('');
    this.message.set('');
    this.resetting.set(true);

    this.auth
      .resetPassword({
        email: this.form.value.email!,
        token: this.form.value.token!,
        password: this.form.value.password!,
        password_confirmation: this.form.value.password_confirmation!,
      })
      .subscribe({
        next: () => {
          this.message.set('Mot de passe réinitialisé avec succès. Vous pouvez vous connecter.');
          this.resetting.set(false);
          this.router.navigate(['/connexion']);
        },
        error: () => {
          this.error.set('Le token est invalide ou a expiré. Demandez un nouveau lien.');
          this.resetting.set(false);
        },
      });
  }
}
