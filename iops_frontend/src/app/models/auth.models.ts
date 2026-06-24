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
    email: string;
    usuario: string;
}

export type AuthState = 'idle' | 'loading' | 'success' | 'error';
