import { HttpInterceptorFn } from '@angular/common/http';
import { getToken } from '../services/auth.service';

const localhostApiPattern = /^https?:\/\/localhost(:\d+)?\/api\//;

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = getToken();

  if (!token || !localhostApiPattern.test(req.url) || req.headers.has('Authorization')) {
    return next(req);
  }

  return next(req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }));
};
