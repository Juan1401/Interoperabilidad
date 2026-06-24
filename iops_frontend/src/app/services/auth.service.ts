import { Injectable, inject, signal, computed } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { environment } from '../../environments/environment';
import { Observable, tap, catchError, throwError, firstValueFrom } from 'rxjs';
import { Router } from '@angular/router';
import { LoginCredentials, LoginResponse, UsuarioInfo } from '../models/auth.models';

/**
 * Interface para la solicitud de auditoría de acceso
 */
export interface AuditoriaRequest {
    uuid: string;
    usuario_id: string;
    ip: string;
    estado: string;
}

/**
 * Interface de la respuesta esperada por Laravel Passport / OAuth2
 */
export interface OAuthTokenResponse {
    token_type: string;
    expires_in: number;
    access_token: string;
    refresh_token?: string;
}

@Injectable({
    providedIn: 'root'
})
export class AuthService {
    private http = inject(HttpClient);

    // Clave usada para persistir el token en sessionStorage
    private readonly TOKEN_KEY = 'HL7_OAUTH_TOKEN';

    // Signal para almacenar la respuesta de auditoría y hacerla reactiva
    public auditoriaRespuesta = signal<any>(null);

    // Estado del usuario autenticado en la sesión actual
    private _currentUser = signal<UsuarioInfo | null>(this.getStoredUser());
    public readonly currentUser = this._currentUser.asReadonly();
    public readonly isAuthenticated = computed(() => this._currentUser() !== null);

    private router = inject(Router);

    /**
     * Recupera el usuario guardado del sessionStorage
     */
    private getStoredUser(): UsuarioInfo | null {
        const stored = sessionStorage.getItem('HL7_USER_INFO');
        return stored ? JSON.parse(stored) : null;
    }

    /**
     * Ejecuta la petición POST a Laravel para obtener el Crendetial Token.
     * Utiliza las variables almacenadas en environment.ts para evitar quemar datos sensibles.
     * Al resolverse, almacena directamente el JWT en SessionStorage.
     */
    public fetchAndStoreToken(): Observable<OAuthTokenResponse> {
        const payload = new HttpParams().set('grant_type', environment.apiAuth.grantType).set('client_id', environment.apiAuth.clientId).set('client_secret', environment.apiAuth.clientSecret);

        const headers = new HttpHeaders({
            'Content-Type': 'application/x-www-form-urlencoded',
            Accept: 'application/json'
        });

        return this.http.post<OAuthTokenResponse>(environment.apiAuth.url + '/oauth/token', payload.toString(), { headers }).pipe(
            tap((response: OAuthTokenResponse) => {
                if (response && response.access_token) {
                    this.setSessionToken(response.access_token);
                    // console.log('Token de API obtenido y guardado en sesión exitosamente.');
                }
            }),
            catchError((error: any) => {
                console.error('Error obteniendo el token OAuth de Laravel:', error);
                return throwError(() => new Error('Fallo la autenticación con el servidor.'));
            })
        );
    }

    /**
     * Guarda el Token en la sesión actual
     */
    private setSessionToken(token: string): void {
        sessionStorage.setItem(this.TOKEN_KEY, token);
    }

    /**
     * Retorna el token almacenado si la aplicación lo requiere en otro servicio (ej: Interceptors)
     */
    public getSessionToken(): string | null {
        return sessionStorage.getItem(this.TOKEN_KEY);
    }

    /**
     * Elimina el token de la sesión al cerrar la app o caducar
     */
    public clearSessionToken(): void {
        sessionStorage.removeItem(this.TOKEN_KEY);
    }

    /**
     * Obtiene una variable del sistema desde el backend.
     */
    public getSystemVariable(modulo: string, moduloTipo: string, variable: string, token: string): Observable<any> {
        const url = `${environment.apiAuth.url}/api/hl7/system/variables`;

        const headers = new HttpHeaders({
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`
        });

        const body = {
            modulo: modulo,
            modulo_tipo: moduloTipo,
            variable: variable
        };

        return this.http.post<any>(url, body, { headers });
    }

    /**
     * Registra una auditoría de acceso usando el endpoint especificado
     */
    public registrarAuditoria(data: AuditoriaRequest, token: string): Observable<any> {
        const url = `${environment.apiAuth.url}/api/hl7/auditoria-acceso-link`;

        const headers = new HttpHeaders({
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`
        });

        return this.http.post<any>(url, data, { headers }).pipe(
            tap((response: any) => this.auditoriaRespuesta.set(response)),
            catchError((error: any) => {
                console.error('Error al registrar la auditoría de acceso:', error);
                if (error.error && error.error.errors) {
                    console.error('Detalles de la validación (Laravel):', error.error.errors);
                }
                return throwError(() => new Error('Fallo al enviar la auditoría de acceso.'));
            })
        );
    }

    /**
     * Autenticación de usuario con credenciales (usuario y password)
     */
    public login(credentials: LoginCredentials): Observable<LoginResponse> {
        const url = `${environment.apiAuth.url}/api/login`;

        const token = this.getSessionToken();
        let headers = new HttpHeaders();
        if (token) {
            headers = headers.set('Authorization', `Bearer ${token}`);
        }

        // Petición POST, enviando el Bearer token obtenido previamente
        return this.http.post<LoginResponse>(url, credentials, { headers }).pipe(
            tap((response) => {
                if (response && response.access_token) {
                    this.setSessionToken(response.access_token);
                    sessionStorage.setItem('HL7_USER_INFO', JSON.stringify(response.user));
                    this._currentUser.set(response.user);
                }
            }),
            catchError((error: any) => {
                console.error('Error en login:', error);
                return throwError(() => new Error(error.error?.message || 'Error de autenticación. Verifica tus credenciales.'));
            })
        );
    }

    /**
     * Cerrar sesión del usuario actual
     */
    public logout(): void {
        this.clearSessionToken();
        sessionStorage.removeItem('HL7_USER_INFO');
        this._currentUser.set(null);
        this.router.navigate(['/auth/login']);
    }
}
