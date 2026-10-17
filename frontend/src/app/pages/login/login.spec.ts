import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { Login } from './login';
import { AuthService } from '../../services/auth.service';

describe('Login role routing', () => {
  let fixture: ComponentFixture<Login>;
  let router: Router;
  let login: ReturnType<typeof vi.fn>;
  let query: Record<string, string | null>;

  async function createLogin(role: string, redirect: string | null = null): Promise<void> {
    query = { redirect };
    login = vi.fn(() => {
      authUser = { id: 7, name: 'Test user', email: 'user@example.com', phone: null, role };
      return of({ user: authUser, token: 'test-token' });
    });
    let authUser: { id: number; name: string; email: string; phone: string | null; role: string } | null = null;
    await TestBed.configureTestingModule({
      imports: [Login],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { queryParamMap: { get: (key: string) => query[key] ?? null } } },
        },
        { provide: AuthService, useValue: { login, user: () => authUser } },
      ],
    }).compileComponents();
    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    fixture = TestBed.createComponent(Login);
    fixture.detectChanges();
  }

  async function submit(): Promise<void> {
    const form = fixture.nativeElement.querySelector('form') as HTMLFormElement;
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await fixture.whenStable();
  }

  it('routes a driver to the driver dashboard by default', async () => {
    await createLogin('driver');
    await submit();

    expect(login).toHaveBeenCalled();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/livreur');
    fixture.destroy();
  });

  it('preserves an allowed redirect for a driver but never routes one into the customer area', async () => {
    await createLogin('driver', '/livreur');
    await submit();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/livreur');
    fixture.destroy();

    TestBed.resetTestingModule();
    await createLogin('driver', '/commandes');
    await submit();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/livreur');
    fixture.destroy();
  });

  it('keeps customers in the customer area and routes administrators to administration', async () => {
    await createLogin('customer');
    await submit();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/');
    fixture.destroy();

    TestBed.resetTestingModule();
    await createLogin('admin');
    await submit();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/admin');
    fixture.destroy();
  });
});
