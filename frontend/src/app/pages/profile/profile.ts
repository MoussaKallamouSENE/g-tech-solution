import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { AuthService, User } from '../../services/auth.service';
import { errorMessage } from '../../services/auth.service';

@Component({
  selector: 'app-profile',
  imports: [RouterLink, ReactiveFormsModule],
  templateUrl: './profile.html',
})
export class Profile {
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder);

  protected readonly user = this.auth.user;
  protected readonly saving = signal(false);
  protected readonly savingPassword = signal(false);
  protected readonly message = signal('');
  protected readonly error = signal('');

  protected readonly profileForm = this.fb.group({
    name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    phone: [''],
  });

  protected readonly passwordForm = this.fb.group({
    current_password: ['', Validators.required],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', Validators.required],
  });

  constructor() {
    const currentUser = this.auth.user();
    if (currentUser) {
      this.profileForm.patchValue({
        name: currentUser.name,
        email: currentUser.email,
        phone: currentUser.phone ?? '',
      });
    }
  }

  protected saveProfile(): void {
    if (this.profileForm.invalid || this.saving()) return;

    this.error.set('');
    this.message.set('');
    this.saving.set(true);

    this.auth
      .updateProfile({
        name: this.profileForm.value.name!,
        email: this.profileForm.value.email!,
        phone: this.profileForm.value.phone || null,
      })
      .subscribe({
        next: (user: User) => {
          this.message.set('Informations mises à jour.');
          this.saving.set(false);
        },
        error: (err: unknown) => {
          this.error.set(errorMessage(err as never));
          this.saving.set(false);
        },
      });
  }

  protected savePassword(): void {
    if (this.passwordForm.invalid || this.savingPassword()) return;

    this.error.set('');
    this.message.set('');
    this.savingPassword.set(true);

    this.auth
      .updatePassword({
        current_password: this.passwordForm.value.current_password!,
        password: this.passwordForm.value.password!,
        password_confirmation: this.passwordForm.value.password_confirmation!,
      })
      .subscribe({
        next: () => {
          this.message.set('Mot de passe mis à jour avec succès.');
          this.passwordForm.reset();
          this.savingPassword.set(false);
        },
        error: (err: unknown) => {
          this.error.set(errorMessage(err as never));
          this.savingPassword.set(false);
        },
      });
  }
}
