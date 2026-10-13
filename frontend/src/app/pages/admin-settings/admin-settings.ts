import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { AdminCategory, AdminShopService, AdminZone } from '../../services/admin-shop.service';
import { errorMessage } from '../../services/auth.service';

@Component({
  selector: 'app-admin-settings',
  imports: [RouterLink],
  templateUrl: './admin-settings.html',
})
export class AdminSettings {
  private readonly shop = inject(AdminShopService);

  protected readonly categories = signal<AdminCategory[]>([]);
  protected readonly zones = signal<AdminZone[]>([]);
  protected readonly loading = signal(true);
  protected readonly denied = signal(false);
  protected readonly notice = signal('');
  protected readonly problem = signal('');

  constructor() {
    this.shop.getCategories().subscribe({
      next: (list) => {
        this.categories.set(list);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        if (err.status === 401 || err.status === 403) {
          this.denied.set(true);
        }
        this.loading.set(false);
      },
    });
    this.reloadZones();
  }

  private reloadZones(): void {
    this.shop.getZones().subscribe({ next: (list) => this.zones.set(list), error: () => {} });
  }

  private done(message: string): void {
    this.notice.set(message);
    this.problem.set('');
  }

  private fail(err: HttpErrorResponse): void {
    this.problem.set(errorMessage(err));
    this.notice.set('');
  }

  private sorted(list: AdminCategory[]): AdminCategory[] {
    return [...list].sort((x, y) => x.name.localeCompare(y.name));
  }

  protected addCategory(event: Event, form: HTMLFormElement): void {
    event.preventDefault();
    const data = new FormData(form);
    this.shop
      .createCategory({
        name: String(data.get('name') ?? '').trim(),
        description: String(data.get('description') ?? '').trim() || null,
        is_active: true,
      })
      .subscribe({
        next: (c) => {
          this.categories.update((list) => this.sorted([...list, c]));
          form.reset();
          this.done(`Catégorie « ${c.name} » ajoutée.`);
        },
        error: (err: HttpErrorResponse) => this.fail(err),
      });
  }

  protected saveCategory(c: AdminCategory, name: string, description: string, active: boolean): void {
    this.shop
      .updateCategory(c.id, { name: name.trim(), description: description.trim() || null, is_active: active })
      .subscribe({
        next: (updated) => {
          this.categories.update((list) => this.sorted(list.map((x) => (x.id === updated.id ? updated : x))));
          this.done(`Catégorie « ${updated.name} » enregistrée.`);
        },
        error: (err: HttpErrorResponse) => this.fail(err),
      });
  }

  protected addZone(event: Event, form: HTMLFormElement): void {
    event.preventDefault();
    const data = new FormData(form);
    this.shop
      .createZone({
        name: String(data.get('name') ?? '').trim(),
        fee: Number(String(data.get('fee') ?? '').trim()),
        is_active: true,
      })
      .subscribe({
        next: (z) => {
          this.reloadZones();
          form.reset();
          this.done(`Zone « ${z.name} » ajoutée.`);
        },
        error: (err: HttpErrorResponse) => this.fail(err),
      });
  }

  protected saveZone(z: AdminZone, name: string, fee: string, active: boolean): void {
    this.shop.updateZone(z.id, { name: name.trim(), fee: Number(fee.trim()), is_active: active }).subscribe({
      next: (updated) => {
        this.zones.update((list) => list.map((x) => (x.id === updated.id ? updated : x)));
        this.done(`Zone « ${updated.name} » enregistrée.`);
      },
      error: (err: HttpErrorResponse) => this.fail(err),
    });
  }
}