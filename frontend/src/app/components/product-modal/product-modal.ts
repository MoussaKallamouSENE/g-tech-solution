import { DOCUMENT } from '@angular/common';
import {
  AfterViewInit,
  Component,
  ElementRef,
  HostListener,
  OnDestroy,
  inject,
  input,
  output,
  signal,
  viewChild,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartService } from '../../services/cart.service';
import { Product } from '../../models/catalogue';

/**
 * Fiche produit affichée en surimpression du catalogue.
 *
 * Le composant est monté et démonté par le parent : le verrouillage du
 * défilement de la page et la restitution du focus sont donc gérés dans le
 * cycle de vie, ce qui garantit que la position de défilement du catalogue
 * est conservée à la fermeture.
 */
@Component({
  selector: 'app-product-modal',
  imports: [RouterLink],
  templateUrl: './product-modal.html',
})
export class ProductModal implements AfterViewInit, OnDestroy {
  readonly product = input.required<Product>();
  readonly closed = output<void>();

  private readonly cart = inject(CartService);
  private readonly document = inject(DOCUMENT);

  protected readonly added = signal(false);
  protected readonly imageFailed = signal(false);

  private readonly panel = viewChild<ElementRef<HTMLElement>>('panel');
  private readonly closeButton = viewChild<ElementRef<HTMLButtonElement>>('closeButton');

  private previousFocus: HTMLElement | null = null;
  private previousOverflow = '';
  private previousPaddingRight = '';

  constructor() {
    this.previousFocus = this.document.activeElement as HTMLElement | null;
    this.lockScroll();
  }

  ngAfterViewInit(): void {
    this.closeButton()?.nativeElement.focus();
  }

  ngOnDestroy(): void {
    this.unlockScroll();
    this.previousFocus?.focus();
  }

  @HostListener('document:keydown.escape')
  protected onEscape(): void {
    this.close();
  }

  protected close(): void {
    this.closed.emit();
  }

  protected addToCart(product: Product): void {
    if (product.stock < 1) {
      return;
    }

    this.cart.add(product);
    this.added.set(true);
  }

  protected formatPrice(price: number): string {
    return price.toLocaleString('fr-FR') + ' FCFA';
  }

  /**
   * Maintient le focus clavier à l'intérieur de la fenêtre modale.
   */
  protected trapFocus(event: KeyboardEvent): void {
    if (event.key !== 'Tab') {
      return;
    }

    const focusables = this.panel()?.nativeElement.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );

    if (!focusables || focusables.length === 0) {
      return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const active = this.document.activeElement;

    if (event.shiftKey && active === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
  }

  /**
   * Empêche le défilement de la page en arrière-plan pendant l'ouverture.
   * La position de défilement n'est pas modifiée : seule la barre de
   * défilement est masquée et sa largeur est compensée sur le body.
   */
  private lockScroll(): void {
    const body = this.document.body;
    const view = this.document.defaultView;
    const clientWidth = this.document.documentElement.clientWidth;
    const scrollbarWidth =
      view && clientWidth > 0 ? Math.max(0, view.innerWidth - clientWidth) : 0;

    this.previousOverflow = body.style.overflow;
    this.previousPaddingRight = body.style.paddingRight;

    body.style.overflow = 'hidden';

    if (scrollbarWidth > 0) {
      body.style.paddingRight = `${scrollbarWidth}px`;
    }
  }

  private unlockScroll(): void {
    const body = this.document.body;

    body.style.overflow = this.previousOverflow;
    body.style.paddingRight = this.previousPaddingRight;
  }
}
