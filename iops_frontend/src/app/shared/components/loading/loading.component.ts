import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { LoadingService } from '../../../core/services/loading.service';

@Component({
  selector: 'app-loading',
  standalone: true,
  imports: [CommonModule, ProgressSpinnerModule],
  template: `
    @if (loadingService.isLoading()) {
      <div class="loading-overlay">
        <div class="loading-container">
          <img src="/img/favicon.svg" alt="Logo Clínica" class="logo-animation" />
          
          <p-progressSpinner 
            ariaLabel="Cargando" 
            strokeWidth="4" 
            [style]="{width: '75px', height: '75px'}" 
            fill="transparent" 
            animationDuration=".8s">
          </p-progressSpinner>
          
          <div class="loading-text">
            Cargando la información, por favor espere...
          </div>
        </div>
      </div>
    }
  `,
  styles: [`
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      display: flex;
      align-items: center;      /* Centrado vertical */
      justify-content: center;   /* Centrado horizontal */
      z-index: 1099;           /* Por encima del layout, pero debajo de Toasts/Modals (1100+) */
      background-color: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
    }

    .loading-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      gap: 2rem; /* Espacio equilibrado entre elementos */
    }

    .logo-animation {
      width: 90px;
      height: auto;
      filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
      animation: heartBeat 1.5s ease-in-out infinite;
    }

    .loading-text {
      color: #1e293b; /* Un slate-800 más profesional */
      font-size: 1.25rem;
      font-weight: 600;
      letter-spacing: -0.025em;
      margin-top: 0.5rem;
    }

    /* Animación más suave tipo latido para clínica */
    @keyframes heartBeat {
      0% { transform: scale(1); }
      15% { transform: scale(1.1); }
      30% { transform: scale(1); }
      45% { transform: scale(1.15); }
      100% { transform: scale(1); }
    }

    /* Color corporativo para el spinner */
    :host ::ng-deep .p-progress-spinner-circle {
      stroke: #0ea5e9 !important; /* Azul médico por defecto si no carga el tema */
    }
  `]
})
export class LoadingComponent {
  loadingService = inject(LoadingService);
}
