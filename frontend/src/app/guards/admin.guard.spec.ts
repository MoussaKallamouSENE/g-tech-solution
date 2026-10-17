import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { adminGuard } from './admin.guard';

describe('adminGuard', () => {
  beforeEach(() => {
    localStorage.removeItem('gtech_token');
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: { user: () => null } },
      ],
    });
  });

  it('preserves the requested admin URL when redirecting to login', () => {
    const targetUrl = '/admin/commandes';
    const result = TestBed.runInInjectionContext(() =>
      adminGuard(new ActivatedRouteSnapshot(), { url: targetUrl } as RouterStateSnapshot),
    );

    expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe(
      '/connexion?redirect=%2Fadmin%2Fcommandes',
    );
  });
});
