import { HttpInterceptorFn } from '@angular/common/http';
import { getToken } from '../services/auth.service';

/** Chemin d'API relayé par le serveur web. */
const apiPathPattern = /^\/api(\/|$)/;

/**
 * Indique si la requête cible l'API de l'application.
 *
 * Les URL relatives sont toujours de même origine. Les URL absolues ne sont
 * retenues que si elles pointent vers l'hôte courant : le jeton n'est ainsi
 * jamais transmis à un domaine externe.
 */
function isApiRequest(url: string): boolean {
  const origin = globalThis.location?.origin;

  if (!origin) {
    return apiPathPattern.test(url);
  }

  try {
    const parsed = new URL(url, origin);

    return parsed.origin === origin && apiPathPattern.test(parsed.pathname);
  } catch {
    return false;
  }
}

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = getToken();

  if (!token || !isApiRequest(req.url) || req.headers.has('Authorization')) {
    return next(req);
  }

  return next(req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }));
};
