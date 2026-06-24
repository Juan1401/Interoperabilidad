import { Injectable, inject, OnDestroy, signal } from '@angular/core';
import { Router } from '@angular/router';
import { ConfirmationService } from 'primeng/api';
import { SessionService } from './session.service';
import { fromEvent, merge, Subject, timer, Subscription, BehaviorSubject } from 'rxjs';
import { switchMap, takeUntil, tap, finalize, takeWhile } from 'rxjs/operators';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class IdleService implements OnDestroy {
    private router = inject(Router);
    private sessionService = inject(SessionService);
    private confirmationService = inject(ConfirmationService);

    private destroy$ = new Subject<void>();
    private activityEvents$ = merge(fromEvent(document, 'mousemove'), fromEvent(document, 'keydown'), fromEvent(document, 'click'), fromEvent(document, 'scroll'));

    // Configuración desde environment
    private idleMinutes = environment.sessionInactivityMinutes || 30;
    private idleMilliseconds = this.idleMinutes * 60 * 1000;

    public idleMessage = signal<string>('');

    private countdownSubscription: Subscription | null = null;
    private idleSubscription: Subscription | null = null;

    constructor() {
        this.startWatching();
    }

    private startWatching(): void {
        this.stopWatching();

        this.idleSubscription = this.activityEvents$
            .pipe(
                takeUntil(this.destroy$),
                // Reinicia el timer cada vez que hay un evento
                switchMap(() => timer(this.idleMilliseconds))
            )
            .subscribe(() => {
                this.handleIdleLimitReached();
            });

        // Trigger initial timer
        document.dispatchEvent(new Event('mousemove'));
    }

    private stopWatching(): void {
        if (this.idleSubscription) {
            this.idleSubscription.unsubscribe();
            this.idleSubscription = null;
        }
    }

    private handleIdleLimitReached(): void {
        this.stopWatching(); // Pausamos la escucha mientras mostramos la alerta

        let countdown = 30; // 30 segundos para responder
        this.idleMessage.set(this.getAlertMessage(countdown));

        this.confirmationService.confirm({
            message: this.idleMessage(), // Fallback formativo
            header: 'Sesión inactiva',
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: 'Cerrar sesión ya',
            rejectLabel: 'Continuar sesión',
            accept: () => {
                this.logout();
            },
            reject: () => {
                this.clearCountdown();
                this.startWatching(); // Reanudar escucha
            }
        });

        // Actualizar el mensaje dinámicamente con el contador
        this.countdownSubscription = timer(0, 1000)
            .pipe(
                takeWhile(() => countdown > 0),
                tap(() => {
                    countdown--;
                    this.idleMessage.set(this.getAlertMessage(countdown));
                }),
                finalize(() => {
                    if (countdown === 0) {
                        this.confirmationService.close(); // Cerrar el diálogo programáticamente
                        this.logout();
                    }
                })
            )
            .subscribe();
    }

    private getAlertMessage(seconds: number): string {
        return `Su sesión está a punto de expirar por inactividad. Se cerrará automáticamente en ${seconds} segundos.`;
    }

    private clearCountdown(): void {
        if (this.countdownSubscription) {
            this.countdownSubscription.unsubscribe();
            this.countdownSubscription = null;
        }
    }

    private logout(): void {
        this.clearCountdown();
        this.stopWatching();

        localStorage.clear();
        sessionStorage.clear();
        this.sessionService.clearSession();

        // this.router.navigate(['/notfound'], { queryParams: { message: 'Sesión cerrada por inactividad.' } });
        setTimeout(() => {
            window.close();

            // Fallback: Si el navegador bloquea window.close(), redirigimos a una página neutra
            window.location.href = 'about:blank';
        }, 1000);
    }

    ngOnDestroy(): void {
        this.destroy$.next();
        this.destroy$.complete();
        this.clearCountdown();
    }
}
