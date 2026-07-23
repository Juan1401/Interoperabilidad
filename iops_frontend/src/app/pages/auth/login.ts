import { Component, ChangeDetectionStrategy, inject, signal, computed, OnInit } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { PasswordModule } from 'primeng/password';
import { RippleModule } from 'primeng/ripple';
import { CheckboxModule } from 'primeng/checkbox';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { AuthService } from '../../services/auth.service';
import { SessionService } from '../../services/session.service';
import { IpService } from '../../services/ip.service';
import { AuthState } from '../../models/auth.models';


@Component({
    selector: 'app-login',
    standalone: true,
    imports: [ReactiveFormsModule, ButtonModule, InputTextModule, PasswordModule, RippleModule, CheckboxModule, ToastModule],
    providers: [MessageService],
    changeDetection: ChangeDetectionStrategy.OnPush,
    template: `
        <p-toast />
        <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-screen overflow-hidden">
            <div class="flex flex-col items-center justify-center">
                <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, var(--primary-color) 10%, rgba(33, 150, 243, 0) 30%)">
                    <div class="w-full bg-surface-0 dark:bg-surface-900 py-20 px-8 sm:px-20" style="border-radius: 53px">
                        <div class="text-center mb-8">
                            <img src="/img/favicon.svg" alt="One Solution Logo" class="mb-8 w-24 mx-auto" />
                            <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-4">ONE SOLUTION</div>
                            <span class="text-muted-color font-medium">Inicia sesión</span>
                        </div>

                        <form [formGroup]="loginForm" (ngSubmit)="onSubmit()">
                            @if (errorMessage()) {
                                <div class="p-3 mb-6 rounded-lg bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm flex items-center gap-2">
                                    <i class="pi pi-exclamation-circle"></i>
                                    <span>{{ errorMessage() }}</span>
                                </div>
                            }

                            <label for="usuario" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Usuario</label>
                            <input pInputText id="usuario" type="text" placeholder="Usuario" class="w-full md:w-120 mb-8" formControlName="usuario" />

                            <label for="password" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Contraseña</label>
                            <p-password id="password" formControlName="password" placeholder="Password" [toggleMask]="true" styleClass="mb-4" [fluid]="true" [feedback]="false"></p-password>

                            <div class="flex items-center justify-between mt-2 mb-8 gap-8">
                                <div class="flex items-center">
                                    <!-- Espacio para recordar contraseña en el futuro -->
                                </div>
                                <!-- <span class="font-medium no-underline ml-2 text-right cursor-pointer text-primary">¿Olvidó su contraseña?</span> -->
                            </div>

                            <p-button type="submit" label="Ingresar al Sistema" styleClass="w-full" [loading]="isLoading()" [disabled]="loginForm.invalid || isLoading()"></p-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    `
})
export class Login implements OnInit {
    private fb            = inject(FormBuilder);
    private authService   = inject(AuthService);
    private router        = inject(Router);
    private messageService = inject(MessageService);

    public loginForm = this.fb.group({
        usuario:  ['', [Validators.required]],
        password: ['', [Validators.required]]
    });

    public authState    = signal<AuthState>('idle');
    public errorMessage = signal<string>('');

    public isLoading = computed(() => this.authState() === 'loading');

    async ngOnInit() {
        try {
            // 1. Obtener el token OAuth (client_credentials) para consumir la API
            await firstValueFrom(this.authService.fetchAndStoreToken());
            this.messageService.add({
                severity: 'success',
                summary: 'Sistema en línea',
                detail: 'Aplicación lista para procesar solicitudes'
            });

            // 2. Capturar y almacenar la IP real del cliente en sessionStorage.
            //    Se hace aquí (con el token ya disponible) para tenerla lista
            //    cuando se requiera en la auditoría de acceso post-login.
            this.capturarIpCliente();

        } catch (error) {
            this.messageService.add({
                severity: 'error',
                summary: 'Error de conexión',
                detail: 'No se pudo establecer conexión con el servidor'
            });
        }
    }

    public async onSubmit() {
        if (this.loginForm.invalid) {
            this.loginForm.markAllAsTouched();
            return;
        }

        this.authState.set('loading');
        this.errorMessage.set('');

        try {
            const credentials = this.loginForm.getRawValue() as { usuario: string; password: string };
            await firstValueFrom(this.authService.login(credentials));

            this.authState.set('success');
            // Login exitoso → navegar a la raíz (protegida por AuthGuard)
            await this.router.navigate(['/']);
        } catch (error: any) {
            this.authState.set('error');
            this.errorMessage.set(error.message || 'Error al conectar con el servidor.');
        }
    }

    private ipService      = inject(IpService);
    private sessionService = inject(SessionService);

    /**
     * Detecta la IP real de la máquina cliente (red LAN física e.g. 192.168.4.127) mediante IpService.
     * Persiste la IP en sessionStorage, localStorage y actualiza la Signal de sesión activa.
     */
    private async capturarIpCliente(): Promise<void> {
        try {
            const ip = await this.ipService.detectLocalIp();
            sessionStorage.setItem('CLIENT_IP', ip);
            localStorage.setItem('CLIENT_IP', ip);

            // Si ya hay sesión cargada en SessionService, actualizar la IP en la Signal
            const currentSession = this.sessionService.sessionData();
            if (currentSession) {
                currentSession.IP = ip;
                this.sessionService.setSessionData(currentSession);
            }
        } catch (e) {
            console.warn('[Login] No se pudo obtener la IP de la máquina local:', e);
            sessionStorage.setItem('CLIENT_IP', '127.0.0.1');
            localStorage.setItem('CLIENT_IP', '127.0.0.1');
        }
    }
}

