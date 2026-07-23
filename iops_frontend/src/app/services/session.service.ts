import { Injectable, signal } from '@angular/core';
import { UsuarioInfo } from '../models/auth.models';

@Injectable({
    providedIn: 'root'
})
export class SessionService {
    // Signal tipada con la sesión reactiva del usuario autenticado
    public sessionData = signal<UsuarioInfo | null>(null);


    constructor() {
        // Re-hidratación automática al inicializar el servicio (recarga de página o navegación)
        const data = this.getStoredData();
        if (data) {
            this.sessionData.set(data);
        }
    }

    /**
     * Normaliza el objeto de sesión para garantizar compatibilidad completa
     * con todos los módulos de la aplicación (consultar-ihce, envio-ihce, topbar, auditoría).
     */
    public normalizeSessionData(raw: any): any {
        if (!raw) return null;

        const userId = Number(raw.id ?? raw.user_id ?? raw.usuario_id ?? raw.US ?? 0);
        const tipoDoc = raw.tipo_documento_us ?? raw.tipo_documento ?? 'CC';
        const numDoc = raw.documento_us ?? raw.numero_documento ?? '';
        const clientIp = raw.IP ?? localStorage.getItem('CLIENT_IP') ?? sessionStorage.getItem('CLIENT_IP') ?? '127.0.0.1';
        const sessionUuid = raw.UUID ?? (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : '00000000-0000-0000-0000-000000000000');
        const permission = raw.PERMISSION ?? { sw_envio_ihce: '0', sw_visor_ihce: '1' };

        return {
            ...raw,
            id: userId,
            user_id: userId,
            usuario_id: userId,
            US: userId,
            name: raw.name ?? raw.usuario ?? 'Usuario',
            email: raw.email ?? '',
            usuario: raw.usuario ?? raw.name ?? '',
            tipo_documento: tipoDoc,
            tipo_documento_us: tipoDoc,
            numero_documento: numDoc,
            documento_us: numDoc,
            IP: clientIp,
            UUID: sessionUuid,
            PERMISSION: permission
        };
    }

    /**
     * Guarda los datos de sesión normalizados en la Signal reactiva y en localStorage + sessionStorage.
     * Sincroniza tanto HL7_USER_INFO como decryptedSession para máxima compatibilidad global.
     */
    setSessionData(data: any | null): void {
        const normalized = this.normalizeSessionData(data);
        this.sessionData.set(normalized);

        if (normalized) {
            const jsonStr = JSON.stringify(normalized);
            localStorage.setItem('HL7_USER_INFO', jsonStr);
            localStorage.setItem('decryptedSession', jsonStr);
            sessionStorage.setItem('HL7_USER_INFO', jsonStr);
            sessionStorage.setItem('decryptedSession', jsonStr);
        } else {
            localStorage.removeItem('HL7_USER_INFO');
            localStorage.removeItem('decryptedSession');
            sessionStorage.removeItem('HL7_USER_INFO');
            sessionStorage.removeItem('decryptedSession');
        }
    }

    /**
     * Recupera los datos de sesión desde localStorage o sessionStorage (persistencia ante recargas/navegación).
     * Verifica la Signal reactiva primero, luego localStorage / sessionStorage.
     */
    getStoredData(): any | null {
        const current = this.sessionData();
        if (current) {
            return current;
        }

        try {
            const storedStr =
                localStorage.getItem('HL7_USER_INFO') ||
                localStorage.getItem('decryptedSession') ||
                sessionStorage.getItem('HL7_USER_INFO') ||
                sessionStorage.getItem('decryptedSession');

            if (storedStr) {
                const parsed = JSON.parse(storedStr);
                const normalized = this.normalizeSessionData(parsed);
                if (normalized) {
                    this.setSessionData(normalized);
                }
                return normalized;

            }
        } catch (error) {
            console.error('[SessionService] Error al parsear datos de sesión:', error);
        }
        return null;
    }

    /**
     * Limpia todos los datos de sesión del usuario en localStorage y sessionStorage.
     */
    clearSession(): void {
        this.sessionData.set(null);
        localStorage.removeItem('HL7_USER_INFO');
        localStorage.removeItem('decryptedSession');
        sessionStorage.removeItem('HL7_USER_INFO');
        sessionStorage.removeItem('decryptedSession');
    }
}


