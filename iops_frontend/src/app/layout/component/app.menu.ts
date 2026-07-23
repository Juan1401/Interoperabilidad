import { Component, effect, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { MenuItem } from 'primeng/api';
import { AppMenuitem } from './app.menuitem';
import { SessionService } from '../../services/session.service';

@Component({
    selector: 'app-menu',
    standalone: true,
    imports: [CommonModule, AppMenuitem, RouterModule],
    template: `<ul class="layout-menu">
        @for (item of model; track item.label) {
            @if (!item.separator) {
                <li app-menuitem [item]="item" [root]="true"></li>
            } @else {
                <li class="menu-separator"></li>
            }
        }
    </ul> `
})
export class AppMenu implements OnInit {
    model: MenuItem[] = [];
    private sessionService = inject(SessionService);
    private router = inject(Router);

    constructor() {
        effect(() => {
            const data = this.sessionService.sessionData();
            this.updateMenu(data);
        });
    }

    ngOnInit() {
        // Initial setup handled by effect
    }

    updateMenu(sessionData: any) {
        const dashboardItems: MenuItem[] = [
            { label: 'Inicio', icon: 'pi pi-fw pi-home', routerLink: ['/'] },
            { label: 'Crear RDA Paciente', icon: 'pi pi-fw pi-user-plus', routerLink: ['/rda-paciente'] }
        ];

        // console.log('sessionData::', sessionData);
        if (sessionData) {
            if (sessionData.PERMISSION) {
                if (sessionData.PERMISSION.sw_envio_ihce == '1') {
                    dashboardItems.push({ label: 'Envío IHCE', icon: 'pi pi-fw pi-send', routerLink: ['/send-ihce'] });
                }
                if (sessionData.PERMISSION.sw_visor_ihce == '1') {
                    dashboardItems.push({ label: 'Consultar IHCE', icon: 'pi pi-fw pi-search', routerLink: ['/consultar-ihce'] });
                }
            } else {
                // Se requiere que si entra aca diga que no tiene permisos y no muestre nada y redireccione a acceso-no-autorizado
                dashboardItems.push({ label: 'Acceso no autorizado', icon: 'pi pi-fw pi-lock', routerLink: ['/notfound'] });
                this.router.navigate(['/notfound'], { queryParams: { message: 'No tiene permisos para acceder a esta funcionalidad.' } });
            }
        }

        this.model = [
            {
                label: 'Dashboard Interoperabilidad',
                items: dashboardItems
            }
        ];
    }
}
