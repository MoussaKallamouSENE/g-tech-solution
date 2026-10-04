import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { AuthService, errorMessage } from '../../services/auth.service';
import { DEVICE_OPTIONS, MaintenanceService } from '../../services/maintenance.service';

@Component({
  selector: 'app-services',
  templateUrl: './services.html',
})
export class Services {
  private readonly maintenance = inject(MaintenanceService);
  protected readonly auth = inject(AuthService);

  protected readonly devices = DEVICE_OPTIONS;
  protected readonly loading = signal(false);
  protected readonly error = signal('');
  protected readonly sentNumber = signal('');

  protected submit(event: Event, form: HTMLFormElement): void {
    event.preventDefault();
    this.error.set('');
    this.sentNumber.set('');

    const data = new FormData(form);
    const text = (key: string): string => String(data.get(key) ?? '').trim();

    this.loading.set(true);
    this.maintenance
      .create({
        name: text('name'),
        phone: text('phone'),
        email: text('email') || null,
        device_type: text('device_type'),
        brand: text('brand') || null,
        problem: text('problem'),
      })
      .subscribe({
        next: (res) => {
          this.sentNumber.set(res.number);
          this.loading.set(false);
          form.reset();
        },
        error: (err: HttpErrorResponse) => {
          this.error.set(errorMessage(err));
          this.loading.set(false);
        },
      });
  }
}