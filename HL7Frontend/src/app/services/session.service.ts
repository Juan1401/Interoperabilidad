import { Injectable, signal } from '@angular/core';

@Injectable({
    providedIn: 'root'
})
export class SessionService {
    public sessionData = signal<any | null>(null);

    constructor() {
        const data = this.getDecryptedData();
        if (data) {
            this.sessionData.set(data);
        }
    }

    setSessionData(data: any): void {
        this.sessionData.set(data);
        if (data) {
            sessionStorage.setItem('decryptedSession', JSON.stringify(data));
        } else {
            sessionStorage.removeItem('decryptedSession');
        }
    }

    getDecryptedData(): any | null {
        const current = this.sessionData();
        if (current) {
            return current;
        }

        try {
            const storedData = sessionStorage.getItem('decryptedSession');
            if (storedData) {
                return JSON.parse(storedData);
            }
        } catch (error) {
            console.error('Error parsing session data from sessionStorage:', error);
        }
        return null;
    }

    clearSession(): void {
        this.sessionData.set(null);
        sessionStorage.removeItem('decryptedSession');
    }
}
