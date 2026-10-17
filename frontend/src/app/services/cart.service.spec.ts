import { CartService } from './cart.service';
import { Product } from '../models/catalogue';

describe('CartService quantity controls', () => {
  let cart: CartService;

  beforeEach(() => {
    localStorage.clear();
    cart = new CartService();
  });

  it('caps the plus control at the stock and API quantity limit', () => {
    cart.add(product({ stock: 20 }), 10);

    cart.setQuantity(1, 11);

    expect(cart.quantityOf(1)).toBe(10);
    expect(cart.maxQuantity(cart.items()[0])).toBe(10);
  });

  it('does not allow a quantity above the available stock', () => {
    cart.add(product({ stock: 3 }));

    cart.setQuantity(1, 8);

    expect(cart.quantityOf(1)).toBe(3);
  });

  it('removes a line when decrementing below one', () => {
    cart.add(product({ stock: 4 }));

    cart.setQuantity(1, 0);

    expect(cart.items()).toEqual([]);
    expect(cart.count()).toBe(0);
    expect(cart.total()).toBe(0);
  });

  it('does not add a product that has no stock', () => {
    cart.add(product({ stock: 0 }));

    expect(cart.items()).toEqual([]);
  });
});

function product(overrides: Partial<Product> = {}): Product {
  return {
    id: 1,
    category_id: 1,
    name: 'Ordinateur test',
    slug: 'ordinateur-test',
    reference: 'TEST-001',
    brand: 'G-Tech',
    description: null,
    price: 100000,
    stock: 5,
    stock_alert_threshold: 1,
    processor: null,
    ram: null,
    storage: null,
    graphics_card: null,
    screen: null,
    state: 'neuf',
    image: null,
    is_active: true,
    ...overrides,
  };
}
