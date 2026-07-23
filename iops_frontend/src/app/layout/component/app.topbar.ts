import { Component, inject, OnInit, OnDestroy, signal, computed } from '@angular/core';
import { MenuItem } from 'primeng/api';
import { RouterModule, Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { StyleClassModule } from 'primeng/styleclass';
import { LayoutService } from '@/app/layout/service/layout.service';
import { SessionService } from '../../services/session.service';
import { MessageService } from 'primeng/api';

// import { Component, inject } from '@angular/core';
// import { MessageService } from 'primeng/api'; // Importante
// import { Router } from '@angular/router';

@Component({
    selector: 'app-topbar',
    standalone: true,
    imports: [RouterModule, CommonModule, StyleClassModule],
    template: `<div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button class="layout-menu-button layout-topbar-action" (click)="layoutService.onMenuToggle()">
                <i class="pi pi-bars"></i>
            </button>
            <a class="layout-topbar-logo" routerLink="/">
                <img [src]="layoutService.isDarkTheme() ? '/img/logo-topbar-dark.svg' : '/img/logo-topbar-light.svg'" alt="Logo" style="height: 40px;" />
            </a>
        </div>

        <!-- IP Central -->
        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center justify-center font-bold text-lg pointer-events-none" style="color: var(--text-color);">
            @if (userIp()) {
                <span>IP: {{ userIp() }}</span>
            }
        </div>



        <div class="layout-topbar-actions gap-2">
            <div class="layout-config-menu flex gap-2">
                <button type="button" class="layout-topbar-action" (click)="toggleDarkMode()">
                    <i [ngClass]="{ 'pi ': true, 'pi-moon': layoutService.isDarkTheme(), 'pi-sun': !layoutService.isDarkTheme() }"></i>
                </button>
            </div>

            <button class="layout-topbar-menu-button layout-topbar-action" pStyleClass="@next" enterFromClass="hidden" enterActiveClass="animate-scalein" leaveToClass="hidden" leaveActiveClass="animate-fadeout" [hideOnOutsideClick]="true">
                <i class="pi pi-ellipsis-v"></i>
            </button>

            <div class="layout-topbar-menu lg:flex">
                <div class="layout-topbar-menu-content flex flex-col lg:flex-row items-center justify-center gap-2">
                    <!-- Bloque de Fecha y Hora -->
                    <div class="flex flex-col text-right justify-center mr-2 tracking-tight" style="color: var(--text-color);">
                        <!--<div class="font-bold whitespace-nowrap">{{ currentDate() }}</div>-->
                        <div class="font-bold text-sm whitespace-nowrap">
                            <!-- {{ sessionData()?.name || 'USUARIO DESCONOCIDO' }} -->
                            {{ userData()?.name || 'USUARIO DESCONOCIDO' }}
                        </div>
                    </div>

                    <div class="flex items-center justify-center font-bold text-lg mr-2" style="color: var(--text-color);">
                        {{ currentTime() }}
                    </div>

                    <!--
                    <button type="button" class="layout-topbar-action">
                        <i class="pi pi-user"></i>
                        <span class="lg:hidden">Perfil</span>
                    </button>
                    -->

                    <!-- Botón Cerrar sesión -->
                    <button
                        type="button"
                        class="layout-topbar-action text-red-500 hover:bg-red-500 hover:text-white transition-all duration-200"
                        style="width: auto; padding: 0 1rem; border-radius: 8px; font-weight: bold; gap: 0.5rem;"
                        (click)="logout()"
                    >
                        <i class="pi pi-power-off"></i>
                        <span>Cerrar sesión</span>
                    </button>
                </div>
            </div>
        </div>
    </div>`
})

// @Component({
//     providers: [MessageService] // Asegúrate de tenerlo aquí o en el app.config
// })
// export class AppTopbarComponent {
//     private messageService = inject(MessageService);
//     private sessionService = inject(SessionService);
//     // ... otros injects
// }
export class AppTopbar implements OnInit, OnDestroy {
    items!: MenuItem[];

    layoutService = inject(LayoutService);
    messageService = inject(MessageService);
    sessionService = inject(SessionService);
    router = inject(Router);

    sessionData = this.sessionService.sessionData;
    userIp = computed(() => (this.sessionData() as any)?.IP || '');


    currentDate = signal<string>('');
    currentTime = signal<string>('');
    private timeInterval: any;

    userData = signal<any>(null);

    ngOnInit() {
        this.updateTime();
        this.timeInterval = setInterval(() => {
            this.updateTime();
        }, 1000);

        const guardado = sessionStorage.getItem('HL7_USER_INFO');
        if (guardado) {
            try {
                const usuarioObjeto = JSON.parse(guardado);
                this.userData.set(usuarioObjeto);
                //console.log('Datos cargados con éxito en el Topbar:', usuarioObjeto);
            } catch (error) {
                console.error('Error al parsear los datos del sessionStorage en Topbar:', error);
            }
        } else {
            console.warn('⚠️ No se encontró "HL7_USER_INFO" en el sessionStorage');
        }
    }

    ngOnDestroy() {
        if (this.timeInterval) {
            clearInterval(this.timeInterval);
        }
    }

    updateTime() {
        const now = new Date();

        const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        const diaSemana = dias[now.getDay()];
        const dia = String(now.getDate()).padStart(2, '0');
        const mes = meses[now.getMonth()];
        const anio = now.getFullYear();

        this.currentDate.set(`${diaSemana},${dia} de ${mes} de ${anio}`);

        const horas = String(now.getHours()).padStart(2, '0');
        const minutos = String(now.getMinutes()).padStart(2, '0');
        //const segundos = String(now.getSeconds()).padStart(2, '0');

        this.currentTime.set(`${horas}:${minutos}`);
    }

    toggleDarkMode() {
        this.layoutService.layoutConfig.update((state: any) => ({
            ...state,
            darkTheme: !state.darkTheme
        }));
    }

    logout() {
        localStorage.clear();
        sessionStorage.clear();
        this.sessionService.clearSession();
        // 2. Mostramos la alerta estilo Sakai
        this.messageService.add({
            severity: 'success',
            summary: 'Confirmación',
            detail: 'Sesión finalizada exitosamente.',
            life: 2000 // Duración de 2 segundos
        });

        // 3. Esperamos un breve momento para que el médico vea el mensaje y cerramos
        setTimeout(() => {
            window.close();

            // Fallback: Si el navegador bloquea window.close(), redirigimos a una página neutra
            // window.location.href = 'about:blank';
            window.location.href = 'http://localhost:4204/auth/login';
        }, 2000);
    }
}
