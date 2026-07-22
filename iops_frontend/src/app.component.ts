import { Component, OnInit, inject } from '@angular/core';
import { RouterModule } from '@angular/router';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { LoadingComponent } from './app/shared/components/loading/loading.component';
import { AuthService } from './app/services/auth.service';
import { SessionService } from './app/services/session.service';
import { IdleService } from './app/services/idle.service';
import { UsuarioInfo } from './app/models/auth.models';

import { IpService } from './app/services/ip.service';

@Component({
    selector: 'app-root',
    standalone: true,
    imports: [RouterModule, ToastModule, ConfirmDialogModule, LoadingComponent],
    template: `
        <p-toast></p-toast>
        <p-confirmDialog [style]="{ width: '50vw', maxWidth: '400px' }" [closable]="false" rejectButtonStyleClass="p-button-text">
            <ng-template pTemplate="message">
                <div class="flex flex-col items-center w-full gap-4 p-4">
                    <i class="pi pi-exclamation-triangle text-6xl text-yellow-500"></i>
                    <p class="text-center text-lg font-medium" style="color: var(--text-color);">{{ idleService.idleMessage() }}</p>
                </div>
            </ng-template>
        </p-confirmDialog>
        <router-outlet></router-outlet>
        <app-loading></app-loading>
    `
})
export class AppComponent implements OnInit {
    private authService    = inject(AuthService);
    private sessionService = inject(SessionService);
    private ipService      = inject(IpService);
    public  idleService    = inject(IdleService);

    async ngOnInit(): Promise<void> {
        // Re-hidratación de sesión al recargar la página
        const storedUserStr =
            localStorage.getItem('HL7_USER_INFO') ||
            localStorage.getItem('decryptedSession') ||
            sessionStorage.getItem('HL7_USER_INFO') ||
            sessionStorage.getItem('decryptedSession');

        if (storedUserStr) {
            try {
                const storedUser = JSON.parse(storedUserStr) as UsuarioInfo;
                this.sessionService.setSessionData(storedUser);
            } catch (e) {
                console.error('[AppComponent] Error al re-hidratar la sesión:', e);
                this.authService.logout();
                return;
            }
        }

        // Actualizar IP local física de la tarjeta de red (ej. 192.168.4.127) en la sesión
        try {
            const localIp = await this.ipService.detectLocalIp();
            sessionStorage.setItem('CLIENT_IP', localIp);
            localStorage.setItem('CLIENT_IP', localIp);

            const activeSession = this.sessionService.sessionData();
            if (activeSession) {
                activeSession.IP = localIp;
                this.sessionService.setSessionData(activeSession);
            }
        } catch (e) {}
    }
}

