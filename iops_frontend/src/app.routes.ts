import { Routes } from '@angular/router';
import { authGuard } from './app/core/guards/auth.guard';
import { AppLayout } from './app/layout/component/app.layout';
import { Dashboard } from './app/pages/dashboard/dashboard';
import { Documentation } from './app/pages/documentation/documentation';
import { Landing } from './app/pages/landing/landing';
import { Notfound } from './app/pages/notfound/notfound';

// Import our new custom components
import { Inicio } from './app/pages/inicio/inicio';
import { EnvioIhce } from './app/pages/envio-ihce/envio-ihce';
import { AccesoNoAutorizado } from './app/pages/acceso-no-autorizado/acceso-no-autorizado';
import { ConsultarIhce } from './app/pages/consultar-ihce/consultar-ihce';
import { RdaPacienteFormComponent } from './app/pages/envio-ihce/components/rda-paciente-form/rda-paciente-form.component';

export const appRoutes: Routes = [
    {
        path: '',
        component: AppLayout,
        canActivate: [authGuard],
        children: [
            // Custom Dashboard Layout
            { path: '', component: Inicio },
            { path: 'send-ihce', component: EnvioIhce },
            { path: 'consultar-ihce', component: ConsultarIhce },
            { path: 'rda-paciente', component: RdaPacienteFormComponent },
            { path: 'acceso-no-autorizado', component: AccesoNoAutorizado },

            // Legacy Sakai Demo
            {
                path: 'demo',
                children: [
                    { path: '', component: Dashboard },
                    { path: 'uikit', loadChildren: () => import('./app/pages/uikit/uikit.routes') },
                    { path: 'documentation', component: Documentation },
                    { path: 'pages', loadChildren: () => import('./app/pages/pages.routes') }
                ]
            }
        ]
    },
    { path: 'landing', component: Landing },
    { path: 'notfound', component: Notfound },
    { path: 'auth', loadChildren: () => import('./app/pages/auth/auth.routes') },
    { path: '**', redirectTo: '/notfound' }
];
