import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { AuthService, getToken } from '../services/auth.service';

export const adminGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (auth.user()?.role === 'admin') {
    return true;
  }

  if (!getToken()) {
    return router.createUrlTree(['/connexion'], {
      queryParams: { redirect: state.url },
    });
  }

  return auth.loadCurrentUser().pipe(
    map((user) => {
      if (user?.role === 'admin') {
        return true;
      }

      if (user) {
        return router.createUrlTree(['/']);
      }

      return router.createUrlTree(['/connexion'], {
        queryParams: { redirect: state.url },
      });
    }),
    catchError(() =>
      of(
        router.createUrlTree(['/connexion'], {
          queryParams: { redirect: state.url, reason: 'session-check-failed' },
        }),
      ),
    ),
  );
};
