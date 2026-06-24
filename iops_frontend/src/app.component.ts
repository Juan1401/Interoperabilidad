import { Component, effect, inject, signal } from '@angular/core';
import { RouterModule, ActivatedRoute, Router } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { CryptoService } from './app/services/crypto.service';
import { AuthService } from './app/services/auth.service';
import { SessionService } from './app/services/session.service';
import { IdleService } from './app/services/idle.service';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { LoadingComponent } from './app/shared/components/loading/loading.component';
@Component({
    selector: 'app-root',
    standalone: true,
    imports: [RouterModule, ProgressSpinnerModule, ToastModule, ConfirmDialogModule, LoadingComponent],
    template: `
        <p-toast></p-toast>
        <p-confirmDialog [style]="{width: '50vw', maxWidth: '400px'}" [closable]="false" rejectButtonStyleClass="p-button-text">
            <ng-template pTemplate="message">
                <div class="flex flex-col items-center w-full gap-4 p-4">
                    <i class="pi pi-exclamation-triangle text-6xl text-yellow-500"></i>
                    <p class="text-center text-lg font-medium" style="color: var(--text-color);">{{ idleService.idleMessage() }}</p>
                </div>
            </ng-template>
        </p-confirmDialog>
        @if (isInitialProcessing()) {
            <div class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-surface-900/70 backdrop-blur-sm transition-opacity duration-300">
                @if (!errorMessage()) {
                   <p-progressSpinner ariaLabel="Cargando sistema..."></p-progressSpinner>
                   <div class="mt-4 text-white text-xl font-semibold">Inicializando sistema, por favor espere...</div>
                }
                @if (errorMessage()) {
                   <i class="pi pi-exclamation-triangle text-red-500 text-6xl mb-4"></i>
                   <div class="mt-4 text-white font-bold max-w-md text-center text-xl bg-red-500/20 p-4 rounded-lg border border-red-500/50">{{ errorMessage() }}</div>
                   <button (click)="retryInit()" class="mt-6 px-6 py-3 bg-primary text-white font-bold rounded-xl shadow-lg hover:bg-primary-emphasis transition-all flex items-center gap-2">
                       <i class="pi pi-refresh"></i> Reintentar
                   </button>
                }
            </div>
        }
        <router-outlet></router-outlet>
        <app-loading></app-loading>
    `
})
export class AppComponent {
    private route = inject(ActivatedRoute);
    private router = inject(Router);
    private queryParamMap = toSignal(this.route.queryParamMap);
    private cryptoService = inject(CryptoService);
    private authService = inject(AuthService);
    private sessionService = inject(SessionService);
    public idleService = inject(IdleService);

    // Signals para el flujo de carga y errores
    public isInitialProcessing = signal<boolean>(false);
    public errorMessage = signal<string | null>(null);
    private currentTokenParam: string | null = null;

    constructor() {
        effect(() => {
            const map = this.queryParamMap();
            if (map && map.has('token')) {
                this.currentTokenParam = map.get('token')!;
                this.processToken(this.currentTokenParam);
            }
        });
    }

    public retryInit() {
        if (this.currentTokenParam) {
            this.processToken(this.currentTokenParam);
        }
    }

    private processToken(tokenParam: string) {
        // Iniciar estado de carga bloqueante
        // this.isInitialProcessing.set(true);
        this.errorMessage.set(null);

        try {
            // 1. Token api del desarrollo 
            this.authService.fetchAndStoreToken().subscribe({
                next: (authRes: any) => {
                    const authToken = authRes.access_token;

                    // 2. Se obtiene el secretKey
                    this.authService.getSystemVariable('BioEstadistica', 'app', 'IHCE_key_secreta', authToken)
                        .subscribe({
                            next: (sysVarRes: any) => {
                                if (sysVarRes.success && sysVarRes.data && sysVarRes.data.length > 0) {
                                    const secretKey = sysVarRes.data[0].valor;

                                    // 3. Establecer la llave secreta
                                    this.cryptoService.setSecretKey(secretKey);

                                    // 4. Desencriptar el Token que viene por los params de la vista
                                    const data = this.cryptoService.decryptTokenPayload<any>(tokenParam);

                                    // console.log('Data desencriptada:');
                                    // console.log(data);

                                    this.sessionService.setSessionData(data);

                                    if (!data) {
                                        console.error('Abortando inicio de sesión: Token inválido o corrupto.');
                                        this.router.navigate(['/notfound'], { queryParams: { message: 'Abortando inicio de sesión: Token inválido o corrupto.' } });
                                        // this.isInitialProcessing.set(false); // Quitar overlay al redirigir
                                        return;
                                    }


                                    const tokenApi = sessionStorage.getItem('HL7_OAUTH_TOKEN');

                                    if (tokenApi) {
                                        if (!data?.UUID) {
                                            console.warn('¡Atención! La data desencriptada no tiene propiedad "uuid", lo cual podría causar un Error 422 en Laravel.', data);
                                        }
                                        const auditoriaData = {
                                            uuid: data?.UUID || '',
                                            usuario_id: data?.US != null ? String(data.US) : '',
                                            ip: data?.IP || '0.0.0.0',
                                            estado: 'Usado'
                                        };

                                        this.authService.registrarAuditoria(auditoriaData, tokenApi).subscribe({
                                            next: (auditoriaRes: any) => {
                                                console.log('Auditoría registrada exitosamente:', auditoriaRes);

                                                if (auditoriaRes && auditoriaRes.status) {
                                                    // ÉXITO TOTAL: Desaparece el overlay transparentemente y se navega
                                                    // this.isInitialProcessing.set(false);

                                                    if (data?.PERMISSION && data?.PERMISSION?.sw_envio_ihce && data?.PERMISSION?.sw_envio_ihce == '1') {
                                                        this.router.navigate(['/send-ihce']);
                                                    }
                                                    if (data?.PERMISSION && data?.PERMISSION?.sw_visor_ihce && data?.PERMISSION?.sw_visor_ihce == '1') {
                                                        this.router.navigate(['/consultar-ihce']);
                                                    }
                                                } else {
                                                    console.warn('La auditoría respondió con un error o estado no exitoso.', auditoriaRes.message);
                                                    this.router.navigate(['/notfound'], { queryParams: { message: 'La auditoría de acceso fue rechazada.', back: data?.BACK } });
                                                    // this.isInitialProcessing.set(false);
                                                }
                                            },
                                            error: (err: any) => {
                                                console.error('No se pudo registrar la auditoría:', err);
                                                // Mostrar botón de reintento reteniendo el overlay
                                                this.errorMessage.set('Error de comunicación al registrar la auditoría de acceso en el backend.');
                                                this.router.navigate(['/notfound'], { queryParams: { message: 'Error de comunicación al registrar la auditoría.', back: data?.BACK } });
                                                // this.isInitialProcessing.set(false);
                                            }
                                        });
                                    } else {
                                        console.error('Token API no encontrado, saltando auditoría.');
                                        this.router.navigate(['/notfound'], { queryParams: { message: 'Autenticación API faltante o corrupta.', back: data?.BACK } });
                                        // this.isInitialProcessing.set(false);
                                    }
                                } else {
                                    console.error('No se pudo obtener la llave secreta del sistema.');
                                    // Error crítico visualizado en pantalla (si no es redirrección) o redirijimos
                                    this.router.navigate(['/notfound'], { queryParams: { message: 'No se pudo obtener la llave secreta del sistema.' } });
                                    // this.isInitialProcessing.set(false);
                                }
                            },
                            error: (err: any) => {
                                console.error('Error al obtener la llave secreta del backend', err);
                                this.router.navigate(['/notfound'], { queryParams: { message: 'Error al obtener la llave secreta del backend.' } });
                                // Error de comunicación -> Reintento
                                this.errorMessage.set('Error al intentar obtener la llave de seguridad desde el servidor.');
                            }
                        });
                },
                error: (err: any) => {
                    console.error('El backend OAuth falló - Navegando sin privilegios API.', err);
                    this.router.navigate(['/notfound'], { queryParams: { message: 'El backend OAuth falló. Navegando sin privilegios API.' } });
                    // Error de comunicación -> Reintento
                    this.errorMessage.set('Fallo de conexión en validación OAuth. No se pudo acceder a la API.');
                }
            });

        } catch (e) {
            console.error('Error en el proceso de autenticación del token de URL', e);
            this.router.navigate(['/notfound'], { queryParams: { message: 'Fallo al procesar credenciales.' } });
            // this.isInitialProcessing.set(false);
        }
    }
}
