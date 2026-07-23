export interface LoginCredentials {
    usuario: string;
    password: string;
}

export interface LoginResponse {
    access_token: string;
    token_type: string;
    message: string;
    user: UsuarioInfo;
}

export interface UsuarioInfo {
    id: number;
    name: string;
    apellidos: string | null;
    email: string;
    usuario: string;
    organization_id: number | null;
    tipo_documento: string | null;
    numero_documento: string | null;
    especialidad_codigo: string | null;
    user_id?: number;
    usuario_id?: number;
    US?: number | string;
    tipo_documento_us?: string | null;
    documento_us?: string | null;
    IP?: string;
    UUID?: string;
    PERMISSION?: any;
    [key: string]: any;
}


export type AuthState = 'idle' | 'loading' | 'success' | 'error';
