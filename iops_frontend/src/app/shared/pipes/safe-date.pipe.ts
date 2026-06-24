import { Pipe, PipeTransform } from '@angular/core';
import { DatePipe } from '@angular/common';

/**
 * Pipe seguro para fechas FHIR.
 * Convierte el valor a fecha solo si es válido. Si el valor es '---', null,
 * undefined o cualquier cadena no convertible, devuelve el fallback (por defecto '---').
 *
 * Uso en template: {{ valor | safeDate }}  o  {{ valor | safeDate:'dd/MM/yyyy' }}
 */
@Pipe({
  name: 'safeDate',
  standalone: true,
})
export class SafeDatePipe implements PipeTransform {
  private datePipe = new DatePipe('en-US');

  transform(value: any, format: string = 'yyyy-MM-dd', fallback: string = '---'): string {
    if (!value || value === '---' || value === '' || value === 'N/A') {
      return fallback;
    }

    try {
      const result = this.datePipe.transform(value, format);
      return result ?? fallback;
    } catch {
      return fallback;
    }
  }
}
