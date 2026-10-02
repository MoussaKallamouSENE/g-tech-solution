import { HttpInterceptorFn } from '@angular/common/http';
import { getToken } from '../services/auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = getToken();
  if (!token || !req.url.startsWith('http://localhost/api')) {
    return next(req);
  }
  return next(req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }));
};