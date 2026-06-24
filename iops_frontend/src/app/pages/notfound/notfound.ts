import { Component, inject, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ButtonModule } from 'primeng/button';
import { CommonModule } from '@angular/common';

@Component({
    selector: 'app-notfound',
    standalone: true,
    imports: [ButtonModule, CommonModule],
    template: ` <div class="flex items-center justify-center min-h-screen overflow-hidden">
        <div class="flex flex-col items-center justify-center">
            <img src="/demo/images/cn/logo-club-noel-2024-horizontal.png" alt="Logo" class="mb-8 w-64 shrink-0" />
            <div style="border-radius: 56px; padding: 0.3rem; background: linear-gradient(180deg, color-mix(in srgb, var(--primary-color), transparent 60%) 10%, var(--surface-ground) 30%)">
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-12 px-8 sm:px-20 flex flex-col items-center" style="border-radius: 53px">
                    <span class="text-primary font-bold text-3xl mb-2">Aviso del Sistema</span>
                    <h1 class="text-surface-900 dark:text-surface-0 font-bold text-2xl lg:text-4xl mb-4 text-center">Acceso no válido</h1>
                    <div class="text-surface-600 dark:text-surface-200 mb-8 text-center text-lg max-w-md">
                        {{ message }}
                    </div>
                    <p-button [label]="backUrl ? 'Regresar' : 'Ir al Inicio'" (onClick)="goBack()" />
                </div>
            </div>
        </div>
    </div>`
})
export class Notfound implements OnInit {
    private route = inject(ActivatedRoute);
    private router = inject(Router);

    public message: string = 'El recurso solicitado no está disponible o no tienes permisos.';
    public backUrl: string | null = null;

    ngOnInit() {
        this.route.queryParams.subscribe((params: any) => {
            if (params['message']) {
                this.message = params['message'];
            }
            if (params['back']) {
                this.backUrl = params['back'];
            }
        });
    }

    goBack() {
        if (this.backUrl) {
            window.location.href = this.backUrl;
        } else {
            this.router.navigate(['/']);
        }
    }
}
