import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { LoadingService } from '../../../core/services/loading.service';

@Component({
    selector: 'app-loading',
    standalone: true,
    imports: [CommonModule],
    template: `
        @if (loadingService.isLoading()) {
            <div class="loading-overlay">
                <div class="loading-container">
                    <!-- Contenedor del orbe/logo animado -->
                    <div class="orbe-wrapper">
                        <div class="glow-ring"></div>
                        <img src="/img/favicon.svg" alt="One Solution" class="logo-animation" />
                    </div>

                    <div class="loading-text">PROCESANDO...</div>
                </div>
            </div>
        }
    `,
    styles: [
        `
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                /* Glassmorphism sutil y premium */
                background-color: rgba(15, 23, 42, 0.5); /* Slate oscuro transparente */
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
            }

            .loading-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                gap: 2rem;
            }

            .orbe-wrapper {
                position: relative;
                width: 120px;
                height: 120px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* El logo pulsando sutilmente (breathing) */
            .logo-animation {
                width: 85px;
                height: 85px;
                position: relative;
                z-index: 2;
                animation: breathing 2.5s ease-in-out infinite;
                filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.5));
            }

            /* Un anillo brillante detrás del logo que se expande */
            .glow-ring {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 100%;
                height: 100%;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(0, 225, 217, 0.4) 0%, rgba(0, 123, 202, 0) 70%);
                z-index: 1;
                animation: pulseGlow 2.5s ease-in-out infinite;
            }

            .loading-text {
                color: #f8fafc; /* Texto claro para resaltar sobre el glassmorphism oscuro */
                font-family: 'Inter', sans-serif;
                font-size: 1.1rem;
                font-weight: 300;
                letter-spacing: 0.3em;
                text-transform: uppercase;
                animation: fadeText 2.5s ease-in-out infinite;
            }

            /* Animaciones */
            @keyframes breathing {
                0% { transform: scale(0.95); }
                50% { transform: scale(1.05); }
                100% { transform: scale(0.95); }
            }

            @keyframes pulseGlow {
                0% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.3; }
                50% { transform: translate(-50%, -50%) scale(1.4); opacity: 1; }
                100% { transform: translate(-50%, -50%) scale(0.8); opacity: 0.3; }
            }

            @keyframes fadeText {
                0% { opacity: 0.4; }
                50% { opacity: 1; text-shadow: 0 0 12px rgba(0, 225, 217, 0.5); }
                100% { opacity: 0.4; }
            }
        `
    ]
})
export class LoadingComponent {
    loadingService = inject(LoadingService);
}
