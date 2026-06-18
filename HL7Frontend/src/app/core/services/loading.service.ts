import { Injectable, signal } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class LoadingService {
  private activeRequests = 0;
  
  // Signal de solo lectura para los componentes visuales
  private _isLoading = signal<boolean>(false);
  readonly isLoading = this._isLoading.asReadonly();

  show() {
    this.activeRequests++;
    if (this.activeRequests === 1) {
      this._isLoading.set(true);
    }
  }

  hide() {
    this.activeRequests--;
    if (this.activeRequests === 0) {
      this._isLoading.set(false);
    }
    // Salvaguarda en caso de que alguna petición aborte anormalmente
    if (this.activeRequests < 0) {
      this.activeRequests = 0;
      this._isLoading.set(false);
    }
  }
}
