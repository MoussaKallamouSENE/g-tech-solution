import { Injectable, computed, inject, signal } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Observable, catchError, finalize, of, tap, throwError } from 'rxjs';
import { API_BASE_URL } from '../config/api';

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: string;
}

interface AuthResponse {
  user: User;
  token: string;
}

const TOKEN_KEY = 'gtech_token';

export function getToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

export function errorMessage(err: HttpErrorResponse): string {
  const errors = err.error?.errors;
  if (errors) {
    const first = Object.values(errors)[0] as string[] | undefined;
    if (first?.[0]) {
      return first[0];
    }
  }
  if (err.status === 429) {
    return 'Trop de tentatives. Réessayez dans une minute.';
  }
  if (err.status === 0) {
    return 'Le serveur ne répond pas. Vérifiez que Docker est démarré.';
  }
  return err.error?.message ?? 'Une erreur est survenue. Réessayez.';
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = API_BASE_URL;

  readonly user = signal<User | null>(null);
  readonly isLoggedIn = computed(() => this.user() !== null);
  readonly sessionLoadError = signal<HttpErrorResponse | null>(null);

  constructor() {
    this.loadCurrentUser().subscribe({
      error: (err: HttpErrorResponse) => this.sessionLoadError.set(err),
    });
  }

  loadCurrentUser(): Observable<User | null> {
    if (!getToken()) {
      return of(null);
    }

    return this.http.get<User>(`${this.apiUrl}/me`).pipe(
      tap((user) => {
        this.user.set(user);
        this.sessionLoadError.set(null);
      }),
      catchError((err: HttpErrorResponse) => {
        if (err.status === 401) {
          this.clear();
          return of(null);
        }
        return throwError(() => err);
      }),
    );
  }

  register(data: { name: string; email: string; phone: string; password: string }): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/register`, data).pipe(tap((res) => this.store(res)));
  }

  login(data: { email: string; password: string }): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/login`, data).pipe(tap((res) => this.store(res)));
  }

  logout(): Observable<unknown> {
    return this.http.post(`${this.apiUrl}/logout`, {}).pipe(finalize(() => this.clear()));
  }

  private store(res: AuthResponse): void {
    try {
      localStorage.setItem(TOKEN_KEY, res.token);
    } catch {
      // Stockage indisponible : la session ne durera que pour cette visite.
    }
    this.user.set(res.user);
  }

  private clear(): void {
    try {
      localStorage.removeItem(TOKEN_KEY);
    } catch {
      // Rien à faire.
    }
    this.user.set(null);
    this.sessionLoadError.set(null);
  }

  updateProfile(data: { name: string; email: string; phone: string | null }): Observable<User> {
    return this.http.put<User>(`${this.apiUrl}/me`, data).pipe(tap((user) => this.user.set(user)));
  }

  updatePassword(data: { current_password: string; password: string; password_confirmation: string }): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`${this.apiUrl}/me/password`, data);
  }

  sendPasswordResetLink(data: { email: string }): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/password/email`, data);
  }

  resetPassword(data: {
    email: string;
    token: string;
    password: string;
    password_confirmation: string;
  }): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/password/reset`, data);
  }
}