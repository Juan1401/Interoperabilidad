import { Component, OnDestroy, OnInit, HostListener, computed, effect, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MessageService } from 'primeng/api';
import { ToastModule } from 'primeng/toast';
import { AppTopbar } from './app.topbar';
import { AppSidebar } from './app.sidebar';
import { AppFooter } from './app.footer';
import { LayoutService } from '@/app/layout/service/layout.service';

@Component({
    selector: 'app-layout',
    standalone: true,
    imports: [CommonModule, AppTopbar, AppSidebar, RouterModule, AppFooter, ToastModule],
    providers: [MessageService],
    template: `
    <p-toast position="top-center" key="print-block" life="4000"></p-toast>
    @if (tabDuplicada()) {
        <!-- Pantalla de bloqueo por pestaña duplicada -->
        <div style="
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: #0a0a1a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            font-family: 'Inter', sans-serif;
            text-align: center;
            padding: 2rem;
        ">
            <div style="
                background: rgba(239, 68, 68, 0.15);
                border: 2px solid rgba(239, 68, 68, 0.5);
                border-radius: 50%;
                width: 80px;
                height: 80px;
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <i class="pi pi-lock" style="font-size: 2.5rem; color: #ef4444;"></i>
            </div>
            <div>
                <h2 style="color: #f1f5f9; font-size: 1.5rem; margin: 0 0 0.75rem 0; font-weight: 700;">
                    Acceso bloqueado por seguridad
                </h2>
                <p style="color: #94a3b8; font-size: 1rem; margin: 0; max-width: 480px; line-height: 1.6;">
                    La aplicación clínica ya está abierta en otra pestaña del navegador.<br>
                    Por favor, cierre esta ventana y continúe en la pestaña original.
                </p>
            </div>
            <button
                (click)="cerrarTab()"
                style="
                    background: #ef4444;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    padding: 0.75rem 2rem;
                    font-size: 0.95rem;
                    font-weight: 600;
                    cursor: pointer;
                    margin-top: 0.5rem;
                ">
                Cerrar esta ventana
            </button>
        </div>
    } @else {
        <div class="layout-wrapper" [ngClass]="containerClass()">
            <app-topbar></app-topbar>
            <app-sidebar></app-sidebar>
            <div class="layout-main-container">
                <div class="layout-main">
                    <router-outlet></router-outlet>
                </div>
                <app-footer></app-footer>
            </div>
            <div class="layout-mask"></div>
        </div>
    }
    <!-- Aviso de seguridad: oculto en pantalla, visible solo al imprimir (@media print en styles.scss) -->
    <div class="print-security-notice" aria-hidden="true"></div>
    `
})
export class AppLayout implements OnInit, OnDestroy {

    layoutService = inject(LayoutService);
    private messageService = inject(MessageService);

    tabDuplicada = signal<boolean>(false);

    private channel!: BroadcastChannel;
    private pingTimeout: ReturnType<typeof setTimeout> | null = null;
    private readonly CHANNEL_NAME = 'clinica_single_instance';
    private readonly PING_TIMEOUT_MS = 500;

    constructor() {
        effect(() => {
            const state = this.layoutService.layoutState();
            if (state.mobileMenuActive) {
                document.body.classList.add('blocked-scroll');
            } else {
                document.body.classList.remove('blocked-scroll');
            }
        });
    }

    ngOnInit(): void {
        this.channel = new BroadcastChannel(this.CHANNEL_NAME);

        // Escuchar mensajes de otras pestañas
        this.channel.onmessage = (event) => {
            if (event.data?.type === 'PING') {
                // Hay una nueva pestaña intentando abrir → responder PONG
                this.channel.postMessage({ type: 'PONG' });
            } else if (event.data?.type === 'PONG') {
                // Recibimos PONG → hay otra pestaña activa
                if (this.pingTimeout !== null) {
                    clearTimeout(this.pingTimeout);
                    this.pingTimeout = null;
                }
                // Intentar cerrar inmediatamente (antes de que Angular renderice nada)
                window.close();

                // Si sigue abierta (el navegador bloqueó el cierre), mostrar overlay
                setTimeout(() => {
                    if (!window.closed) {
                        this.tabDuplicada.set(true);
                    }
                }, 150);
            }
        };

        // Enviar PING al abrir. Si nadie responde en 500ms → somos la única pestaña
        this.channel.postMessage({ type: 'PING' });

        this.pingTimeout = setTimeout(() => {
            // Nadie respondió → estamos solos, la app carga normalmente
            this.pingTimeout = null;
        }, this.PING_TIMEOUT_MS);
    }

    ngOnDestroy(): void {
        if (this.pingTimeout !== null) {
            clearTimeout(this.pingTimeout);
        }
        this.channel?.close();
    }

    /**
     * Intercepta el atajo de teclado Ctrl+P (Windows/Linux) y Cmd+P (macOS)
     * para bloquear la apertura del diálogo de impresión del navegador.
     *
     * NOTA DE SEGURIDAD: Este bloqueo por JavaScript NO es absoluto.
     * Un usuario avanzado puede imprimir usando el menú nativo del navegador
     * (Archivo > Imprimir) sin pasar por este listener.
     * Por eso, la regla @media print en styles.scss actúa como mecanismo
     * de respaldo obligatorio, ocultando el contenido incluso si se accede
     * a la impresión de forma directa.
     */
    @HostListener('window:keydown', ['$event'])
    onKeydown(event: KeyboardEvent): void {
        // Detectar Ctrl+P en Windows/Linux o Cmd+P en macOS
        const esPrintShortcut =
            (event.ctrlKey || event.metaKey) && event.key === 'p';

        if (esPrintShortcut) {
            // Cancelar el evento de impresión del navegador
            event.preventDefault();
            event.stopPropagation();

            // Notificar al usuario mediante un toast de PrimeNG
            this.messageService.add({
                key: 'print-block',
                severity: 'warn',
                summary: 'Acción no permitida',
                detail: 'La impresión de documentos clínicos está deshabilitada por política de seguridad.',
                life: 4000
            });
        }
    }

    cerrarTab(): void {
        // window.close() ya fue intentado al detectar la pestaña duplicada.
        // Como el navegador lo bloqueó (llegamos hasta aquí), limpiar el contenido
        // redirigiendo a about:blank — garantiza que los datos médicos queden ocultos.
        window.location.href = 'about:blank';
    }

    containerClass = computed(() => {
        const config = this.layoutService.layoutConfig();
        const state = this.layoutService.layoutState();
        return {
            'layout-overlay': config.menuMode === 'overlay',
            'layout-static': config.menuMode === 'static',
            'layout-static-inactive': state.staticMenuDesktopInactive && config.menuMode === 'static',
            'layout-overlay-active': state.overlayMenuActive,
            'layout-mobile-active': state.mobileMenuActive
        };
    });
}
