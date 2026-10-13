import { Component, computed } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SHOP } from '../../config/shop';

@Component({
  selector: 'app-contact',
  imports: [RouterLink],
  templateUrl: './contact.html',
})
export class Contact {
  protected readonly shop = SHOP;
  protected readonly phoneHref = 'tel:' + SHOP.phone.split(' ').join('');
  protected readonly whatsappHref =
    'https://wa.me/' + SHOP.whatsapp + '?text=' + encodeURIComponent('Bonjour G-Tech, j\'ai une question.');
  protected readonly emailHref = 'mailto:' + SHOP.email;

  protected readonly hasContact = computed(() => !!(SHOP.phone || SHOP.whatsapp || SHOP.email));
}