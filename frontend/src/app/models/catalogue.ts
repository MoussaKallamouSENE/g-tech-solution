export interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  is_active: boolean;
  products_count?: number;
}

export interface Product {
  id: number;
  category_id: number;
  name: string;
  slug: string;
  reference: string;
  brand: string | null;
  description: string | null;
  price: number;
  stock: number;
  stock_alert_threshold: number;
  processor: string | null;
  ram: string | null;
  storage: string | null;
  graphics_card: string | null;
  screen: string | null;
  state: string;
  image: string | null;
  is_active: boolean;
  image_url?: string | null;
  category?: Category;
}

export interface Paginated<T> {
  current_page: number;
  data: T[];
  last_page: number;
  per_page: number;
  total: number;
}