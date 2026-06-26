import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { finalize } from 'rxjs/operators';
import { LoadingService } from '../services/loading.service';

export const loadingInterceptor: HttpInterceptorFn = (req, next) => {
    const loadingService = inject(LoadingService);

    // Verificar si la petición debe ser silenciosa (sin loader)
    if (req.headers.has('X-Skip-Loading')) {
        const clonedRequest = req.clone({ headers: req.headers.delete('X-Skip-Loading') });
        return next(clonedRequest);
    }

    loadingService.show();

    return next(req).pipe(
        finalize(() => {
            loadingService.hide();
        })
    );
};
