import { TestBed } from '@angular/core/testing';
import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { authInterceptor } from './auth.interceptor';

describe('authInterceptor', () => {
  const TOKEN_KEY = 'gtech_token';
  const TOKEN = 'jeton-de-test';

  let http: HttpClient;
  let controller: HttpTestingController;

  beforeEach(() => {
    localStorage.setItem(TOKEN_KEY, TOKEN);

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
      ],
    });

    http = TestBed.inject(HttpClient);
    controller = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    controller.verify();
    localStorage.removeItem(TOKEN_KEY);
  });

  it('ajoute le jeton aux requêtes d’API relatives', () => {
    http.get('/api/products').subscribe();

    const request = controller.expectOne('/api/products');
    expect(request.request.headers.get('Authorization')).toBe(`Bearer ${TOKEN}`);
    request.flush({});
  });

  it('ajoute le jeton aux requêtes d’API absolues de la même origine', () => {
    http.get(`${location.origin}/api/admin/dashboard`).subscribe();

    const request = controller.expectOne(`${location.origin}/api/admin/dashboard`);
    expect(request.request.headers.get('Authorization')).toBe(`Bearer ${TOKEN}`);
    request.flush({});
  });

  it('n’envoie jamais le jeton vers un domaine externe', () => {
    http.get('https://exemple-externe.test/api/products').subscribe();

    const request = controller.expectOne('https://exemple-externe.test/api/products');
    expect(request.request.headers.has('Authorization')).toBe(false);
    request.flush({});
  });

  it('n’ajoute pas le jeton aux chemins qui ne sont pas l’API', () => {
    http.get('/panier').subscribe();

    const request = controller.expectOne('/panier');
    expect(request.request.headers.has('Authorization')).toBe(false);
    request.flush({});
  });

  it('ne remplace pas un en-tête Authorization déjà présent', () => {
    http.get('/api/products').subscribe();

    const request = controller.expectOne('/api/products');
    expect(request.request.headers.get('Authorization')).toBe(`Bearer ${TOKEN}`);
    request.flush({});
  });

  it('n’ajoute rien sans jeton enregistré', () => {
    localStorage.removeItem(TOKEN_KEY);

    http.get('/api/products').subscribe();

    const request = controller.expectOne('/api/products');
    expect(request.request.headers.has('Authorization')).toBe(false);
    request.flush({});
  });
});
