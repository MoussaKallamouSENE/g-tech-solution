import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { Product } from '../../models/catalogue';
import { AdminService, PurchaseReceipt, Supplier } from '../../services/admin.service';
import { errorMessage } from '../../services/auth.service';
import { formatPrice } from '../../services/order.service';

interface ReceiptLineDraft {
  rowId: number;
  productId: number | null;
  quantity: string;
  unitCost: string;
}

@Component({
  selector: 'app-admin-suppliers',
  imports: [RouterLink],
  templateUrl: './admin-suppliers.html',
})
export class AdminSuppliers {
  private readonly admin = inject(AdminService);
  private nextLineId = 1;

  protected readonly suppliers = signal<Supplier[]>([]);
  protected readonly products = signal<Product[]>([]);
  protected readonly receipts = signal<PurchaseReceipt[]>([]);
  protected readonly receiptPage = signal(1);
  protected readonly receiptLastPage = signal(1);
  protected readonly lines = signal<ReceiptLineDraft[]>([this.newLine()]);
  protected readonly loading = signal(true);
  protected readonly savingSupplier = signal(false);
  protected readonly savingReceipt = signal(false);
  protected readonly denied = signal(false);
  protected readonly notice = signal('');
  protected readonly problem = signal('');
  protected readonly supplierProblem = signal('');
  protected readonly receiptProblem = signal('');
  protected readonly receiptKey = signal(crypto.randomUUID());

  protected readonly formatPrice = formatPrice;
  protected readonly today = this.localDate();

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.admin.getSuppliers().subscribe({
      next: (response) => this.suppliers.set(response.data),
      error: (err: HttpErrorResponse) => this.handleLoadError(err),
    });
    this.admin.getProducts({ page: 1 }).subscribe({
      next: (response) => this.products.set(response.data),
      error: (err: HttpErrorResponse) => this.handleLoadError(err),
    });
    this.loadReceipts();
  }

  protected loadReceipts(): void {
    this.admin.getPurchaseReceipts(this.receiptPage()).subscribe({
      next: (response) => {
        this.receipts.set(response.data);
        this.receiptLastPage.set(response.last_page);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => this.handleLoadError(err),
    });
  }

  protected goToReceiptPage(page: number): void {
    this.receiptPage.set(page);
    this.loading.set(true);
    this.loadReceipts();
  }

  protected searchProducts(query: string): void {
    this.admin.getProducts({ search: query.trim(), page: 1 }).subscribe({
      next: (response) => {
        const byId = new Map(this.products().map((product) => [product.id, product]));
        response.data.forEach((product) => byId.set(product.id, product));
        this.products.set([...byId.values()]);
      },
      error: (err: HttpErrorResponse) => this.problem.set(errorMessage(err)),
    });
  }

  protected createSupplier(event: Event, form: HTMLFormElement): void {
    event.preventDefault();
    this.supplierProblem.set('');
    const formData = new FormData(form);
    this.savingSupplier.set(true);
    this.admin.createSupplier({
      name: String(formData.get('name') ?? '').trim(),
      contact_name: String(formData.get('contact_name') ?? '').trim() || null,
      phone: String(formData.get('phone') ?? '').trim() || null,
      email: String(formData.get('email') ?? '').trim() || null,
      address: String(formData.get('address') ?? '').trim() || null,
      notes: null,
      is_active: true,
    }).subscribe({
      next: (supplier) => {
        this.suppliers.update((items) => [...items, supplier].sort((a, b) => a.name.localeCompare(b.name)));
        form.reset();
        this.savingSupplier.set(false);
        this.notice.set(`Fournisseur « ${supplier.name} » ajouté.`);
      },
      error: (err: HttpErrorResponse) => {
        this.supplierProblem.set(errorMessage(err));
        this.savingSupplier.set(false);
      },
    });
  }

  protected toggleSupplier(supplier: Supplier): void {
    this.admin.updateSupplier(supplier.id, {
      name: supplier.name,
      contact_name: supplier.contact_name,
      phone: supplier.phone,
      email: supplier.email,
      address: supplier.address,
      notes: supplier.notes,
      is_active: !supplier.is_active,
    }).subscribe({
      next: (updated) => this.suppliers.update((items) => items.map((item) => item.id === updated.id ? updated : item)),
      error: (err: HttpErrorResponse) => this.supplierProblem.set(errorMessage(err)),
    });
  }

  protected addLine(): void {
    this.lines.update((items) => [...items, this.newLine()]);
  }

  protected activeSuppliers(): Supplier[] {
    return this.suppliers().filter((supplier) => supplier.is_active);
  }

  protected removeLine(rowId: number): void {
    if (this.lines().length > 1) {
      this.lines.update((items) => items.filter((item) => item.rowId !== rowId));
    }
  }

  protected updateLine(rowId: number, field: 'productId' | 'quantity' | 'unitCost', value: string): void {
    this.lines.update((items) => items.map((item) => {
      if (item.rowId !== rowId) return item;
      return { ...item, [field]: field === 'productId' ? (value ? Number(value) : null) : value };
    }));
  }

  protected updateLineFromEvent(rowId: number, field: 'productId' | 'quantity' | 'unitCost', event: Event): void {
    const target = event.target;
    if (target instanceof HTMLInputElement || target instanceof HTMLSelectElement) {
      this.updateLine(rowId, field, target.value);
    }
  }

  protected lineTotal(line: ReceiptLineDraft): number {
    const quantity = Number(line.quantity);
    const cost = Number(line.unitCost);
    return Number.isSafeInteger(quantity * cost) ? quantity * cost : 0;
  }

  protected receiptTotal(): number {
    return this.lines().reduce((sum, line) => sum + this.lineTotal(line), 0);
  }

  protected totalIsSafe(): boolean {
    return this.lines().every((line) => Number.isSafeInteger(Number(line.quantity) * Number(line.unitCost)))
      && Number.isSafeInteger(this.receiptTotal());
  }

  protected submitReceipt(event: Event, form: HTMLFormElement): void {
    event.preventDefault();
    this.receiptProblem.set('');
    this.notice.set('');
    const formData = new FormData(form);
    const lines = this.lines();
    const items = lines.map((line) => ({
      product_id: Number(line.productId),
      quantity: Number(line.quantity),
      unit_cost: Number(line.unitCost),
    }));
    const totalCost = items.reduce((sum, item) => sum + item.quantity * item.unit_cost, 0);
    if (lines.some((line) => line.unitCost.trim() === '') ||
      items.some((item) => !Number.isInteger(item.product_id) || item.product_id < 1 ||
      !Number.isSafeInteger(item.quantity) || item.quantity < 1 ||
      !Number.isSafeInteger(item.unit_cost) || item.unit_cost < 0 ||
      !Number.isSafeInteger(item.quantity * item.unit_cost))) {
      this.receiptProblem.set('Choisissez un produit et saisissez des quantités et coûts unitaires entiers valides.');
      return;
    }
    if (!Number.isSafeInteger(totalCost)) {
      this.receiptProblem.set('Le montant total dépasse la limite de précision prise en charge.');
      return;
    }
    if (new Set(items.map((item) => item.product_id)).size !== items.length) {
      this.receiptProblem.set('Un même produit ne peut apparaître qu’une seule fois dans une réception.');
      return;
    }

    this.savingReceipt.set(true);
    this.admin.createPurchaseReceipt({
      idempotency_key: this.receiptKey(),
      supplier_id: Number(formData.get('supplier_id')),
      supplier_reference: String(formData.get('supplier_reference') ?? '').trim(),
      received_at: String(formData.get('received_at') ?? ''),
      notes: String(formData.get('notes') ?? '').trim(),
      items,
    }).subscribe({
      next: (receipt) => {
        this.receiptPage.set(1);
        this.loading.set(true);
        this.loadReceipts();
        this.lines.set([this.newLine()]);
        this.receiptKey.set(crypto.randomUUID());
        form.reset();
        const dateInput = form.elements.namedItem('received_at') as HTMLInputElement | null;
        if (dateInput) dateInput.value = this.localDate();
        this.savingReceipt.set(false);
        this.notice.set(`Réception ${receipt.number} enregistrée. Le stock a été mis à jour.`);
      },
      error: (err: HttpErrorResponse) => {
        this.receiptProblem.set(errorMessage(err));
        this.savingReceipt.set(false);
      },
    });
  }

  private newLine(): ReceiptLineDraft {
    return { rowId: this.nextLineId++, productId: null, quantity: '1', unitCost: '' };
  }

  private localDate(): string {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  }

  private handleLoadError(err: HttpErrorResponse): void {
    if (err.status === 401 || err.status === 403) this.denied.set(true);
    this.problem.set(errorMessage(err));
    this.loading.set(false);
  }
}
